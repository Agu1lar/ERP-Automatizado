<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_authenticated_layout_includes_workspace_tab_bar(): void
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Comercial->value);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Abas do sistema"', false)
            ->assertSee('Ctrl+clique abre nova aba');
    }

    public function test_guest_layout_does_not_include_workspace_tab_bar(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Abas do sistema');
    }
}
