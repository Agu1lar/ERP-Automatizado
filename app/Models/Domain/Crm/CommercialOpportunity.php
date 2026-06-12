<?php

namespace App\Models\Domain\Crm;

use App\Enums\OpportunityStage;
use App\Models\Concerns\BelongsToOperatingCompany;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalQuote;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialOpportunity extends Model
{
    use BelongsToOperatingCompany;

    protected $fillable = [
        'operating_company_id',
        'customer_id',
        'rental_quote_id',
        'rental_id',
        'titulo',
        'descricao',
        'stage',
        'valor_estimado',
        'assigned_to',
        'proximo_follow_up_em',
        'ultimo_contato_em',
        'lost_reason',
        'won_at',
        'lost_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'valor_estimado' => 'decimal:2',
            'proximo_follow_up_em' => 'date',
            'ultimo_contato_em' => 'datetime',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
        ];
    }

    public function stageEnum(): OpportunityStage
    {
        return OpportunityStage::from($this->stage);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function rentalQuote(): BelongsTo
    {
        return $this->belongsTo(RentalQuote::class);
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CommercialActivity::class)->latest();
    }
}
