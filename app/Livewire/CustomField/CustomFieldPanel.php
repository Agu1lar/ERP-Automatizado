<?php

namespace App\Livewire\CustomField;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Models\Domain\CustomField\CustomFieldDefinition;
use App\Services\CustomFieldService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class CustomFieldPanel extends Component
{
    use AuthorizesRequests;

    public string $entityType;

    public int $entityId;

    public bool $inline = false;

    /** @var array<string, mixed> */
    public array $customFields = [];

    public bool $showCreateForm = false;

    public string $new_label = '';

    public string $new_type = 'text';

    public bool $new_triggers_warning = false;

    public string $new_warning_message = '';

    public function mount(string $entityType, int $entityId, bool $inline = false): void
    {
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->inline = $inline;
        $this->loadValues();
    }

    public function saveValues(CustomFieldService $service): void
    {
        abort_unless(
            auth()->user()->can('records.edit') || auth()->user()->can('custom_fields.manage'),
            403,
        );

        $entity = CustomFieldEntity::from($this->entityType);
        $service->saveValues($entity, $this->entityId, $this->customFields);
        $this->dispatch('custom-fields-saved');
    }

    public function saveSingleField(string $key, CustomFieldService $service): void
    {
        abort_unless(
            auth()->user()->can('records.edit') || auth()->user()->can('custom_fields.manage'),
            403,
        );

        $entity = CustomFieldEntity::from($this->entityType);
        $service->saveValues($entity, $this->entityId, [
            $key => $this->customFields[$key] ?? null,
        ]);
        $this->dispatch('custom-fields-saved');
    }

    public function createField(CustomFieldService $service): void
    {
        $this->authorize('manage', CustomFieldDefinition::class);

        $data = $this->validate([
            'new_label' => 'required|string|max:255',
            'new_type' => 'required|in:'.implode(',', array_column(CustomFieldType::cases(), 'value')),
            'new_triggers_warning' => 'boolean',
            'new_warning_message' => 'nullable|string|max:500',
        ]);

        $service->createDefinition(
            CustomFieldEntity::from($this->entityType),
            $data['new_label'],
            CustomFieldType::from($data['new_type']),
            $data['new_triggers_warning'],
            $data['new_warning_message'] ?: null,
        );

        $this->resetCreateForm();
        $this->loadValues();
        session()->flash('success', 'Campo criado.');
    }

    public function toggleHidden(int $definitionId, CustomFieldService $service): void
    {
        $this->authorize('hide', CustomFieldDefinition::class);

        $definition = CustomFieldDefinition::findOrFail($definitionId);
        $user = auth()->user();
        $hidden = $service->isHiddenForUser($definition, $user);
        $service->toggleHiddenForUser($definition, $user, ! $hidden);

        if ($hidden) {
            $this->loadValues();
        } else {
            unset($this->customFields[$definition->field_key]);
        }
    }

    public function deactivateField(int $definitionId, CustomFieldService $service): void
    {
        $definition = CustomFieldDefinition::findOrFail($definitionId);
        $this->authorize('delete', $definition);
        $service->deactivateDefinition($definition);
        $this->loadValues();
        session()->flash('success', 'Campo desativado.');
    }

    public function render(): View
    {
        $entity = CustomFieldEntity::from($this->entityType);
        $service = app(CustomFieldService::class);
        $definitions = $service->visibleDefinitions($entity);
        $customWarnings = $service->warningsFor($entity, $this->entityId);

        return view('livewire.custom-field.custom-field-panel', [
            'definitions' => $definitions,
            'typeOptions' => CustomFieldType::cases(),
            'customWarnings' => $customWarnings,
            'canManage' => auth()->user()->can('manage', CustomFieldDefinition::class),
            'canHide' => auth()->user()->can('hide', CustomFieldDefinition::class),
            'canEdit' => auth()->user()->can('records.edit') || auth()->user()->can('custom_fields.manage'),
            'inline' => $this->inline,
        ]);
    }

    private function loadValues(): void
    {
        $this->customFields = app(CustomFieldService::class)->valuesMap(
            CustomFieldEntity::from($this->entityType),
            $this->entityId,
        );
    }

    private function resetCreateForm(): void
    {
        $this->showCreateForm = false;
        $this->new_label = '';
        $this->new_type = CustomFieldType::Text->value;
        $this->new_triggers_warning = false;
        $this->new_warning_message = '';
        $this->resetValidation();
    }
}
