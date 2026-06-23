<?php

namespace App\Support\Agent;

use App\Agent\AgentCommandRegistry;
use App\Agent\AgentCommandResult;

/**
 * Traduz respostas internas do agente para linguagem amigável ao usuário final.
 */
class CopilotUserMessenger
{
    public function __construct(
        private readonly AgentCommandRegistry $registry,
        private readonly AgentCommandRequirementsRegistry $requirements,
    ) {}

    public function commandLabel(string $command): string
    {
        $definition = $this->requirements->definition($command);

        if ($definition !== null && filled($definition['label'] ?? '')) {
            return (string) $definition['label'];
        }

        if ($this->registry->has($command)) {
            return $this->registry->get($command)::description();
        }

        return 'esta ação';
    }

    public function fromCommandResult(AgentCommandResult $result): string
    {
        if ($result->ok) {
            return $this->sanitizeReply($result->message);
        }

        return $this->forError($result->message, $result->errorCode);
    }

    public function forError(?string $rawMessage, ?string $errorCode = null): string
    {
        $message = trim((string) $rawMessage);

        return match ($errorCode) {
            'forbidden' => 'Você não tem permissão para fazer isso. Se precisar, peça ajuda a um gestor.',
            'resource_conflict' => 'Esta ficha foi alterada enquanto eu preparava a ação. Atualize a página e tente de novo.',
            'internal_error' => 'Não consegui concluir agora. Tente novamente em instantes ou faça pela tela do sistema.',
            'dry_run_unsupported' => 'Ainda não consigo simular esta ação. Posso executá-la após sua confirmação.',
            default => $this->translateRawError($message, $errorCode),
        };
    }

    public function sanitizeReply(string $reply): string
    {
        $reply = preg_replace('/```json[\s\S]*?```/i', '', $reply) ?? $reply;
        $reply = preg_replace('/\[(Contexto estruturado[^\]]*)\][\s\S]*?```/i', '', $reply) ?? $reply;

        foreach ($this->registry->names() as $command) {
            $reply = str_replace($command, $this->commandLabel($command), $reply);
        }

        $reply = preg_replace('/\b[A-Za-z]+_[A-Za-z0-9_]+\b/', '', $reply) ?? $reply;
        $reply = preg_replace("/\n{3,}/", "\n\n", $reply) ?? $reply;

        return trim($reply);
    }

    public function unknownAction(): string
    {
        return 'Não peguei essa da primeira — me explica de outro jeito ou manda um código (LOC-, PAT-, FAT-, ORC-). Sem adivinhação.';
    }

    public function actionUnavailable(): string
    {
        return 'Essa função ainda não está no meu arsenal. Tenta pela tela correspondente no menu.';
    }

    public function noPermission(): string
    {
        return 'Por aqui não posso — falta permissão. Se precisar, chama um gestor.';
    }

    public function askModeExecutionBlocked(string $command): string
    {
        return 'No modo **Pergunta** eu só olho — não mexo em cadastro nem avanço fluxo. '
            .'Muda para **Agente** se quiser que eu **'.$this->commandLabel($command).'** (com confirmação, claro).';
    }

    public function confirmPrompt(string $command): string
    {
        return 'Consigo **'.$this->commandLabel($command).'** — só preciso do seu ok abaixo. Ou cancela e faz manual na tela, sem drama.';
    }

    public function cancelledOperation(string $command): string
    {
        return 'Beleza — operação **cancelada** (**'.$this->commandLabel($command).'**). Quando quiser, é só pedir de novo.';
    }

    public function llmOperationalFallbackNotice(?string $reason = null): string
    {
        $detail = match ($reason) {
            AgentLlmFailureClassifier::QUOTA_EXCEEDED => 'O **limite de tokens ou créditos** da IA foi atingido.',
            AgentLlmFailureClassifier::RATE_LIMIT => 'A IA recebeu **muitas solicitações** em sequência (limite temporário).',
            AgentLlmFailureClassifier::CONTEXT_LENGTH => 'A conversa ou o documento **excedeu o tamanho máximo** que o modelo aceita.',
            AgentLlmFailureClassifier::TIMEOUT => 'A IA **demorou demais** para responder.',
            AgentLlmFailureClassifier::AUTH_ERROR => 'A **configuração da IA** está incompleta ou incorreta.',
            AgentLlmFailureClassifier::SERVICE_UNAVAILABLE => 'O **serviço de IA está indisponível** no momento.',
            AgentLlmFailureClassifier::CONNECTION_ERROR => 'Não consegui **conectar à API de IA** (rede/SSL). No Windows de desenvolvimento, tente `AGENT_LLM_VERIFY_SSL=false` no `.env` ou instale o pacote CA do PHP.',
            default => 'A IA **não respondeu** como esperado.',
        };

        return '⚠️ **Inteligência operacional indisponível** — estou no modo “regras básicas”, que entende bem menos contexto (e pode interpretar torto).'
            ."\n\n{$detail} Vale **verificar plano/créditos da IA** ou falar com o administrador."
            ."\n\nEnquanto isso: telas do ERP, códigos LOC-/PAT-/ORC- ou pedidos bem objetivos. Eu tento, mas sem cérebro fica mais difícil.";
    }

    public function welcomeMessage(): string
    {
        return "E aí — sou a IA da **Acesso Equipamentos**, copiloto do ERP (José me montou; se tiver bug, já sabe com quem falar).\n\n"
            ."**Pergunta** — consulto fichas e relatórios. **Zero alteração** no sistema.\n\n"
            ."**Agente** — reserva, fatura, cadastro… **sempre com sua confirmação** (não saio clicando sozinha).\n\n"
            ."Me diz o que precisa ou manda PDF/imagem. Bora resolver.";
    }

    public function emptyPromptGreeting(): string
    {
        return 'Fala — o que vamos resolver? Descreve a tarefa, anexa um documento ou usa um atalho.';
    }

    private function translateRawError(string $message, ?string $errorCode): string
    {
        if ($message === '') {
            return match ($errorCode) {
                'validation_failed' => 'Faltam alguns dados para eu continuar. Veja o que pedi acima e complete no chat.',
                'business_rule' => 'Não dá para fazer isso com as informações atuais. Confira a ficha e tente outra forma.',
                default => 'Não foi possível concluir. Revise os dados e tente novamente.',
            };
        }

        if ($this->looksUserFriendly($message)) {
            return $this->sanitizeReply($message);
        }

        $lower = mb_strtolower($message);

        if (str_contains($lower, 'campo obrigatório') || str_contains($lower, 'informe um dos campos') || str_contains($lower, 'informe ')) {
            return $this->mapMissingFieldMessage($message);
        }

        if (str_contains($lower, 'não encontrad') || str_contains($lower, 'nao encontrad')) {
            return 'Não encontrei o registro informado. Confira o código ou nome e tente de novo.';
        }

        if (str_contains($lower, 'transição inválida') || str_contains($lower, 'transicao invalida')) {
            return 'Este patrimônio não pode mudar para o status pedido no momento. Verifique a situação atual na ficha.';
        }

        if (str_contains($lower, 'permissão') || str_contains($lower, 'permissao')) {
            return 'Você não tem permissão para fazer isso.';
        }

        return match ($errorCode) {
            'validation_failed' => 'Alguns dados estão incompletos ou incorretos. Ajuste conforme indicado e envie novamente.',
            'business_rule' => 'Não dá para fazer isso com as informações atuais. Confira a ficha e tente outra forma.',
            default => 'Não foi possível concluir. Revise os dados e tente novamente.',
        };
    }

    private function mapMissingFieldMessage(string $message): string
    {
        $map = [
            'rental' => 'Preciso identificar a **locação** (código LOC-…).',
            'asset' => 'Preciso identificar o **patrimônio** (código PAT-… ou similar).',
            'customer' => 'Preciso identificar o **cliente** (nome ou documento).',
            'quote' => 'Preciso identificar o **orçamento** (código ORC-…).',
            'entry' => 'Preciso identificar a **pendência de faturamento** (código FAT-…).',
            'title' => 'Preciso identificar o **título** (código TIT-…).',
            'order' => 'Preciso identificar a **ordem de serviço** (código OS-…).',
            'person' => 'Preciso identificar a **pessoa** (nome ou CPF).',
            'company' => 'Preciso identificar a **empresa** (nome ou CNPJ).',
            'reason' => 'Preciso do **motivo** da ação.',
            'destino' => 'Preciso informar o **destino** da movimentação.',
        ];

        $lower = mb_strtolower($message);

        foreach ($map as $needle => $friendly) {
            if (str_contains($lower, $needle)) {
                return $friendly;
            }
        }

        return 'Faltam dados para eu continuar. Complete as informações pedidas e envie novamente.';
    }

    private function looksUserFriendly(string $message): bool
    {
        if (str_contains($message, '{') || str_contains($message, '```')) {
            return false;
        }

        if (preg_match('/\b[a-z]+_[a-z0-9_]+\b/i', $message)) {
            return false;
        }

        if (preg_match('/\b[a-z]+\.[a-z_]+\b/', $message)) {
            return false;
        }

        return ! preg_match('/\b(API|SQL|Exception|validation_failed|HTTP)\b/i', $message);
    }
}
