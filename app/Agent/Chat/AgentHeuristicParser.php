<?php

namespace App\Agent\Chat;

use App\Support\EquipmentCategoryResolver;

class AgentHeuristicParser
{
  /**
   * @return array{command?: string, input?: array<string, mixed>, reply?: string}
   */
  public function parse(string $message): array
  {
    $lower = mb_strtolower($message);
    $rentalCodigo = $this->matchCode($message, '/\b(LOC-[0-9A-Z-]+)\b/i');
    $orderCodigo = $this->matchCode($message, '/\b(OS-[0-9]+)\b/i');
    $fatCodigo = $this->matchCode($message, '/\b(FAT-[0-9]+)\b/i');
    $titCodigo = $this->matchCode($message, '/\b(TIT-[0-9]+)\b/i');
    $patCodigo = $this->matchCode($message, '/\b(PAT-[A-Z0-9-]+)\b/i');
    $assetCodigo = $patCodigo ?? $this->matchCode($message, '/\b((?:AC|SM|PRT)-[A-Z0-9-]+)\b/i');
    $quoteCodigo = $this->matchCode($message, '/\b(ORC-[0-9]+)\b/i');

    if ($this->containsAny($lower, ['bloquear cliente', 'bloqueie cliente', 'cliente bloqueado'])) {
      $query = $this->extractCustomerNameForBilling($message) ?? $this->extractSearchQuery($message, ['bloquear cliente', 'bloqueie cliente']);

      if ($query) {
        return [
          'command' => 'customer.update',
          'input' => [
            'customer_name' => $query,
            'bloqueado' => true,
            'motivo_bloqueio' => $this->extractAfter($message, ['motivo:', 'por:']) ?? 'Bloqueio solicitado via copiloto',
          ],
          'reply' => "Bloquear cliente {$query}.",
        ];
      }
    }

    if ($this->containsAny($lower, ['desbloquear cliente', 'desbloqueie cliente', 'liberar cliente'])) {
      $query = $this->extractCustomerNameForBilling($message) ?? $this->extractSearchQuery($message, ['desbloquear cliente', 'desbloqueie cliente', 'liberar cliente']);

      if ($query) {
        return [
          'command' => 'customer.update',
          'input' => [
            'customer_name' => $query,
            'bloqueado' => false,
          ],
          'reply' => "Remover bloqueio do cliente {$query}.",
        ];
      }
    }

    if ($this->containsAny($lower, ['export contábil', 'export contabil', 'exportar contábil', 'exportar contabil', 'exportação contábil', 'exportacao contabil'])) {
      $format = null;

      foreach (['omie', 'bling', 'sisloc', 'csv'] as $candidate) {
        if (str_contains($lower, $candidate)) {
          $format = $candidate;
          break;
        }
      }

      return [
        'command' => 'finance.accounting_export',
        'input' => array_filter([
          'format' => $format,
          'status' => $this->containsAny($lower, ['pago', 'pagos']) ? 'pago' : 'aberto',
          'overdue' => $this->containsAny($lower, ['vencid', 'atrasad', 'inadimpl']) ? true : null,
        ]),
        'reply' => 'Montando exportação contábil — mostro quantos títulos entram no lote e o link para download.',
      ];
    }

    if ($this->containsAny($lower, ['buscar', 'procurar', 'pesquisar']) && ! $this->containsAny($lower, ['cliente', 'pessoa', 'contato crm'])) {
      $query = $this->extractSearchQuery($message, ['buscar', 'procurar', 'pesquisar']);

      if ($query && mb_strlen($query) >= 2) {
        return [
          'command' => 'search.global',
          'input' => ['q' => $query],
          'reply' => "Busca global: {$query}.",
        ];
      }
    }

    if ($this->containsAny($lower, ['inadimplência detalhada', 'inadimplencia detalhada', 'relatório inadimplência', 'relatorio inadimplencia', 'aging inadimpl'])) {
      $query = $this->extractCustomerNameForBilling($message);

      return [
        'command' => 'finance.delinquency',
        'input' => array_filter(['q' => $query]),
        'reply' => 'Consultando inadimplência com aging por cliente.',
      ];
    }

    if ($fatCodigo && $this->containsAny($lower, ['detalhe', 'detalhes', 'consultar', 'ver pendência', 'ver pendencia'])) {
      return [
        'command' => 'billing.get',
        'input' => ['entry_codigo' => $fatCodigo],
        'reply' => "Consultar pendência {$fatCodigo}.",
      ];
    }

    if ($this->containsAny($lower, ['resumo financeiro', 'financeiro', 'inadimplência', 'a receber'])) {
      return [
        'command' => 'finance.summary',
        'input' => [],
        'reply' => 'Consultando resumo financeiro da empresa ativa. Em seguida mostro os números e um atalho para a tela correspondente.',
      ];
    }

    if ($this->containsAny($lower, ['relatório comercial', 'relatorio comercial', 'faturamento por equipamento', 'faturamento comercial'])) {
      [$dateFrom, $dateTo] = $this->extractPeriod($message);

      return [
        'command' => 'report.commercial',
        'input' => array_filter([
          'date_from' => $dateFrom,
          'date_to' => $dateTo,
          'group_by' => $this->containsAny($lower, ['comercial', 'vendedor', 'responsável']) ? 'user' : null,
        ]),
        'reply' => 'Montando resumo do relatório comercial.',
      ];
    }

    if ($this->containsAny($lower, ['análise financeira', 'analise financeira', 'margem de locação', 'margem locacao', 'rentabilidade operacional'])) {
      [$dateFrom, $dateTo] = $this->extractPeriod($message);

      return [
        'command' => 'report.financial_analysis',
        'input' => array_filter([
          'date_from' => $dateFrom,
          'date_to' => $dateTo,
        ]),
        'reply' => 'Consultando análise financeira do período.',
      ];
    }

    if ($this->containsAny($lower, ['tabela de preços', 'tabela de precos', 'preços de locação', 'precos de locacao', 'valores de locação'])) {
      return [
        'command' => 'pricing.list',
        'input' => [],
        'reply' => 'Listando tabela de preços por categoria.',
      ];
    }

    if ($this->containsAny($lower, ['categorias de equipamento', 'listar categorias', 'cadastro de categorias'])) {
      return [
        'command' => 'category.list',
        'input' => [],
        'reply' => 'Listando categorias de equipamento.',
      ];
    }

    if ($this->containsAny($lower, ['modelos de equipamento', 'listar modelos', 'cadastro de modelos'])) {
      $category = EquipmentCategoryResolver::detectTermFromText($message);

      return [
        'command' => 'model.list',
        'input' => array_filter(['category_name' => $category]),
        'reply' => 'Listando modelos de equipamento.',
      ];
    }

    if ($this->containsAny($lower, ['catálogo de peças', 'catalogo de pecas', 'estoque de peças', 'peças abaixo do mínimo', 'pecas abaixo do minimo'])) {
      return [
        'command' => 'part.list',
        'input' => [
          'below_minimum_only' => $this->containsAny($lower, ['abaixo', 'mínimo', 'minimo', 'crítico', 'critico']),
        ],
        'reply' => 'Consultando catálogo de peças.',
      ];
    }

    if ($this->containsAny($lower, ['preventiva vencida', 'preventivas vencidas', 'manutenção preventiva vencida'])) {
      return [
        'command' => 'preventive.due',
        'input' => [],
        'reply' => 'Listando patrimônios com preventiva vencida.',
      ];
    }

    if ($this->containsAny($lower, ['regras preventivas', 'regra preventiva', 'cadastro preventiva'])) {
      return [
        'command' => 'preventive.list',
        'input' => [],
        'reply' => 'Listando regras de manutenção preventiva.',
      ];
    }

    if ($this->containsAny($lower, ['painel admin', 'administração', 'administracao', 'usuários do sistema', 'usuarios do sistema', 'auditoria do sistema'])) {
      return [
        'command' => 'admin.summary',
        'input' => [],
        'reply' => 'Consultando visão administrativa.',
      ];
    }

    if ($rentalCodigo && $this->containsAny($lower, ['pdf', 'contrato', 'imprimir', 'exportar'])) {
      $docType = $this->containsAny($lower, ['contrato']) ? 'rental_contract' : 'rental_summary';

      return [
        'command' => 'document.export',
        'input' => [
          'document_type' => $docType,
          'rental_codigo' => $rentalCodigo,
        ],
        'reply' => "Gerar PDF da locação {$rentalCodigo}.",
      ];
    }

    if ($orderCodigo && $this->containsAny($lower, ['pdf', 'imprimir', 'exportar'])) {
      return [
        'command' => 'document.export',
        'input' => [
          'document_type' => 'maintenance_order',
          'order_codigo' => $orderCodigo,
        ],
        'reply' => "Gerar PDF da OS {$orderCodigo}.",
      ];
    }

    if ($assetCodigo && $this->containsAny($lower, ['pdf', 'ficha', 'imprimir patrim', 'exportar patrim'])) {
      return [
        'command' => 'document.export',
        'input' => [
          'document_type' => 'asset_sheet',
          'asset_codigo' => $assetCodigo,
        ],
        'reply' => "Gerar PDF do patrimônio {$assetCodigo}.",
      ];
    }

    if ($fatCodigo && $this->containsAny($lower, ['pdf', 'imprimir', 'exportar']) && ! $this->containsAny($lower, ['faturar', 'autorizar', 'gerar fatura'])) {
      return [
        'command' => 'document.export',
        'input' => [
          'document_type' => 'billing_invoice',
          'entry_codigo' => $fatCodigo,
        ],
        'reply' => "Gerar PDF da fatura {$fatCodigo}.",
      ];
    }

    if ($this->containsAny($lower, ['lista do dia', 'logística do dia', 'logistica do dia', 'romaneio', 'entregas hoje', 'entregas de hoje', 'recolhidas hoje'])) {
      $date = $this->extractDateFromMessage($message);

      return [
        'command' => 'logistics.daily',
        'input' => array_filter([
          'date' => $date,
          'section' => $this->containsAny($lower, ['entrega', 'entregar']) && ! $this->containsAny($lower, ['retir', 'recolh', 'devolv'])
            ? 'entregas'
            : ($this->containsAny($lower, ['recolh', 'retirada']) ? 'retiradas' : null),
        ]),
        'reply' => $date
          ? "Lista logística do dia {$date}."
          : 'Lista logística de hoje — entregas, retiradas e movimentações no pátio.',
      ];
    }

    if ($this->containsAny($lower, ['pendências a faturar', 'pendencias a faturar', 'fila a faturar', 'fila faturamento', 'pendências faturamento'])) {
      return [
        'command' => 'billing.list_pending',
        'input' => [],
        'reply' => 'Listando pendências na fila a faturar. Você pode abrir a fila completa pelo atalho.',
      ];
    }

    if ($this->containsAny($lower, ['faturar ciclos', 'faturar pendências', 'faturar pendencias', 'faturar fila'])) {
      $customerQuery = $this->extractCustomerNameForBilling($message);

      if ($customerQuery) {
        return [
          'command' => 'billing.process_customer_pending',
          'input' => [
            'customer_name' => $customerQuery,
            'action' => $this->containsAny($lower, ['autorizar']) && ! $this->containsAny($lower, ['faturar', 'emitir']) ? 'authorize' : 'authorize_and_invoice',
          ],
          'reply' => "Processar pendências de faturamento de {$customerQuery}.",
        ];
      }
    }

    if ($this->containsAny($lower, ['criar orçamento', 'criar orcamento', 'novo orçamento', 'novo orcamento', 'abrir contrato', 'criar contrato', 'novo contrato', 'abrir um contrato'])) {
      return [
        'command' => 'quote.create',
        'input' => array_filter([
          'asset_codigo' => $assetCodigo,
          'customer_name' => $this->extractCustomerNameForBilling($message),
          'local_obra' => $this->extractAfter($message, ['obra:', 'local obra:', 'local da obra:']),
        ]),
        'reply' => 'Vou preparar um orçamento/pré-contrato — informe patrimônio e cliente se ainda não estiverem claros.',
      ];
    }

    if ($recentLimit = $this->matchRecentRentalsLimit($lower)) {
      return [
        'command' => 'rental.list',
        'input' => ['limit' => $recentLimit, 'sort' => 'recent'],
        'reply' => "Listando os {$recentLimit} contratos mais recentes.",
      ];
    }

    if ($this->isRentalStatsIntent($lower)) {
      $category = EquipmentCategoryResolver::resolveFromText($message);
      $categoryTerm = EquipmentCategoryResolver::detectTermFromText($message);
      [$dateFrom, $dateTo] = $this->extractPeriod($message);

      return [
        'command' => 'rental.stats',
        'input' => array_filter([
          'category_id' => $category?->id,
          'category_name' => $category?->nome,
          'category_query' => $category ? null : $categoryTerm,
          'date_from' => $dateFrom,
          'date_to' => $dateTo,
          'status' => $this->containsAny($lower, ['locad', 'em campo']) ? 'locado' : null,
        ]),
        'reply' => $category || $categoryTerm
          ? 'Contando locações por categoria no período informado.'
          : 'Contando locações no período informado.',
      ];
    }

    if ($assetCodigo && $this->isAssetSituationIntent($lower)) {
      return [
        'command' => 'asset.get',
        'input' => ['asset_codigo' => $assetCodigo],
        'reply' => "Consultando situação do patrimônio {$assetCodigo}.",
      ];
    }

    if ($this->isRentalFilterIntent($lower)) {
      $category = EquipmentCategoryResolver::resolveFromText($message);
      $categoryTerm = EquipmentCategoryResolver::detectTermFromText($message);
      $status = $this->containsAny($lower, ['locad', 'contrato', 'em campo', 'ativas']) ? 'locado' : null;

      return [
        'command' => 'rental.list',
        'input' => array_filter([
          'status' => $status ?? 'locado',
          'category_id' => $category?->id,
          'category_name' => $category?->nome,
          'category_query' => $category ? null : $categoryTerm,
          'limit' => 25,
        ]),
        'reply' => $category
          ? "Filtrando locações locadas de {$category->nome}."
          : ($categoryTerm
            ? 'Filtrando locações — vou usar a categoria pedida se estiver cadastrada.'
            : 'Filtrando locações conforme pedido.'),
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

    if ($quoteCodigo && $this->containsAny($lower, ['converter', 'converta', 'virar reserva', 'transformar em reserva'])) {
      return [
        'command' => 'quote.convert',
        'input' => ['quote_codigo' => $quoteCodigo],
        'reply' => "Converter orçamento {$quoteCodigo} em reserva.",
      ];
    }

    if ($quoteCodigo && $this->containsAny($lower, ['enviar orçamento', 'enviar orcamento', 'envia orçamento', 'envia orcamento'])) {
      return [
        'command' => 'quote.send',
        'input' => ['quote_codigo' => $quoteCodigo],
        'reply' => "Enviar orçamento {$quoteCodigo}.",
      ];
    }

    if ($quoteCodigo && $this->containsAny($lower, ['cancelar orçamento', 'cancelar orcamento', 'cancela orçamento'])) {
      return [
        'command' => 'quote.cancel',
        'input' => ['quote_codigo' => $quoteCodigo],
        'reply' => "Cancelar orçamento {$quoteCodigo}.",
      ];
    }

    if ($rentalCodigo && $this->containsAny($lower, ['renovação faturamento', 'renovacao faturamento', 'gerar renovação', 'gerar renovacao', 'renovar faturamento'])) {
      return [
        'command' => 'billing.create_renewal',
        'input' => ['rental_codigo' => $rentalCodigo],
        'reply' => "Gerar renovação de faturamento para {$rentalCodigo}.",
      ];
    }

    if ($this->containsAny($lower, ['buscar pessoa', 'procurar pessoa', 'contato crm'])) {
      $query = $this->extractSearchQuery($message, ['buscar pessoa', 'procurar pessoa', 'contato crm']);

      if ($query) {
        return [
          'command' => 'person.search',
          'input' => ['q' => $query],
          'reply' => "Buscando pessoas CRM: {$query}.",
        ];
      }
    }

    if ($assetCodigo && $this->containsAny($lower, ['mover patrim', 'mover para', 'transferir para pátio', 'transferir para patio', 'localização patrim'])) {
      $destino = $this->extractAfter($message, ['para:', 'para ']);

      return [
        'command' => 'asset.move_location',
        'input' => array_filter([
          'asset_codigo' => $assetCodigo,
          'destino' => $destino,
        ]),
        'reply' => $destino
          ? "Mover {$assetCodigo} para {$destino}."
          : "Mover {$assetCodigo} — informe o destino.",
      ];
    }

    if ($rentalCodigo && $this->containsAny($lower, ['cancelar', 'cancela reserva', 'cancelar reserva'])) {
      return [
        'command' => 'rental.cancel',
        'input' => [
          'rental_codigo' => $rentalCodigo,
          'reason' => $this->extractAfter($message, ['motivo:', 'por:']) ?? 'Cancelamento solicitado via copiloto',
        ],
        'reply' => "Cancelar reserva {$rentalCodigo}.",
      ];
    }

    if ($rentalCodigo && $this->containsAny($lower, ['prorrogar', 'prorrogação', 'prorrogacao', 'estender prazo', 'estender locação', 'estender locacao'])) {
      $date = $this->extractDateFromMessage($message);

      return [
        'command' => 'rental.extend',
        'input' => array_filter([
          'rental_codigo' => $rentalCodigo,
          'new_expected_return_at' => $date,
        ]),
        'reply' => $date
          ? "Prorrogar {$rentalCodigo} até {$date}."
          : "Prorrogar {$rentalCodigo} — informe a nova data de retorno (YYYY-MM-DD).",
      ];
    }

    if ($rentalCodigo && $this->containsAny($lower, ['substituir', 'substituição', 'substituicao', 'trocar equipamento', 'trocar patrim'])) {
      return [
        'command' => 'rental.substitute',
        'input' => array_filter([
          'rental_codigo' => $rentalCodigo,
          'new_asset_codigo' => $this->extractSubstituteAssetCode($message, $assetCodigo),
          'motivo' => $this->extractAfter($message, ['motivo:', 'por:']),
        ]),
        'reply' => $this->extractSubstituteAssetCode($message, $assetCodigo)
          ? 'Substituir equipamento na '.$rentalCodigo.' por '.$this->extractSubstituteAssetCode($message, $assetCodigo).'.'
          : "Substituir patrimônio na {$rentalCodigo} — informe o código do novo equipamento.",
      ];
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

    if ($assetCodigo && $this->containsAny($lower, ['abrir os', 'nova os', 'ordem de serviço', 'manutenção', 'manutencao'])) {
      return [
        'command' => 'maintenance.open',
        'input' => [
          'asset_codigo' => $assetCodigo,
          'descricao' => $this->extractAfter($message, ['problema:', 'motivo:', 'defeito:']) ?? 'Solicitação via copiloto',
        ],
        'reply' => "Abrir OS para patrimônio {$assetCodigo}.",
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
      'reply' => 'Posso **consultar** (filtros, fichas, patrimônio, resumos) ou **executar** ações (saída, retorno, faturar) — sempre com confirmação quando altera dados.'."\n\n"
        .'Exemplos:'."\n"
        .'• "Filtrar contratos com betoneiras locadas"'."\n"
        .'• "Quantos marteletes foram locados este mês?"'."\n"
        .'• "Situação do patrimônio AC-1001"'."\n"
        .'• "Resumo financeiro"'."\n"
        .'• "Retorno LOC-000012"',
    ];
  }

  private function isRentalStatsIntent(string $lower): bool
  {
    return $this->containsAny($lower, ['quantos', 'quantas', 'quantidade', 'contagem', 'total de'])
      && $this->containsAny($lower, ['locad', 'locaç', 'locac', 'alugad', 'contrato', 'locação', 'locacao']);
  }

  private function isAssetSituationIntent(string $lower): bool
  {
    return $this->containsAny($lower, [
      'situação do patrim', 'situacao do patrim', 'situação do pat', 'situacao do pat',
      'status do patrim', 'como está o patrim', 'como esta o patrim', 'me fale a situação',
      'me fale a situacao', 'me diga a situação', 'me diga a situacao',
    ]) || (
      $this->containsAny($lower, ['patrimônio', 'patrimonio', 'pat '])
      && $this->containsAny($lower, ['situação', 'situacao', 'status', 'como está', 'como esta', 'me fale', 'me diga'])
    );
  }

  /** @return array{0: string, 1: string} */
  private function extractPeriod(string $message): array
  {
    $lower = mb_strtolower($message);
    $now = now();

    if (str_contains($lower, 'mês passado') || str_contains($lower, 'mes passado')) {
      $start = $now->copy()->subMonth()->startOfMonth();
      $end = $now->copy()->subMonth()->endOfMonth();
    } elseif (str_contains($lower, 'este mês') || str_contains($lower, 'este mes')) {
      $start = $now->copy()->startOfMonth();
      $end = $now->copy()->endOfMonth();
    } elseif (str_contains($lower, 'este ano')) {
      $start = $now->copy()->startOfYear();
      $end = $now->copy()->endOfYear();
    } elseif (preg_match('/últimos?\s+(\d+)\s+dias/u', $lower, $matches)) {
      $start = $now->copy()->subDays((int) $matches[1])->startOfDay();
      $end = $now->copy()->endOfDay();
    } else {
      $start = $now->copy()->subDays(30)->startOfDay();
      $end = $now->copy()->endOfDay();
    }

    return [$start->toDateString(), $end->toDateString()];
  }

  private function matchRecentRentalsLimit(string $lower): ?int
  {
    $hasRentalTerm = $this->containsAny($lower, ['contrato', 'locaç', 'locac', 'locação', 'locacao']);
    $hasRecentTerm = $this->containsAny($lower, ['recent', 'últim', 'ultim', 'retorn', 'trazer', 'recente']);

    if (! $hasRentalTerm || ! $hasRecentTerm) {
      return null;
    }

    if (preg_match('/(\d+)\s+(contratos|locações|locacoes)/u', $lower, $matches)) {
      return min(max((int) $matches[1], 1), 50);
    }

    return 10;
  }

  private function isRentalFilterIntent(string $lower): bool
  {
    $hasFilterVerb = $this->containsAny($lower, [
      'filtr', 'filtro', 'mostr', 'listar', 'ver contrato', 'ver loca', 'quero que você', 'quero que voce',
    ]);

    $hasCategory = EquipmentCategoryResolver::detectTermFromText($lower) !== null
      || $this->containsAny($lower, ['categoria', 'equipamento']);

    return ($hasFilterVerb && ($hasCategory || str_contains($lower, 'contrato') || str_contains($lower, 'loca')))
      || ($hasCategory && $this->containsAny($lower, ['locad', 'contrato', 'ativas', 'em campo']));
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

  private function extractCustomerNameForBilling(string $message): ?string
  {
    $patterns = [
      '/(?:faturar\s+(?:ciclos\s+)?pendentes?\s+(?:da|de|do)\s+)(.+)$/iu',
      '/(?:faturar\s+(?:a\s+)?fila\s+(?:da|de|do)\s+)(.+)$/iu',
      '/(?:autorizar\s+pendências?\s+(?:da|de|do)\s+)(.+)$/iu',
    ];

    foreach ($patterns as $pattern) {
      if (preg_match($pattern, trim($message), $m)) {
        $name = trim($m[1], " \t\n\r\0\x0B\"'.,;");

        return $name !== '' ? $name : null;
      }
    }

    return null;
  }

  private function extractDateFromMessage(string $message): ?string
  {
    if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $message, $m)) {
      return $m[1];
    }

    if (preg_match('/\b(\d{2})\/(\d{2})\/(\d{4})\b/', $message, $m)) {
      return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
    }

    return null;
  }

  private function extractSubstituteAssetCode(string $message, ?string $fallbackAssetCode): ?string
  {
    if (preg_match('/\b(?:por|substituir por|trocar por)\s+((?:AC|SM|PRT|PAT)-[A-Z0-9-]+)\b/i', $message, $m)) {
      return strtoupper($m[1]);
    }

    return $fallbackAssetCode;
  }
}
