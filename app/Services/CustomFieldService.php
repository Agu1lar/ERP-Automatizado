<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Models\Domain\CustomField\CustomFieldDefinition;
use App\Models\Domain\CustomField\CustomFieldValue;
use App\Models\Domain\CustomField\UserHiddenCustomField;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CustomFieldService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /** @return Collection<int, CustomFieldDefinition> */
    public function visibleDefinitions(CustomFieldEntity $entity, ?User $user = null): Collection
    {
        $user ??= auth()->user();

        $query = CustomFieldDefinition::query()
            ->where('entity_type', $entity->value)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label');

        if ($user) {
            $hiddenIds = UserHiddenCustomField::query()
                ->where('user_id', $user->id)
                ->pluck('custom_field_definition_id');

            if ($hiddenIds->isNotEmpty()) {
                $query->whereNotIn('id', $hiddenIds);
            }
        }

        return $query->get();
    }

    /** @return array<string, string|null> */
    public function valuesMap(CustomFieldEntity $entity, int $entityId, ?User $user = null): array
    {
        $definitions = $this->visibleDefinitions($entity, $user);
        $stored = CustomFieldValue::query()
            ->where('entity_type', $entity->value)
            ->where('entity_id', $entityId)
            ->whereIn('custom_field_definition_id', $definitions->pluck('id'))
            ->get()
            ->keyBy('custom_field_definition_id');

        $map = [];
        foreach ($definitions as $definition) {
            $map[$definition->field_key] = $stored->get($definition->id)?->value;
        }

        return $map;
    }

    /** @param array<string, mixed> $input */
    public function saveValues(CustomFieldEntity $entity, int $entityId, array $input, ?User $user = null): void
    {
        $user ??= auth()->user();
        $definitions = $this->visibleDefinitions($entity, $user)->keyBy('field_key');
        $before = [];
        $after = [];

        foreach ($input as $key => $rawValue) {
            if (! $definitions->has($key)) {
                continue;
            }

            $definition = $definitions->get($key);
            $value = $this->normalizeValue($definition, $rawValue);

            $existing = CustomFieldValue::query()
                ->where('custom_field_definition_id', $definition->id)
                ->where('entity_type', $entity->value)
                ->where('entity_id', $entityId)
                ->first();

            $oldValue = $existing?->value;

            if ($oldValue === $value) {
                continue;
            }

            $before[$definition->label] = $oldValue;
            $after[$definition->label] = $value;

            CustomFieldValue::updateOrCreate(
                [
                    'custom_field_definition_id' => $definition->id,
                    'entity_type' => $entity->value,
                    'entity_id' => $entityId,
                ],
                ['value' => $value],
            );
        }

        if ($before !== [] || $after !== []) {
            $this->auditService->log(
                AuditAction::Updated,
                $this->auditEntityName($entity),
                $entityId,
                ['custom_fields' => $before],
                ['custom_fields' => $after],
                $user,
            );
        }
    }

    public function createDefinition(
        CustomFieldEntity $entity,
        string $label,
        CustomFieldType $type,
        bool $triggersWarning = false,
        ?string $warningMessage = null,
        ?User $user = null,
    ): CustomFieldDefinition {
        $user ??= auth()->user();
        $fieldKey = $this->generateFieldKey($entity, $label);

        $definition = CustomFieldDefinition::create([
            'entity_type' => $entity->value,
            'field_key' => $fieldKey,
            'label' => $label,
            'field_type' => $type->value,
            'sort_order' => (int) CustomFieldDefinition::query()->where('entity_type', $entity->value)->max('sort_order') + 1,
            'triggers_warning' => $triggersWarning,
            'warning_message' => $warningMessage,
            'created_by' => $user?->id,
        ]);

        $this->auditService->log(
            AuditAction::Created,
            'CustomFieldDefinition',
            $definition->id,
            null,
            [
                'entity_type' => $entity->value,
                'entity_label' => $entity->label(),
                'field_key' => $fieldKey,
                'label' => $label,
                'field_type' => $type->value,
                'triggers_warning' => $triggersWarning,
            ],
            $user,
        );

        return $definition;
    }

    public function deactivateDefinition(CustomFieldDefinition $definition, ?User $user = null): void
    {
        $user ??= auth()->user();

        if (! $definition->is_active) {
            return;
        }

        $before = [
            'label' => $definition->label,
            'entity_type' => $definition->entity_type,
            'is_active' => true,
        ];

        $definition->update(['is_active' => false]);

        $this->auditService->log(
            AuditAction::Updated,
            'CustomFieldDefinition',
            $definition->id,
            $before,
            ['is_active' => false],
            $user,
        );
    }

    public function toggleHiddenForUser(CustomFieldDefinition $definition, User $user, bool $hidden): void
    {
        $wasHidden = $this->isHiddenForUser($definition, $user);

        if ($hidden) {
            UserHiddenCustomField::firstOrCreate([
                'user_id' => $user->id,
                'custom_field_definition_id' => $definition->id,
            ]);
        } else {
            UserHiddenCustomField::query()
                ->where('user_id', $user->id)
                ->where('custom_field_definition_id', $definition->id)
                ->delete();
        }

        if ($wasHidden !== $hidden) {
            $this->auditService->log(
                AuditAction::Updated,
                'CustomFieldDefinition',
                $definition->id,
                [
                    'hidden_for_user' => $wasHidden,
                    'user_id' => $user->id,
                    'field_label' => $definition->label,
                ],
                [
                    'hidden_for_user' => $hidden,
                    'user_id' => $user->id,
                    'field_label' => $definition->label,
                ],
                $user,
            );
        }
    }

    public function isHiddenForUser(CustomFieldDefinition $definition, User $user): bool
    {
        return UserHiddenCustomField::query()
            ->where('user_id', $user->id)
            ->where('custom_field_definition_id', $definition->id)
            ->exists();
    }

    /** @return list<array{field: string, message: string}> */
    public function warningsFor(CustomFieldEntity $entity, int $entityId, ?User $user = null): array
    {
        $warnings = [];
        $definitions = $this->visibleDefinitions($entity, $user);
        $values = $this->valuesMap($entity, $entityId, $user);

        foreach ($definitions as $definition) {
            if (! $definition->triggers_warning) {
                continue;
            }

            $value = $values[$definition->field_key] ?? null;

            if ($this->isEmptyValue($definition, $value)) {
                $warnings[] = [
                    'field' => 'custom_'.$definition->field_key,
                    'message' => $definition->warning_message ?: "{$definition->label} não preenchido",
                ];
            }
        }

        return $warnings;
    }

    private function auditEntityName(CustomFieldEntity $entity): string
    {
        return match ($entity) {
            CustomFieldEntity::Asset => 'Asset',
            CustomFieldEntity::Rental => 'Rental',
            CustomFieldEntity::MaintenanceOrder => 'MaintenanceOrder',
        };
    }

    private function generateFieldKey(CustomFieldEntity $entity, string $label): string
    {
        $base = Str::slug($label, '_');
        $key = $base;
        $i = 1;

        while (CustomFieldDefinition::query()->where('entity_type', $entity->value)->where('field_key', $key)->exists()) {
            $key = $base.'_'.$i;
            $i++;
        }

        return $key;
    }

    private function normalizeValue(CustomFieldDefinition $definition, mixed $rawValue): ?string
    {
        if ($rawValue === null || $rawValue === '') {
            return null;
        }

        return match ($definition->typeEnum()) {
            CustomFieldType::Boolean => filter_var($rawValue, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            CustomFieldType::Number => is_numeric($rawValue) ? (string) $rawValue : throw new InvalidArgumentException("Valor inválido para {$definition->label}."),
            default => (string) $rawValue,
        };
    }

    /** @return list<array{label: string, value: string}> */
    public function displayRows(CustomFieldEntity $entity, int $entityId, ?User $user = null): array
    {
        $definitions = $this->visibleDefinitions($entity, $user);
        $values = $this->valuesMap($entity, $entityId, $user);
        $rows = [];

        foreach ($definitions as $definition) {
            $raw = $values[$definition->field_key] ?? null;
            $rows[] = [
                'label' => $definition->label,
                'value' => $this->formatDisplayValue($definition, $raw),
            ];
        }

        return $rows;
    }

    private function formatDisplayValue(CustomFieldDefinition $definition, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($definition->typeEnum()) {
            CustomFieldType::Boolean => $value === '1' ? 'Sim' : 'Não',
            default => $value,
        };
    }

    private function isEmptyValue(CustomFieldDefinition $definition, ?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return $definition->typeEnum() === CustomFieldType::Boolean && $value === '0';
    }
}
