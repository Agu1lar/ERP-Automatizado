<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Models\User;
use App\Services\GlobalSearchService;

class SearchGlobalCommand extends AbstractReadAgentCommand
{
    public function __construct(
        private readonly GlobalSearchService $searchService,
    ) {}

    public static function name(): string
    {
        return 'search.global';
    }

    public static function description(): string
    {
        return 'Busca unificada em patrimônios, clientes, locações e categorias de equipamento.';
    }

    public function permission(): string
    {
        return 'dashboard.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['q'],
            'properties' => [
                'q' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $term = trim((string) $input['q']);

        if ($term === '') {
            return $this->failure('Informe o termo de busca.', 'validation_failed');
        }

        $limit = min(max((int) ($input['limit'] ?? 15), 1), 30);
        $directUrl = $this->searchService->resolveDirectUrl($term);
        $results = $this->searchService->fullResults($term);

        $assets = $results['assets']->take($limit)->values()->all();
        $customers = $results['customers']->take($limit)->values()->all();
        $rentals = $results['rentals']->take($limit)->values()->all();
        $categories = $results['categories']->take(5)->map(fn (array $cat) => [
            'id' => $cat['id'],
            'nome' => $cat['nome'],
            'total' => $cat['total'],
        ])->values()->all();

        $total = count($assets) + count($customers) + count($rentals) + count($categories);

        $message = $total === 0
            ? "Nenhum resultado para **{$term}**."
            : "**{$total}** resultado(s) para **{$term}**.";

        if ($directUrl) {
            $message .= "\n\nCorrespondência única — use o atalho direto.";
        }

        $actions = [];

        if ($directUrl) {
            $actions[] = ['label' => 'Abrir resultado', 'url' => $directUrl, 'primary' => true];
        }

        foreach ($assets as $assetRow) {
            if (! empty($assetRow['rental_url']) && ! empty($assetRow['secondary_url'])) {
                $actions[] = [
                    'label' => 'Contrato '.($assetRow['rental_codigo'] ?? ''),
                    'url' => $assetRow['rental_url'],
                    'primary' => empty($actions),
                ];
                $actions[] = [
                    'label' => 'Patrimônio '.($assetRow['codigo_patrimonio'] ?? ''),
                    'url' => $assetRow['secondary_url'],
                ];
            } elseif (! empty($assetRow['primary_url'])) {
                $actions[] = [
                    'label' => $assetRow['primary_label'] ?? 'Ver patrimônio',
                    'url' => $assetRow['primary_url'],
                ];
            }
        }

        $actions = collect($actions)
            ->unique('url')
            ->values()
            ->all();

        if ($actions === [] && $rentals !== []) {
            $actions[] = ['label' => 'Abrir locação', 'url' => $rentals[0]['url'] ?? null, 'primary' => true];
        }

        return $this->success(
            $message,
            [
                'entity' => 'global_search',
                'query' => $term,
                'direct_url' => $directUrl,
                'categories' => $categories,
                'assets' => $assets,
                'customers' => $customers,
                'rentals' => $rentals,
            ],
            array_values(array_filter($actions, fn ($a) => ! empty($a['url']))),
        );
    }
}
