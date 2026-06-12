<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Data\PaymentChargeResult;
use App\Enums\PaymentChargeStatus;
use App\Enums\PaymentMethod;
use App\Models\Domain\Finance\ReceivableTitle;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class AsaasPaymentGateway implements PaymentGateway
{
    public function createCharge(ReceivableTitle $title, PaymentMethod $method): PaymentChargeResult
    {
        $title->loadMissing('customer');
        $customer = $title->customer;

        $cpfCnpj = preg_replace('/\D/', '', (string) $customer->cpf_cnpj);
        if (strlen($cpfCnpj) < 11) {
            throw new InvalidArgumentException('Cliente sem CPF/CNPJ válido para cobrança no Asaas.');
        }

        $billingType = match ($method) {
            PaymentMethod::Pix => 'PIX',
            PaymentMethod::Boleto => 'BOLETO',
            default => throw new InvalidArgumentException('Forma de pagamento não suportada pelo Asaas.'),
        };

        $payload = [
            'customer' => $this->resolveCustomerId($title, $cpfCnpj, $customer->nome, $customer->email, $customer->telefone),
            'billingType' => $billingType,
            'value' => round((float) $title->valor, 2),
            'dueDate' => $title->vencimento->format('Y-m-d'),
            'description' => $title->observacoes ?: "Título {$title->codigo}",
            'externalReference' => $title->codigo,
        ];

        $response = $this->client()->post('/v3/payments', $payload)->throw();
        $data = $response->json();

        return $this->mapResponse($data, $method);
    }

    public function refreshCharge(ReceivableTitle $title): PaymentChargeResult
    {
        if (! $title->gateway_charge_id) {
            throw new InvalidArgumentException('Título sem cobrança no gateway.');
        }

        $response = $this->client()->get('/v3/payments/'.$title->gateway_charge_id)->throw();
        $method = $title->gateway_billing_type
            ? PaymentMethod::from(strtolower($title->gateway_billing_type))
            : PaymentMethod::Pix;

        return $this->mapResponse($response->json(), $method);
    }

    private function resolveCustomerId(
        ReceivableTitle $title,
        string $cpfCnpj,
        string $name,
        ?string $email,
        ?string $phone,
    ): string {
        $search = $this->client()->get('/v3/customers', [
            'cpfCnpj' => $cpfCnpj,
        ])->throw();

        $existing = collect($search->json('data', []))->first();
        if ($existing && ! empty($existing['id'])) {
            return (string) $existing['id'];
        }

        $created = $this->client()->post('/v3/customers', array_filter([
            'name' => $name,
            'cpfCnpj' => $cpfCnpj,
            'email' => $email,
            'mobilePhone' => $phone ? preg_replace('/\D/', '', $phone) : null,
            'externalReference' => 'customer_'.$title->customer_id,
        ]))->throw();

        return (string) $created->json('id');
    }

    /** @param array<string, mixed> $data */
    private function mapResponse(array $data, PaymentMethod $method): PaymentChargeResult
    {
        $status = $this->mapStatus((string) ($data['status'] ?? 'PENDING'));

        $pixQrCode = null;
        $pixQrImageUrl = null;
        $boletoUrl = $data['bankSlipUrl'] ?? null;
        $invoiceUrl = $data['invoiceUrl'] ?? null;

        if ($method === PaymentMethod::Pix && ! empty($data['id'])) {
            try {
                $pix = $this->client()->get('/v3/payments/'.$data['id'].'/pixQrCode')->throw()->json();
                $pixQrCode = $pix['payload'] ?? null;
                $pixQrImageUrl = $pix['encodedImage'] ?? null;
                if ($pixQrImageUrl && ! str_starts_with($pixQrImageUrl, 'http')) {
                    $pixQrImageUrl = 'data:image/png;base64,'.$pixQrImageUrl;
                }
            } catch (RequestException) {
                // QR pode não estar pronto imediatamente
            }
        }

        return new PaymentChargeResult(
            chargeId: (string) $data['id'],
            status: $status,
            pixQrCode: $pixQrCode,
            pixQrImageUrl: $pixQrImageUrl,
            boletoUrl: $boletoUrl,
            invoiceUrl: $invoiceUrl,
        );
    }

    private function mapStatus(string $asaasStatus): PaymentChargeStatus
    {
        return match (strtoupper($asaasStatus)) {
            'RECEIVED', 'RECEIVED_IN_CASH' => PaymentChargeStatus::Received,
            'CONFIRMED' => PaymentChargeStatus::Confirmed,
            'OVERDUE' => PaymentChargeStatus::Overdue,
            'CANCELED', 'DELETED' => PaymentChargeStatus::Cancelled,
            default => PaymentChargeStatus::Pending,
        };
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        $apiKey = config('payment.asaas.api_key');
        if (! $apiKey) {
            throw new InvalidArgumentException('ASAAS_API_KEY não configurada.');
        }

        return Http::baseUrl(rtrim(config('payment.asaas.base_url'), '/'))
            ->withHeaders([
                'access_token' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->acceptJson()
            ->timeout(30);
    }
}
