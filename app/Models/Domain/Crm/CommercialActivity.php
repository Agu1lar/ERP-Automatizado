<?php

namespace App\Models\Domain\Crm;

use App\Enums\CommercialActivityType;
use App\Models\Domain\Customer\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialActivity extends Model
{
    protected $fillable = [
        'customer_id',
        'commercial_opportunity_id',
        'tipo',
        'descricao',
        'user_id',
        'proximo_follow_up_em',
    ];

    protected function casts(): array
    {
        return [
            'proximo_follow_up_em' => 'date',
        ];
    }

    public function typeEnum(): CommercialActivityType
    {
        return CommercialActivityType::from($this->tipo);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CommercialOpportunity::class, 'commercial_opportunity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
