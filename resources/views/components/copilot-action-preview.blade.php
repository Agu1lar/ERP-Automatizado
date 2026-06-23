@props(['preview'])

@if(is_array($preview))
    @if(!empty($preview['summary']))
        <p class="mt-2 text-xs text-amber-950 leading-relaxed">{{ $preview['summary'] }}</p>
    @endif

    @if(!empty($preview['parameters']))
        <div class="mt-2 rounded-lg border border-amber-200 bg-white/90 overflow-hidden">
            <p class="px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-amber-800 bg-amber-100/80">Dados informados</p>
            <dl class="divide-y divide-amber-100">
                @foreach($preview['parameters'] as $parameter)
                    <div class="grid grid-cols-[minmax(0,42%)_minmax(0,1fr)] gap-2 px-2.5 py-1.5 text-xs">
                        <dt class="text-amber-800">{{ $parameter['label'] ?? $parameter['key'] ?? 'Campo' }}</dt>
                        <dd class="text-amber-950 font-medium text-right break-words">{{ $parameter['value'] ?? '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif

    @if(!empty($preview['effects']))
        <div class="mt-2 rounded-lg border border-amber-200 bg-white/90 overflow-hidden">
            <p class="px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-amber-800 bg-amber-100/80">O que será feito</p>
            <ul class="divide-y divide-amber-100">
                @foreach($preview['effects'] as $effect)
                    <li class="px-2.5 py-1.5 text-xs text-amber-950">
                        <span class="font-medium text-amber-800">{{ $effect['label'] ?? 'Alteração' }}:</span>
                        @if(!empty($effect['before']))
                            <span class="line-through text-amber-700/80">{{ $effect['before'] }}</span>
                            <span class="mx-1 text-amber-500">→</span>
                        @endif
                        <span class="font-medium">{{ $effect['after'] ?? '—' }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!empty($preview['targets']))
        <div class="mt-2 flex flex-wrap gap-1.5">
            @foreach($preview['targets'] as $target)
                <span class="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-900">
                    {{ $target['label'] ?? $target['codigo'] ?? 'Registro' }}
                </span>
            @endforeach
        </div>
    @endif

    @if(!empty($preview['warnings']))
        <ul class="mt-2 space-y-1 text-xs text-red-800 list-disc list-inside">
            @foreach($preview['warnings'] as $warning)
                <li>{{ $warning }}</li>
            @endforeach
        </ul>
    @endif
@endif
