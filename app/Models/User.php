<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'ativo', 'ultimo_login'])]
#[Hidden(['password', 'remember_token'])]
/**
 * Credencial de login dos operadores internos (pátio, comercial, financeiro).
 *
 * Entidade separada de {@see \App\Models\Domain\Customer\Customer} (cliente da locação)
 * e de {@see \App\Models\Domain\Person\Person} (contato CRM). Permissões via Spatie Roles.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ativo' => 'boolean',
            'ultimo_login' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->ativo;
    }
}
