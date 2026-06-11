<?php

namespace App\Agent\Chat;

class AgentHeuristicParser
{
  /**
   * @return array{command?: string, input?: array<string, mixed>, reply?: string}
   */
  public function parse(string $message): array
  {
    $lower = mb_strtolower($message);
    $rentalCodigo = $this->matchCode($message, '/\b(LOC-[0-9]+)\b/i');
    $orderCodigo = $this->matchCode($message, '/\b(OS-[0-9]+)\b/i');
    $fatCodigo = $this->matchCode($message, '/\b(FAT-[0-9]+)\b/i');
    $titCodigo = $this->matchCode($message, '/\b(TIT-[0-9]+)\b/i');
    $patCodigo = $this->matchCode($message, '/\b(PAT-[A-Z0-9-]+)\b/i');

    if ($this->containsAny($lower, ['resumo financeiro', 'financeiro', 'inadimplência', 'a receber'])) {
      return [
        'command' => 'finance.summary',
        'input' => [],
        'reply' => 'Consultando resumo financeiro da empresa ativa.',
      ];
    }

    if ($this->containsAny($lower, ['pendências a faturar', 'pendencias a faturar', 'fila a faturar', 'fila faturamento', 'pendências faturamento'])) {
      return [
        'command' => 'billing.list_pending',
        'input' => [],
        'reply' => 'Listando pendências na fila a faturar.',
      ];
    }

    if ($this->containsAny($lower, ['listar locações', 'listar locacoes', 'locações ativas', 'locacoes ativas', 'locações locadas'])) {
      return [
        'command' => 'rental.list',
        'input' => [
          'status' => $this->containsAny($lower, ['locad', 'ativ']) ? 'locado' : null,
          'limit' => 20,
        ],
        'reply' => 'Listando locações.',
      ];
    }

    if ($this->containsAny($lower, ['buscar cliente', 'procurar cliente', 'cliente chamado', 'cliente cpf', 'cliente cnpj'])) {
      $query = $this->extractSearchQuery($message, ['buscar cliente', 'procurar cliente', 'cliente chamado', 'cliente cpf', 'cliente cnpj']);

      if ($query) {
        return [
          'command' => 'customer.search',
          'input' => ['q' => $query],
          'reply' => "Buscando clientes: {$query}.",
        ];
      }
    }

    if ($rentalCodigo && $this->containsAny($lower, ['ficha', 'contexto', 'status', 'detalhe', 'mostrar', 'ver loc'])) {
      return [
        'command' => 'rental.get',
        'input' => ['rental_codigo' => $rentalCodigo],
        'reply' => "Buscando ficha da locação {$rentalCodigo}.",
      ];
    }

    if ($rentalCodigo && $this->containsAny($lower, ['saída', 'saida', 'checkout', 'entregar'])) {
      return [
        'command' => 'rental.checkout',
        'input' => ['rental_codigo' => $rentalCodigo],
        'reply' => "Registrar saída da locação {$rentalCodigo}.",
      ];
    }

    if ($rentalCodigo && $this->containsAny($lower, ['retorno', 'devolver', 'devolução', 'devolucao'])) {
      return [
        'command' => 'rental.return',
        'input' => ['rental_codigo' => $rentalCodigo],
        'reply' => "Registrar retorno da locação {$rentalCodigo}.",
      ];
    }

    if ($rentalCodigo && $this->containsAny($lower, ['inspeção', 'inspecao', 'vistoria', 'concluir inspe'])) {
      return [
        'command' => 'rental.complete_inspection',
        'input' => [
          'rental_codigo' => $rentalCodigo,
          'outcome' => $this->containsAny($lower, ['manutenção', 'manutencao']) ? 'maintenance' : 'ok',
          'motivo' => $this->containsAny($lower, ['manutenção', 'manutencao']) ? 'Manutenção identificada na inspeção' : null,
        ],
        'reply' => "Concluir inspeção da locação {$rentalCodigo}.",
      ];
    }

    if ($fatCodigo && $this->containsAny($lower, ['faturar', 'gerar fatura', 'emitir'])) {
      return [
        'command' => 'billing.invoice_entry',
        'input' => ['entry_codigo' => $fatCodigo],
        'reply' => "Gerar fatura {$fatCodigo}.",
      ];
    }

    if ($fatCodigo && $this->containsAny($lower, ['autorizar'])) {
      return [
        'command' => 'billing.authorize_entry',
        'input' => ['entry_codigo' => $fatCodigo],
        'reply' => "Autorizar pendência {$fatCodigo}.",
      ];
    }

    if ($titCodigo && $this->containsAny($lower, ['baixar', 'pagar', 'pagamento', 'receber'])) {
      return [
        'command' => 'receivable.mark_paid',
        'input' => [
          'title_codigo' => $titCodigo,
          'payment_method' => $this->containsAny($lower, ['pix']) ? 'pix' : 'transferencia',
        ],
        'reply' => "Registrar pagamento do título {$titCodigo}.",
      ];
    }

    if ($orderCodigo && $this->containsAny($lower, ['aguardar peça', 'aguardar peca', 'esperar peça', 'esperar peca', 'aguardando peça'])) {
      return [
        'command' => 'maintenance.wait_part',
        'input' => [
          'order_codigo' => $orderCodigo,
          'observacao' => $this->extractAfter($message, ['motivo:', 'obs:']) ?? 'Aguardando peça via copiloto',
        ],
        'reply' => "Marcar OS {$orderCodigo} como aguardando peça.",
      ];
    }

    if ($orderCodigo && $this->containsAny($lower, ['retomar', 'resume', 'continuar execução', 'continuar execucao'])) {
      return [
        'command' => 'maintenance.resume',
        'input' => ['order_codigo' => $orderCodigo],
        'reply' => "Retomar execução da OS {$orderCodigo}.",
      ];
    }

    if ($orderCodigo && $this->containsAny($lower, ['iniciar', 'executar', 'começar', 'comecar'])) {
      return [
        'command' => 'maintenance.start',
        'input' => ['order_codigo' => $orderCodigo],
        'reply' => "Iniciar execução da OS {$orderCodigo}.",
      ];
    }

    if ($orderCodigo && $this->containsAny($lower, ['concluir', 'finalizar', 'encerrar'])) {
      return [
        'command' => 'maintenance.complete',
        'input' => ['order_codigo' => $orderCodigo],
        'reply' => "Concluir OS {$orderCodigo}.",
      ];
    }

    if ($patCodigo && $this->containsAny($lower, ['abrir os', 'nova os', 'ordem de serviço', 'manutenção', 'manutencao'])) {
      return [
        'command' => 'maintenance.open',
        'input' => [
          'asset_codigo' => $patCodigo,
          'descricao' => $this->extractAfter($message, ['problema:', 'motivo:', 'defeito:']) ?? 'Solicitação via copiloto',
        ],
        'reply' => "Abrir OS para patrimônio {$patCodigo}.",
      ];
    }

    if ($rentalCodigo) {
      return [
        'command' => 'rental.get',
        'input' => ['rental_codigo' => $rentalCodigo],
        'reply' => "Mostrando locação {$rentalCodigo}. Diga \"saída\", \"retorno\" ou \"inspeção\" para avançar no fluxo.",
      ];
    }

    return [
      'reply' => 'Mencione códigos como LOC-000001, OS-000001, FAT-000001 ou TIT-000001. Ex.: "resumo financeiro", "retorno LOC-000012", "faturar FAT-000003".',
    ];
  }

  private function matchCode(string $message, string $pattern): ?string
  {
    return preg_match($pattern, $message, $m) ? strtoupper($m[1]) : null;
  }

  /** @param  list<string>  $needles */
  private function containsAny(string $haystack, array $needles): bool
  {
    foreach ($needles as $needle) {
      if (str_contains($haystack, $needle)) {
        return true;
      }
    }

    return false;
  }

  /** @param  list<string>  $prefixes */
  private function extractAfter(string $message, array $prefixes): ?string
  {
    $lower = mb_strtolower($message);

    foreach ($prefixes as $prefix) {
      $pos = mb_strpos($lower, $prefix);

      if ($pos !== false) {
        $text = trim(mb_substr($message, $pos + mb_strlen($prefix)));

        return $text !== '' ? $text : null;
      }
    }

    return null;
  }

  /** @param  list<string>  $prefixes */
  private function extractSearchQuery(string $message, array $prefixes): ?string
  {
    $lower = mb_strtolower($message);

    foreach ($prefixes as $prefix) {
      $pos = mb_strpos($lower, $prefix);

      if ($pos !== false) {
        $text = trim(mb_substr($message, $pos + mb_strlen($prefix)));

        return $text !== '' ? $text : null;
      }
    }

    return null;
  }
}
