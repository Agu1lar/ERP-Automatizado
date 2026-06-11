<?php

namespace App\Support\Agent;

use App\Agent\AgentCommandRegistry;

class AgentCommandRequirementsRegistry
{
    /** @var array<string, array{label: string, required_groups: list<array{fields: list<string>, label: string, hint: string}>, recommended: list<array{key: string, label: string, hint: string}>}> */
    private array $definitions = [
        'quote.create' => [
            'label' => 'Criar orçamento / pré-contrato',
            'required_groups' => [
                [
                    'fields' => ['asset_id', 'asset_codigo'],
                    'label' => 'Patrimônio',
                    'hint' => 'Informe o código (ex.: PAT-001) ou ID do equipamento.',
                ],
                [
                    'fields' => ['customer_id', 'customer_cpf_cnpj', 'customer_name'],
                    'label' => 'Cliente',
                    'hint' => 'Nome, CPF/CNPJ ou ID do cliente de locação.',
                ],
            ],
            'recommended' => [
                ['key' => 'local_obra', 'label' => 'Local da obra', 'hint' => 'Endereço ou descrição do canteiro.'],
                ['key' => 'expected_return_at', 'label' => 'Previsão de retorno', 'hint' => 'Data prevista (AAAA-MM-DD).'],
                ['key' => 'pricing_period', 'label' => 'Período de cobrança', 'hint' => 'diaria, semanal ou mensal.'],
                ['key' => 'observacoes', 'label' => 'Observações', 'hint' => 'Notas comerciais ou operacionais.'],
            ],
        ],
        'rental.reserve' => [
            'label' => 'Abrir reserva / contrato de locação',
            'required_groups' => [
                [
                    'fields' => ['asset_id', 'asset_codigo'],
                    'label' => 'Patrimônio',
                    'hint' => 'Código PAT-… ou ID.',
                ],
                [
                    'fields' => ['customer_id', 'customer_cpf_cnpj', 'customer_name'],
                    'label' => 'Cliente',
                    'hint' => 'Nome, documento ou ID.',
                ],
            ],
            'recommended' => [
                ['key' => 'local_obra', 'label' => 'Local da obra', 'hint' => 'Onde o equipamento será usado.'],
                ['key' => 'expected_return_at', 'label' => 'Previsão de retorno', 'hint' => 'Data AAAA-MM-DD.'],
                ['key' => 'pricing_period', 'label' => 'Período', 'hint' => 'diaria, semanal ou mensal.'],
                ['key' => 'observacoes', 'label' => 'Observações', 'hint' => ''],
            ],
        ],
        'quote.convert' => [
            'label' => 'Converter orçamento em reserva',
            'required_groups' => [
                [
                    'fields' => ['quote_id', 'quote_codigo'],
                    'label' => 'Orçamento',
                    'hint' => 'Código ORC-… ou ID.',
                ],
            ],
            'recommended' => [],
        ],
        'quote.send' => [
            'label' => 'Enviar orçamento',
            'required_groups' => [
                [
                    'fields' => ['quote_id', 'quote_codigo'],
                    'label' => 'Orçamento',
                    'hint' => 'Código ORC-… ou ID.',
                ],
            ],
            'recommended' => [
                ['key' => 'validity_days', 'label' => 'Validade (dias)', 'hint' => 'Padrão: 7 dias.'],
            ],
        ],
        'rental.checkout' => [
            'label' => 'Registrar saída',
            'required_groups' => [
                [
                    'fields' => ['rental_id', 'rental_codigo'],
                    'label' => 'Locação',
                    'hint' => 'Código LOC-… ou ID.',
                ],
            ],
            'recommended' => [
                ['key' => 'horimetro_saida', 'label' => 'Horímetro de saída', 'hint' => ''],
            ],
        ],
        'rental.return' => [
            'label' => 'Registrar retorno',
            'required_groups' => [
                [
                    'fields' => ['rental_id', 'rental_codigo'],
                    'label' => 'Locação',
                    'hint' => 'Código LOC-… ou ID.',
                ],
            ],
            'recommended' => [
                ['key' => 'horimetro_retorno', 'label' => 'Horímetro de retorno', 'hint' => ''],
            ],
        ],
        'rental.cancel' => [
            'label' => 'Cancelar reserva',
            'required_groups' => [
                [
                    'fields' => ['rental_id', 'rental_codigo'],
                    'label' => 'Locação',
                    'hint' => 'Código LOC-… ou ID.',
                ],
                [
                    'fields' => ['reason'],
                    'label' => 'Motivo',
                    'hint' => 'Motivo do cancelamento.',
                ],
            ],
            'recommended' => [],
        ],
        'customer.create' => [
            'label' => 'Cadastrar cliente',
            'required_groups' => [
                [
                    'fields' => ['nome'],
                    'label' => 'Nome',
                    'hint' => 'Razão social ou nome completo.',
                ],
                [
                    'fields' => ['cpf_cnpj'],
                    'label' => 'CPF/CNPJ',
                    'hint' => 'Documento do cliente.',
                ],
            ],
            'recommended' => [
                ['key' => 'email', 'label' => 'E-mail', 'hint' => ''],
                ['key' => 'telefone', 'label' => 'Telefone', 'hint' => ''],
                ['key' => 'endereco', 'label' => 'Endereço', 'hint' => ''],
            ],
        ],
        'person.create' => [
            'label' => 'Cadastrar pessoa CRM',
            'required_groups' => [
                [
                    'fields' => ['nome'],
                    'label' => 'Nome',
                    'hint' => 'Nome completo.',
                ],
                [
                    'fields' => ['cpf'],
                    'label' => 'CPF',
                    'hint' => 'CPF válido.',
                ],
            ],
            'recommended' => [
                ['key' => 'email', 'label' => 'E-mail', 'hint' => ''],
                ['key' => 'telefone', 'label' => 'Telefone', 'hint' => ''],
                ['key' => 'company_id', 'label' => 'Empresa vinculada', 'hint' => 'ID da empresa CRM.'],
            ],
        ],
        'company.create' => [
            'label' => 'Cadastrar empresa CRM',
            'required_groups' => [
                [
                    'fields' => ['nome'],
                    'label' => 'Nome',
                    'hint' => 'Razão social ou nome fantasia.',
                ],
            ],
            'recommended' => [
                ['key' => 'cnpj', 'label' => 'CNPJ', 'hint' => ''],
                ['key' => 'tipo', 'label' => 'Tipo', 'hint' => 'propria, externa ou cliente.'],
                ['key' => 'endereco', 'label' => 'Endereço', 'hint' => ''],
            ],
        ],
        'asset.move_location' => [
            'label' => 'Mover patrimônio',
            'required_groups' => [
                [
                    'fields' => ['asset_id', 'asset_codigo'],
                    'label' => 'Patrimônio',
                    'hint' => 'Código PAT-… ou ID.',
                ],
                [
                    'fields' => ['destino'],
                    'label' => 'Destino',
                    'hint' => 'Nova localização (pátio, obra…).',
                ],
            ],
            'recommended' => [
                ['key' => 'motivo', 'label' => 'Motivo', 'hint' => ''],
            ],
        ],
        'billing.authorize_entry' => [
            'label' => 'Autorizar faturamento',
            'required_groups' => [
                [
                    'fields' => ['entry_id', 'entry_codigo'],
                    'label' => 'Pendência',
                    'hint' => 'Código FAT-… ou ID.',
                ],
            ],
            'recommended' => [],
        ],
        'receivable.mark_paid' => [
            'label' => 'Baixar título',
            'required_groups' => [
                [
                    'fields' => ['title_id', 'title_codigo'],
                    'label' => 'Título',
                    'hint' => 'Código TIT-… ou ID.',
                ],
            ],
            'recommended' => [
                ['key' => 'payment_method', 'label' => 'Forma de pagamento', 'hint' => 'pix, boleto, transferencia…'],
                ['key' => 'pago_em', 'label' => 'Data do pagamento', 'hint' => 'AAAA-MM-DD.'],
            ],
        ],
    ];

    public function __construct(
        private readonly AgentCommandRegistry $registry,
    ) {}

    /** @return array{label: string, required_groups: list<array{fields: list<string>, label: string, hint: string}>, recommended: list<array{key: string, label: string, hint: string}>}|null */
    public function definition(string $command): ?array
    {
        if (isset($this->definitions[$command])) {
            return $this->definitions[$command];
        }

        if (! $this->registry->has($command)) {
            return null;
        }

        return $this->inferFromSchema($command);
    }

    /** @return array{label: string, required_groups: list<array{fields: list<string>, label: string, hint: string}>, recommended: list<array{key: string, label: string, hint: string}>} */
    private function inferFromSchema(string $command): array
    {
        $schema = $this->registry->get($command)->inputSchema();
        $requiredGroups = [];

        foreach ($schema['oneOfRequired'] ?? [] as $group) {
            $requiredGroups[] = [
                'fields' => array_values($group),
                'label' => implode(' ou ', $group),
                'hint' => 'Informe um dos campos: '.implode(', ', $group),
            ];
        }

        foreach ($schema['required'] ?? [] as $field) {
            $requiredGroups[] = [
                'fields' => [$field],
                'label' => $field,
                'hint' => "Campo obrigatório: {$field}",
            ];
        }

        $propertyKeys = array_keys($schema['properties'] ?? []);
        $requiredFlat = array_merge(
            $schema['required'] ?? [],
            ...array_map(fn ($g) => $g['fields'], $requiredGroups),
        );

        $recommended = [];

        foreach ($propertyKeys as $key) {
            if (in_array($key, $requiredFlat, true)) {
                continue;
            }

            $recommended[] = [
                'key' => $key,
                'label' => str_replace('_', ' ', $key),
                'hint' => '',
            ];
        }

        return [
            'label' => $command,
            'required_groups' => $requiredGroups,
            'recommended' => array_slice($recommended, 0, 8),
        ];
    }
}
