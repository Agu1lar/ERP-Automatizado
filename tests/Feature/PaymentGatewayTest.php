<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\ReceivableTitleStatus;
use App\Enums\UserRole;
use App\Livewire\Finance\ReceivableIndex;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\User;
use App\Services\ReceivablePaymentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config(['payment.driver' => 'mock']);
    }

    public function test_mock_gateway_creates_pix_charge_on_title(): void
    {
        $user = $this->user(UserRole::Gestor);
        $title = $this->openTitle();

        $this->actingAs($user);

        $updated = app(ReceivablePaymentService::class)->createCharge($title, PaymentMethod::Pix);

        $this->assertNotNull($updated->gateway_charge_id);
        $this->assertSame('mock', $updated->gateway_driver);
        $this->assertNotNull($updated->pix_qr_code);
        $this->assertSame(ReceivableTitleStatus::Aberto->value, $updated->status);
    }

    public function test_asaas_webhook_marks_title_paid(): void
    {
        $title = $this->openTitle();
        $title->update([
            'gateway_driver' => 'asaas',
            'gateway_charge_id' => 'pay_test_123',
            'gateway_billing_type' => PaymentMethod::Pix->value,
        ]);

        $response = $this->postJson(route('webhooks.asaas'), [
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_test_123',
                'value' => 500,
            ],
        ]);

        $response->assertOk();
        $title->refresh();
        $this->assertSame(ReceivableTitleStatus::Pago->value, $title->status);
    }

    public function test_receivable_index_can_generate_charge(): void
    {
        $user = $this->user(UserRole::Gestor);
        $title = $this->openTitle();

        $this->actingAs($user);

        Livewire::test(ReceivableIndex::class)
            ->call('openChargeModal', $title->id)
            ->set('charge_method', PaymentMethod::Pix->value)
            ->call('generateCharge')
            ->assertHasNoErrors();

        $title->refresh();
        $this->assertNotNull($title->gateway_charge_id);
    }

    public function test_health_endpoint_returns_ok(): void
    {
        $this->get(route('health'))
            ->assertOk()
            ->assertJsonPath('checks.database', 'ok');
    }

    private function openTitle(): ReceivableTitle
    {
        $customer = Customer::create([
            'nome' => 'Cliente Gateway',
            'cpf_cnpj' => '12345678901',
            'ativo' => true,
        ]);

        return ReceivableTitle::create([
            'codigo' => 'TIT-GW-001',
            'customer_id' => $customer->id,
            'valor' => 500,
            'vencimento' => now()->addDays(7),
            'status' => ReceivableTitleStatus::Aberto->value,
        ]);
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }
}
