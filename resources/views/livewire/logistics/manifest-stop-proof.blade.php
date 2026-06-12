<div class="min-h-screen bg-gray-100 p-4">
    <div class="max-w-lg mx-auto space-y-4">
        <div>
            <a href="{{ route('logistics.manifest.show', $manifest) }}" wire:navigate class="text-sm text-indigo-600">← Romaneio {{ $manifest->codigo }}</a>
            <h1 class="text-xl font-bold text-gray-900 mt-2">Comprovante de {{ $stop->tipoEnum()->label() }}</h1>
            <p class="text-sm text-gray-600">{{ $stop->rental->codigo }} — {{ $stop->rental->customer->nome }}</p>
            <p class="text-xs text-gray-500">{{ $stop->endereco ?? $stop->rental->local_obra ?? 'Sem endereço' }}</p>
        </div>

        <form wire:submit="submit" class="bg-white rounded-xl shadow p-4 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Nome de quem assina *</label>
                <input wire:model="receptor_nome" type="text" class="mt-1 w-full rounded-lg border-gray-300 text-base" placeholder="Nome do receptor" />
                @error('receptor_nome') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Assinatura</label>
                <div class="border border-gray-300 rounded-lg bg-white overflow-hidden">
                    <canvas id="signature-pad" class="w-full touch-none" height="160" style="display:block;width:100%;"></canvas>
                </div>
                <div class="flex gap-2 mt-2">
                    <button type="button" id="signature-clear" class="text-xs text-gray-600 underline">Limpar assinatura</button>
                </div>
                <input type="hidden" wire:model="assinatura_imagem" id="signature-data" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Foto (opcional)</label>
                <input wire:model="foto" type="file" accept="image/*" capture="environment" class="mt-1 w-full text-sm" />
                @error('foto') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Observações</label>
                <textarea wire:model="observacoes" rows="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></textarea>
            </div>

            <button type="submit" class="w-full py-3 rounded-lg bg-indigo-600 text-white font-semibold text-base hover:bg-indigo-700">
                Salvar comprovante
            </button>
        </form>
    </div>
</div>

@script
<script>
    const canvas = document.getElementById('signature-pad');
    const hidden = document.getElementById('signature-data');
    const clearBtn = document.getElementById('signature-clear');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    let drawing = false;

    const resize = () => {
        const ratio = window.devicePixelRatio || 1;
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * ratio;
        canvas.height = 160 * ratio;
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#111827';
    };
    resize();
    window.addEventListener('resize', resize);

    const pos = (e) => {
        const rect = canvas.getBoundingClientRect();
        const touch = e.touches ? e.touches[0] : e;
        return { x: touch.clientX - rect.left, y: touch.clientY - rect.top };
    };

    const start = (e) => { drawing = true; const p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); };
    const move = (e) => {
        if (!drawing) return;
        const p = pos(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        e.preventDefault();
    };
    const end = () => {
        if (!drawing) return;
        drawing = false;
        hidden.value = canvas.toDataURL('image/png');
        hidden.dispatchEvent(new Event('input', { bubbles: true }));
    };

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    canvas.addEventListener('mouseup', end);
    canvas.addEventListener('mouseleave', end);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', end);

    clearBtn?.addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hidden.value = '';
        hidden.dispatchEvent(new Event('input', { bubbles: true }));
    });
</script>
@endscript
