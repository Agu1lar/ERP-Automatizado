<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\User;
use App\Support\Accounting\AccountingExportRegistry;
use Illuminate\Database\Eloquent\Builder;

class FinanceAccountingExportCommand extends AbstractReadAgentCommand
{
    public function __construct(
        private readonly AccountingExportRegistry $exportRegistry,
    ) {}

    public static function name(): string
    {
        return 'finance.accounting_export';
    }

    public static function description(): string
    {
        return 'Monta URL de exportação contábil (CSV, Omie, Bling, Sisloc) e resume títulos incluídos no lote.';
    }

    public function permission(): string
    {
        return 'finance.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'format' => [
                    'type' => 'string',
                    'enum' => ['csv', 'omie', 'bling', 'sisloc'],
                    'description' => 'Formato do arquivo. Omita para listar todos os formatos disponíveis.',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['aberto', 'pago', 'cancelado'],
                    'description' => 'Filtrar por status do título. Padrão: aberto.',
                ],
                'overdue' => [
                    'type' => 'boolean',
                    'description' => 'Somente títulos vencidos.',
                ],
                'exclude_exported' => [
                    'type' => 'boolean',
                    'description' => 'Excluir títulos já exportados ao ERP. Padrão: true.',
                ],
                'mark_exported' => [
                    'type' => 'boolean',
                    'description' => 'Ao baixar o arquivo, marcar títulos como exportados. Padrão: true.',
                ],
                'preview_limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 20,
                ],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $status = $input['status'] ?? 'aberto';
        $overdue = (bool) ($input['overdue'] ?? false);
        $excludeExported = array_key_exists('exclude_exported', $input)
            ? (bool) $input['exclude_exported']
            : true;
        $markExported = array_key_exists('mark_exported', $input)
            ? (bool) $input['mark_exported']
            : true;
        $previewLimit = min(max((int) ($input['preview_limit'] ?? 10), 1), 20);

        $titles = $this->buildQuery($status, $overdue, $excludeExported)
            ->orderBy('vencimento')
            ->get();

        $total = (float) $titles->sum('valor');
        $formats = $this->exportRegistry->formats();
        $requestedFormat = isset($input['format']) ? strtolower((string) $input['format']) : null;

        if ($requestedFormat !== null && ! isset($formats[$requestedFormat])) {
            return $this->failure("Formato contábil desconhecido: {$requestedFormat}.", 'validation_failed');
        }

        $exportUrls = [];
        $targetFormats = $requestedFormat !== null
            ? [$requestedFormat => $formats[$requestedFormat]]
            : $formats;

        foreach (array_keys($targetFormats) as $format) {
            $exportUrls[$format] = route('finance.accounting.export', array_filter([
                'format' => $format,
                'status' => $status,
                'overdue' => $overdue ? 1 : null,
                'exclude_exported' => $excludeExported ? 1 : 0,
                'mark_exported' => $markExported ? 1 : 0,
            ], fn ($value) => $value !== null));
        }

        $filters = array_filter([
            'status' => $status,
            'overdue' => $overdue ?: null,
            'exclude_exported' => $excludeExported,
            'mark_exported' => $markExported,
        ], fn ($value) => $value !== null && $value !== false);

        $message = "**Exportação contábil** — **{$titles->count()}** título(s), total **R$ "
            .number_format($total, 2, ',', '.')."**.\n\n";

        if ($markExported) {
            $message .= 'Ao baixar o arquivo, os títulos serão marcados como exportados ao ERP. '
                ."Use `mark_exported: false` para gerar o arquivo sem marcar.\n\n";
        }

        $message .= 'Abra o link do formato desejado para baixar o CSV.';

        $actions = [];

        foreach ($targetFormats as $format => $meta) {
            $actions[] = [
                'label' => $meta['label'] ?? strtoupper($format),
                'url' => $exportUrls[$format],
                'primary' => ($requestedFormat ?? config('accounting.default_format', 'csv')) === $format,
            ];
        }

        $actions[] = ['label' => 'Títulos a receber', 'url' => route('finance.receivables')];

        return $this->success(
            $message,
            [
                'entity' => 'accounting_export',
                'filters' => $filters,
                'count' => $titles->count(),
                'total_valor' => round($total, 2),
                'formats' => collect($targetFormats)->map(fn (array $meta, string $format) => [
                    'format' => $format,
                    'label' => $meta['label'] ?? $format,
                    'description' => $meta['description'] ?? null,
                    'url' => $exportUrls[$format],
                ])->values()->all(),
                'titles_preview' => $titles->take($previewLimit)->map(fn (ReceivableTitle $t) => [
                    'id' => $t->id,
                    'codigo' => $t->codigo,
                    'customer_nome' => $t->customer?->nome,
                    'vencimento' => $t->vencimento?->toDateString(),
                    'valor' => (float) $t->valor,
                    'status' => $t->status,
                    'exportado_erp_em' => $t->exportado_erp_em?->toIso8601String(),
                ])->all(),
            ],
            $actions,
        );
    }

    private function buildQuery(string $status, bool $overdue, bool $excludeExported): Builder
    {
        return ReceivableTitle::query()
            ->with(['customer'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($overdue, fn ($q) => $q->overdue())
            ->when($excludeExported, fn ($q) => $q->notExportedToErp());
    }
}
