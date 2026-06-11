<?php

namespace App\Support\Agent;

class AgentInputCompletionService
{
    public function __construct(
        private readonly AgentCommandRequirementsRegistry $requirements,
    ) {}

    /** @param  array<string, mixed>  $input */
    public function assess(string $command, array $input): AgentInputAssessment
    {
        $definition = $this->requirements->definition($command);

        if ($definition === null) {
            return new AgentInputAssessment($command, true, actionLabel: $command);
        }

        $missing = [];

        foreach ($definition['required_groups'] as $group) {
            if ($this->groupSatisfied($group['fields'], $input)) {
                continue;
            }

            $missing[] = [
                'key' => $group['fields'][0],
                'label' => $group['label'],
                'hint' => $group['hint'],
                'alternatives' => $group['fields'],
            ];
        }

        $recommended = [];

        foreach ($definition['recommended'] as $field) {
            $key = $field['key'];

            if ($this->fieldPresent($key, $input)) {
                continue;
            }

            $recommended[] = $field;
        }

        return new AgentInputAssessment(
            command: $command,
            complete: $missing === [],
            missing: $missing,
            recommended: $recommended,
            actionLabel: $definition['label'],
        );
    }

    /**
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    public function mergeFromMessage(string $message, array $existing, string $command): array
    {
        $merged = $existing;
        $extracted = $this->extractFromMessage($message);

        foreach ($extracted as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $merged[$key] = $value;
        }

        return $merged;
    }

    public function buildRequestMessage(AgentInputAssessment $assessment): string
    {
        $lines = [
            'Quase lá — para **'.$assessment->actionLabel.'** ainda faltam estes dados:',
            '',
        ];

        foreach ($assessment->missing as $field) {
            $lines[] = '• **'.$field['label'].'** — '.$field['hint'];
        }

        if ($assessment->recommended !== []) {
            $lines[] = '';
            $lines[] = '_Opcional (recomendado para um contrato/reserva mais completo):_';

            foreach ($assessment->recommended as $field) {
                $hint = filled($field['hint'] ?? '') ? ' — '.$field['hint'] : '';
                $lines[] = '• '.$field['label'].$hint;
            }
        }

        $lines[] = '';
        $lines[] = 'Manda no chat o que falta (ex.: patrimônio PAT-001, cliente Construtora X, obra Rua das Flores 100).';

        return implode("\n", $lines);
    }

    /** @return array<string, mixed> */
    private function extractFromMessage(string $message): array
    {
        $lower = mb_strtolower($message);
        $data = [];

        if (preg_match('/\b(LOC-[0-9A-Z-]+)\b/i', $message, $m)) {
            $data['rental_codigo'] = strtoupper($m[1]);
        }

        if (preg_match('/\b(ORC-[0-9]+)\b/i', $message, $m)) {
            $data['quote_codigo'] = strtoupper($m[1]);
        }

        if (preg_match('/\b(FAT-[0-9]+)\b/i', $message, $m)) {
            $data['entry_codigo'] = strtoupper($m[1]);
        }

        if (preg_match('/\b(TIT-[0-9A-Z-]+)\b/i', $message, $m)) {
            $data['title_codigo'] = strtoupper($m[1]);
        }

        if (preg_match('/\b(PAT-[A-Z0-9-]+)\b/i', $message, $m)) {
            $data['asset_codigo'] = strtoupper($m[1]);
        } elseif (preg_match('/\b((?:AC|SM|PRT)-[A-Z0-9-]+)\b/i', $message, $m)) {
            $data['asset_codigo'] = strtoupper($m[1]);
        }

        if (preg_match('/\b(\d{3}\.\d{3}\.\d{3}-\d{2}|\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}|\d{11}|\d{14})\b/', $message, $m)) {
            $digits = preg_replace('/\D/', '', $m[1]);

            if (strlen($digits) === 11) {
                $data['customer_cpf_cnpj'] = $digits;
                $data['cpf'] = $digits;
            } elseif (strlen($digits) === 14) {
                $data['customer_cpf_cnpj'] = $digits;
                $data['cnpj'] = $digits;
            }
        }

        if (preg_match('/(?:cliente|para)\s*[:\-]?\s*(.+?)(?:,|;|$|\n)/iu', $message, $m)) {
            $name = trim(preg_replace('/^(cliente|para)\s+/iu', '', trim($m[1])));

            if ($name !== '' && ! preg_match('/^(PAT-|LOC-|ORC-)/i', $name)) {
                $data['customer_name'] = $name;
            }
        }

        if (preg_match('/(?:obra|local(?:\s+da\s+obra)?|endereço|endereco)\s*[:\-]?\s*(.+?)(?:,|;|$|\n)/iu', $message, $m)) {
            $data['local_obra'] = trim($m[1]);
        }

        if (preg_match('/(?:motivo|razão|razao)\s*[:\-]?\s*(.+?)(?:,|;|$|\n)/iu', $message, $m)) {
            $data['reason'] = trim($m[1]);
            $data['motivo_bloqueio'] = trim($m[1]);
        }

        if (preg_match('/(?:destino|mover para|transferir para)\s*[:\-]?\s*(.+?)(?:,|;|$|\n)/iu', $message, $m)) {
            $data['destino'] = trim($m[1]);
        }

        if (preg_match('/(?:retorno|devolução|devolucao|até|ate)\s*[:\-]?\s*(\d{4}-\d{2}-\d{2}|\d{1,2}\/\d{1,2}\/\d{2,4})/iu', $message, $m)) {
            $data['expected_return_at'] = $this->normalizeDate($m[1]);
        } elseif (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $message, $m)) {
            $data['expected_return_at'] = $m[1];
        }

        if (str_contains($lower, 'diária') || str_contains($lower, 'diaria')) {
            $data['pricing_period'] = 'diaria';
        } elseif (str_contains($lower, 'semanal')) {
            $data['pricing_period'] = 'semanal';
        } elseif (str_contains($lower, 'mensal')) {
            $data['pricing_period'] = 'mensal';
        }

        if (preg_match('/(?:nome)\s*[:\-]?\s*(.+?)(?:,|;|$|\n)/iu', $message, $m) && ! isset($data['customer_name'])) {
            $data['nome'] = trim($m[1]);
        }

        if (preg_match('/(?:validade|válido por|valido por)\s*[:\-]?\s*(\d+)\s*dias?/iu', $message, $m)) {
            $data['validity_days'] = (int) $m[1];
        }

        return $data;
    }

    private function normalizeDate(string $value): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value)) {
            return $value;
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $value, $m)) {
            $year = strlen($m[3]) === 2 ? '20'.$m[3] : $m[3];

            return sprintf('%04d-%02d-%02d', (int) $year, (int) $m[2], (int) $m[1]);
        }

        return $value;
    }

    /** @param  list<string>  $fields @param  array<string, mixed>  $input */
    private function groupSatisfied(array $fields, array $input): bool
    {
        foreach ($fields as $field) {
            if ($this->fieldPresent($field, $input)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $input */
    private function fieldPresent(string $field, array $input): bool
    {
        if (! array_key_exists($field, $input)) {
            return false;
        }

        $value = $input[$field];

        if ($value === null || $value === '') {
            return false;
        }

        return true;
    }
}
