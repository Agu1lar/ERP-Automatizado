<?php

namespace App\Models\Domain\CustomField;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomFieldDefinition extends Model
{
    protected $fillable = [
        'entity_type',
        'field_key',
        'label',
        'field_type',
        'sort_order',
        'triggers_warning',
        'warning_message',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'triggers_warning' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    public function hiddenByUsers(): HasMany
    {
        return $this->hasMany(UserHiddenCustomField::class);
    }

    public function entityEnum(): CustomFieldEntity
    {
        return CustomFieldEntity::from($this->entity_type);
    }

    public function typeEnum(): CustomFieldType
    {
        return CustomFieldType::from($this->field_type);
    }
}
