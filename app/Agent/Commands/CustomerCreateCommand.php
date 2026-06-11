<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\Contracts\SupportsDryRun;
use App\Models\Domain\Customer\Customer;
use App\Models\User;
use App\Rules\ValidCpfCnpj;
use App\Support\CopilotNavigationLinks;
use Illuminate\Support\Facades\Validator;

class CustomerCreateCommand extends AbstractAgentCommand implements SupportsDryRun
{
    public static function name(): string
    {
        return 'customer.create';
    }

    public static function description(): string
    {
        return 'Cadastra um cliente a partir de dados estruturados (ex.: extraídos de documento).';
    }

    public function permission(): string
    {
        return 'customers.manage';
    }

    protected function declaredResourceTypes(): array
    {
        return ['customer'];
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['nome', 'cpf_cnpj'],
            'properties' => [
                'nome' => ['type' => 'string'],
                'cpf_cnpj' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'telefone' => ['type' => 'string'],
                'endereco' => ['type' => 'string'],
                'contato' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input, User $user): \App\Agent\AgentCommandResult
    {
        $data = Validator::make($input, [
            'nome' => ['required', 'string', 'max:255'],
            'cpf_cnpj' => ['required', 'string', new ValidCpfCnpj],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:50'],
            'endereco' => ['nullable', 'string', 'max:500'],
            'contato' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $digits = preg_replace('/\D/', '', (string) $data['cpf_cnpj']);

        $existing = Customer::query()->where('cpf_cnpj', $digits)->first();

        if ($existing) {
            return $this->success(
                "**Cliente já cadastrado:** {$existing->nome} ({$existing->formattedDocument()}).\n\nUse a ficha existente ou atualize manualmente.",
                ['entity' => 'customer', 'customer_id' => $existing->id, 'existing' => true],
                [
                    ['label' => 'Abrir ficha do cliente', 'url' => route('customers.show', $existing), 'primary' => true],
                ],
            );
        }

        $customer = Customer::create([
            'nome' => trim($data['nome']),
            'cpf_cnpj' => $digits,
            'email' => $data['email'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'endereco' => $data['endereco'] ?? null,
            'contato' => $data['contato'] ?? null,
            'ativo' => true,
            'created_by' => $user->id,
        ]);

        return $this->success(
            "**Cliente cadastrado:** {$customer->nome} — {$customer->formattedDocument()}.",
            ['entity' => 'customer', 'customer_id' => $customer->id, 'customer' => [
                'id' => $customer->id,
                'nome' => $customer->nome,
                'cpf_cnpj' => $customer->formattedDocument(),
            ]],
            [
                ['label' => 'Abrir ficha do cliente', 'url' => route('customers.show', $customer), 'primary' => true],
                ['label' => 'Ver clientes', 'url' => CopilotNavigationLinks::customers()],
            ],
        );
    }

    public function dryRun(array $input, User $user): AgentCommandResult
    {
        $nome = trim((string) ($input['nome'] ?? ''));
        $doc = trim((string) ($input['cpf_cnpj'] ?? ''));

        return AgentCommandResult::preview(
            "Simulação: cadastrar cliente **{$nome}** ({$doc}).",
            ['entity' => 'customer', 'dry_run' => true],
        );
    }
}
