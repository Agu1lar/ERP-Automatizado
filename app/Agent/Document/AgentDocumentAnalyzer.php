<?php

namespace App\Agent\Document;

use App\Enums\CopilotMode;
use App\Models\User;
use App\Support\Agent\AgentLlmFailureClassifier;
use App\Support\Agent\AgentModeContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AgentDocumentAnalyzer
{
    public function __construct(
        private readonly DocumentTextExtractor $extractor,
    ) {}

    public function isConfigured(): bool
    {
        return (bool) config('agent.llm.enabled');
    }

    public function isAvailable(): bool
    {
        return $this->isConfigured()
            && filled(config('agent.llm.api_key'));
    }

    /**
     * @param  list<array{path: string, mime: string, original_name: string}>  $attachments
     */
    public function analyze(string $userPrompt, array $attachments, CopilotMode $mode, User $user): AgentDocumentAnalyzeResult
    {
        if (! $this->isConfigured()) {
            return AgentDocumentAnalyzeResult::skipped();
        }

        if (! filled(config('agent.llm.api_key'))) {
            return AgentDocumentAnalyzeResult::failure(AgentLlmFailureClassifier::AUTH_ERROR);
        }

        $documentBlocks = [];
        $visionImages = [];

        foreach ($attachments as $attachment) {
            $extracted = $this->extractor->extract(
                $attachment['path'],
                $attachment['mime'],
                $attachment['original_name'],
            );

            if ($extracted['method'] === 'vision') {
                $visionImages[] = [
                    'name' => $attachment['original_name'],
                    'base64' => base64_encode((string) Storage::disk('local')->get($attachment['path'])),
                    'mime' => $attachment['mime'],
                ];
            } elseif ($extracted['text'] !== '') {
                $documentBlocks[] = "### {$attachment['original_name']}\n".$extracted['text'];
            }
        }

        $documentsText = implode("\n\n", $documentBlocks);

        try {
            $userContent = $this->buildUserContent($userPrompt, $documentsText, $visionImages);

            $response = Http::withToken(config('agent.llm.api_key'))
                ->timeout((int) config('agent.llm.timeout', 60))
                ->post(rtrim((string) config('agent.llm.base_url'), '/').'/chat/completions', [
                    'model' => config('agent.llm.model'),
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->systemPrompt($mode),
                        ],
                        [
                            'role' => 'user',
                            'content' => $userContent,
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                $reason = AgentLlmFailureClassifier::fromHttp($response->status(), $response->body());
                Log::warning('Document analyze LLM failed', ['status' => $response->status(), 'reason' => $reason]);

                return AgentDocumentAnalyzeResult::failure($reason);
            }

            $raw = (string) $response->json('choices.0.message.content', '');
            $json = json_decode($raw, true);

            if (! is_array($json)) {
                return AgentDocumentAnalyzeResult::failure(AgentLlmFailureClassifier::UNKNOWN);
            }

            return AgentDocumentAnalyzeResult::success($this->normalizePlan($json, $mode));
        } catch (\Throwable $e) {
            $reason = AgentLlmFailureClassifier::fromException($e);
            Log::warning('Document analyze exception', ['error' => $e->getMessage(), 'reason' => $reason]);

            return AgentDocumentAnalyzeResult::failure($reason);
        }
    }

    private function systemPrompt(CopilotMode $mode): string
    {
        return AgentModeContext::forDocumentAnalysis($mode)."\n\nResponda JSON com:\n"
            ."- reply (markdown pt-BR)\n"
            ."- extracted (cliente, locacao, observacoes)\n"
            ."- proposed_actions: só comandos de execução no modo Agente; VAZIO no modo Pergunta.\n"
            ."Não invente CPF/CNPJ.";
    }

    /** @param  list<array{name: string, base64: string, mime: string}>  $visionImages @return array<int, array<string, mixed>|string> */
    private function buildUserContent(string $userPrompt, string $documentsText, array $visionImages): array
    {
        if ($visionImages === []) {
            return [
                "Pedido do usuário:\n{$userPrompt}\n\nConteúdo dos documentos:\n".($documentsText ?: '(sem texto extraível — descreva o que precisa)'),
            ];
        }

        $parts = [
            ['type' => 'text', 'text' => "Pedido do usuário:\n{$userPrompt}\n\nTexto extraído:\n".($documentsText ?: '(nenhum)')],
        ];

        foreach ($visionImages as $image) {
            $parts[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => "data:{$image['mime']};base64,{$image['base64']}",
                ],
            ];
        }

        return $parts;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{reply: string, extracted: array<string, mixed>, proposed_actions: list<array<string, mixed>>}
     */
    private function normalizePlan(array $json, CopilotMode $mode): array
    {
        $actions = $mode === CopilotMode::Agent
            ? array_values(array_filter(
                is_array($json['proposed_actions'] ?? null) ? $json['proposed_actions'] : [],
                fn ($a) => is_array($a) && ! empty($a['command']) && ! empty($a['params']),
            ))
            : [];

        return [
            'reply' => (string) ($json['reply'] ?? 'Análise concluída.'),
            'extracted' => is_array($json['extracted'] ?? null) ? $json['extracted'] : [],
            'proposed_actions' => $actions,
        ];
    }
}
