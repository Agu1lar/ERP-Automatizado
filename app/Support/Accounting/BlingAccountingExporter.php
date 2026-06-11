<?php

namespace App\Support\Accounting;

use App\Enums\ReceivableTitleStatus;
use App\Models\Domain\Finance\ReceivableTitle;

/**
 * Layout alinhado à planilha modelo do Bling (Contas a Receber via CSV, separador ;).
 *
 * @see https://ajuda.bling.com.br/hc/pt-br/articles/4410469923095
 */
class BlingAccountingExporter extends AbstractAccountingExporter
{
    public function headers(): array
    {
        return [
            'ID',
            'Cliente',
            'Data de Emissão',
            'Data de Vencimento',
            'Data de Liquidação',
            'Valor do Documento',
            'Valor Pago',
            'Situação',
            'Nº Documento',
            'Nº no Banco',
            'Categoria',
            'Histórico',
            'Portador',
            'Vencimento Original',
            'Forma de pagamento',
            'Competência',
            'CNPJ',
        ];
    }

    public function mapRow(ReceivableTitle $title): array
    {
        $title->loadMissing(['customer', 'rental']);

        $situacao = match ($title->statusEnum()) {
            ReceivableTitleStatus::Pago => 'pago',
            ReceivableTitleStatus::Cancelado => 'cancelada',
            default => 'aberto',
        };

        $emissao = $title->created_at ?? now();
        $vencimento = $title->vencimento;

        return [
            '',
            $title->customer->nome,
            $this->dateBr($emissao),
            $this->dateBr($vencimento),
            $title->statusEnum() === ReceivableTitleStatus::Pago ? $this->dateBr($title->updated_at) : '',
            $this->money($title->valor),
            $title->statusEnum() === ReceivableTitleStatus::Pago ? $this->money($title->valor) : '',
            $situacao,
            $title->codigo,
            '',
            config('accounting.bling.categoria'),
            'Locação '.($title->rental?->codigo ?? '').' — '.$title->parcelLabel(),
            config('accounting.bling.portador'),
            $this->dateBr($vencimento),
            config('accounting.bling.forma_pagamento'),
            $this->dateBr($emissao),
            $this->onlyDigits($title->customer->cpf_cnpj),
        ];
    }

    public function filename(): string
    {
        return 'bling-contas-receber-'.now()->format('Y-m-d').'.csv';
    }
}
