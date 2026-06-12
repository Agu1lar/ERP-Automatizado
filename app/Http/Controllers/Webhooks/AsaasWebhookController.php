<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\ReceivablePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AsaasWebhookController extends Controller
{
    public function __invoke(Request $request, ReceivablePaymentService $paymentService): JsonResponse
    {
        $token = config('payment.asaas.webhook_token');
        if ($token && $request->header('asaas-access-token') !== $token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $event = (string) $request->input('event', '');
        $payment = $request->input('payment', []);
        $chargeId = (string) ($payment['id'] ?? '');

        if ($chargeId === '') {
            return response()->json(['message' => 'ignored']);
        }

        $title = $paymentService->handleWebhookPayment(
            $chargeId,
            $event,
            isset($payment['value']) ? (float) $payment['value'] : null,
        );

        if ($title) {
            Log::info('Asaas webhook processed', [
                'event' => $event,
                'charge_id' => $chargeId,
                'title' => $title->codigo,
            ]);
        }

        return response()->json(['message' => 'ok']);
    }
}
