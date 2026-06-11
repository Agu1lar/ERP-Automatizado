<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Mail\OperationalDigestMail;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\AssetStatusService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OperationalAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_operational_alerts_command_sends_digest_for_overdue_return(): void
    {
        Mail::fake();

        $user = $this->gestorUser();
        $customer = Customer::create([
            'nome' => 'Cliente Atraso',
            'cpf_cnpj' => '39053344705',
            'ativo' => true,
        ]);

        $asset = $this->asset('PAT-ALERT-1');
        Rental::create([
            'codigo' => 'LOC-ALERT-1',
            'customer_id' => $customer->id,
            'asset_id' => $asset->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => now()->subDays(10),
            'checkout_at' => now()->subDays(10),
            'expected_return_at' => now()->subDays(2),
        ]);

        $this->artisan('notifications:operational-alerts')
            ->assertSuccessful();

        Mail::assertSent(OperationalDigestMail::class, function (OperationalDigestMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && isset($mail->sections['overdue_returns'])
                && $mail->sections['overdue_returns']->count() === 1;
        });
    }

    public function test_operational_alerts_skips_when_no_alerts(): void
    {
        Mail::fake();

        $this->gestorUser();

        $this->artisan('notifications:operational-alerts')
            ->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_operational_alerts_includes_overdue_maintenance_order(): void
    {
        Mail::fake();

        $user = $this->gestorUser();
        $asset = $this->asset('PAT-OS-ALERT');

        MaintenanceOrder::create([
            'codigo' => 'OS-ALERT-1',
            'asset_id' => $asset->id,
            'status' => MaintenanceOrderStatus::Aberta->value,
            'tipo' => 'corretiva',
            'descricao_problema' => 'Teste',
            'opened_at' => now()->subDays(5),
            'expected_completion_at' => now()->subDay(),
        ]);

        $this->artisan('notifications:operational-alerts')
            ->assertSuccessful();

        Mail::assertSent(OperationalDigestMail::class, function (OperationalDigestMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && isset($mail->sections['overdue_orders']);
        });
    }

    public function test_dry_run_lists_recipients_without_sending(): void
    {
        Mail::fake();

        $this->gestorUser();
        $asset = $this->asset('PAT-DRY-1');

        MaintenanceOrder::create([
            'codigo' => 'OS-DRY-1',
            'asset_id' => $asset->id,
            'status' => MaintenanceOrderStatus::Aberta->value,
            'tipo' => 'corretiva',
            'descricao_problema' => 'Teste dry run',
            'opened_at' => now(),
            'expected_completion_at' => now()->subDay(),
        ]);

        $this->artisan('notifications:operational-alerts', ['--dry-run' => true])
            ->assertSuccessful();

        Mail::assertNothingSent();
    }

    private function gestorUser(): User
    {
        $user = User::factory()->create(['ativo' => true, 'email' => 'gestor-alerts@test.local']);
        $user->assignRole(UserRole::Gestor->value);

        return $user;
    }

    private function asset(string $code): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Alert',
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        $model = EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'M',
            'modelo' => 'X',
            'ativo' => true,
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus(new Asset([
            'codigo_patrimonio' => $code,
            'equipment_model_id' => $model->id,
            'localizacao' => 'Pátio',
        ]), AssetStatus::Disponivel);
    }
}
