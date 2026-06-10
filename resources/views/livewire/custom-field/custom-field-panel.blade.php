<div class="border-t border-gray-100 pt-4 mt-2">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <h4 class="text-sm font-semibold text-gray-800">Campos personalizados</h4>
        @if($canManage)
            <button type="button" wire:click="$set('showCreateForm', true)" class="text-xs text-indigo-600 hover:underline">+ Novo campo</button>
        @endif
    </div>

    @if(count($customWarnings) > 0 && ! $inline)
        <ul class="mb-3 text-xs text-amber-700 space-y-0.5">
            @foreach($customWarnings as $warning)
                <li class="flex items-center gap-1">
                    <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full bg-amber-500 text-[9px] font-bold text-white">!</span>
                    {{ $warning['message'] }}
                </li>
            @endforeach
        </ul>
    @endif

    @if($showCreateForm && $canManage)
        <form wire:submit="createField" class="mb-4 p-3 bg-gray-50 rounded-lg space-y-2 text-sm">
            <input wire:model="new_label" type="text" placeholder="Nome do campo *" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
            @error('new_label') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            <select wire:model="new_type" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                @foreach($typeOptions as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 text-xs">
                <input wire:model="new_triggers_warning" type="checkbox" class="rounded border-gray-300" />
                Alertar (!) se vazio
            </label>
            @if($new_triggers_warning)
                <input wire:model="new_warning_message" type="text" placeholder="Mensagem do alerta (opcional)" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
            @endif
            <div class="flex gap-2">
                <x-btn-primary type="submit" class="text-xs">Criar</x-btn-primary>
                <x-btn-secondary type="button" wire:click="$set('showCreateForm', false)" class="text-xs">Cancelar</x-btn-secondary>
            </div>
        </form>
    @endif

    @if($definitions->isEmpty())
        <p class="text-xs text-gray-500">Nenhum campo personalizado.</p>
    @elseif($canEdit && $inline)
        <div class="grid md:grid-cols-2 gap-x-4 gap-y-1">
            @foreach($definitions as $definition)
                @php
                    $val = $customFields[$definition->field_key] ?? null;
                    $fieldKey = 'custom_'.$definition->field_key;
                    $hasWarning = \App\Support\FichaCompleteness::hasFieldWarning($customWarnings, $fieldKey);
                    $warningMessage = collect($customWarnings)->firstWhere('field', $fieldKey)['message'] ?? '';
                    $saveAction = "saveSingleField('{$definition->field_key}')";
                    $display = match($definition->typeEnum()->value) {
                        'boolean' => ($val === '1' || $val === true) ? 'Sim' : (($val === '0' || $val === false) ? 'Não' : null),
                        'date' => filled($val) ? \Carbon\Carbon::parse($val)->format('d/m/Y') : null,
                        default => filled($val) ? $val : null,
                    };
                    $type = match($definition->typeEnum()->value) {
                        'textarea' => 'textarea',
                        'number' => 'number',
                        'date' => 'date',
                        'boolean' => 'boolean',
                        default => 'text',
                    };
                @endphp
                <div class="{{ in_array($type, ['textarea', 'boolean']) ? 'md:col-span-2' : '' }} relative">
                    @if($type === 'boolean')
                        <div class="rounded-lg border border-transparent px-3 py-2.5 hover:border-indigo-100 hover:bg-indigo-50/50">
                            <div class="mb-1 flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-gray-500">
                                {{ $definition->label }}
                                @if($hasWarning)
                                    <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full bg-amber-500 text-[9px] font-bold text-white" title="{{ $warningMessage }}">!</span>
                                @endif
                            </div>
                            <label class="flex items-center gap-2 text-sm text-gray-900 cursor-pointer">
                                <input wire:model="customFields.{{ $definition->field_key }}" wire:change="{{ $saveAction }}" type="checkbox" value="1" class="rounded border-gray-300 text-indigo-600" />
                                <span>{{ $display === 'Sim' ? 'Sim' : 'Não / clique para marcar' }}</span>
                            </label>
                        </div>
                    @else
                        <x-inline-field
                            :label="$definition->label"
                            :display="$display"
                            :type="$type"
                            :editable="true"
                            :save="$saveAction"
                            :warning="$hasWarning"
                            :warning-message="$warningMessage"
                            wire:model="customFields.{{ $definition->field_key }}"
                        />
                    @endif
                    <div class="absolute right-0 top-2 flex gap-1">
                        @if($canHide)
                            <button type="button" wire:click="toggleHidden({{ $definition->id }})" class="text-xs text-gray-400 hover:text-gray-600" title="Ocultar para mim">👁</button>
                        @endif
                        @can('delete', $definition)
                            <button type="button" wire:click="deactivateField({{ $definition->id }})" wire:confirm="Desativar este campo?" class="text-xs text-red-400 hover:text-red-600" title="Desativar">×</button>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @elseif($canEdit)
        <form wire:submit="saveValues" class="space-y-3">
            @foreach($definitions as $definition)
                <div class="flex items-start gap-2">
                    <div class="flex-1">
                        <x-sheet-field-label :label="$definition->label" :field="'custom_'.$definition->field_key" :warnings="$customWarnings" />
                        @if($definition->typeEnum()->value === 'textarea')
                            <textarea wire:model="customFields.{{ $definition->field_key }}" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                        @elseif($definition->typeEnum()->value === 'boolean')
                            <label class="mt-1 flex items-center gap-2 text-sm">
                                <input wire:model="customFields.{{ $definition->field_key }}" type="checkbox" class="rounded border-gray-300" value="1" />
                                <span>Sim</span>
                            </label>
                        @elseif($definition->typeEnum()->value === 'date')
                            <input wire:model="customFields.{{ $definition->field_key }}" type="date" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        @elseif($definition->typeEnum()->value === 'number')
                            <input wire:model="customFields.{{ $definition->field_key }}" type="number" step="any" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        @else
                            <input wire:model="customFields.{{ $definition->field_key }}" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        @endif
                    </div>
                    <div class="flex flex-col gap-1 pt-6">
                        @if($canHide)
                            <button type="button" wire:click="toggleHidden({{ $definition->id }})" class="text-xs text-gray-400 hover:text-gray-600" title="Ocultar para mim">👁</button>
                        @endif
                        @can('delete', $definition)
                            <button type="button" wire:click="deactivateField({{ $definition->id }})" wire:confirm="Desativar este campo?" class="text-xs text-red-400 hover:text-red-600" title="Desativar (admin)">×</button>
                        @endcan
                    </div>
                </div>
            @endforeach
            <x-btn-secondary type="submit" class="text-xs">Salvar campos personalizados</x-btn-secondary>
        </form>
    @else
        <dl class="space-y-2 text-sm">
            @foreach($definitions as $definition)
                @php $val = $customFields[$definition->field_key] ?? null; @endphp
                <div>
                    <dt class="text-gray-500 text-xs">{{ $definition->label }}</dt>
                    <dd class="text-gray-800">
                        @if($definition->typeEnum()->value === 'boolean')
                            {{ ($val === '1' || $val === true) ? 'Sim' : (($val === '0' || $val === false) ? 'Não' : '—') }}
                        @else
                            {{ filled($val) ? $val : '—' }}
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    @endif
</div>
