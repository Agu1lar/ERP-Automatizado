/**
 * Checklist do modo pátio: estado local (Alpine) + sessionStorage.
 * Sincroniza com Livewire apenas no envio final — tolera oscilação de rede.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('yardChecklist', (config) => ({
        checklist: {},
        observacoes: '',
        labels: config.labels ?? {},

        init() {
            const keys = Object.keys(this.labels);
            let restored = null;

            try {
                const raw = sessionStorage.getItem(config.storageKey);
                if (raw) {
                    restored = JSON.parse(raw);
                }
            } catch {
                restored = null;
            }

            keys.forEach((key) => {
                this.checklist[key] = restored?.checklist?.[key] ?? false;
            });

            this.observacoes = restored?.observacoes ?? '';
        },

        persist() {
            try {
                sessionStorage.setItem(
                    config.storageKey,
                    JSON.stringify({
                        checklist: this.checklist,
                        observacoes: this.observacoes,
                    }),
                );
            } catch {
                // sessionStorage indisponível (modo privado, quota)
            }
        },

        async submit() {
            await this.$wire.set('checklist', { ...this.checklist });
            await this.$wire.set('observacoes', this.observacoes);
            await this.$wire[config.submitAction]();

            try {
                sessionStorage.removeItem(config.storageKey);
            } catch {
                //
            }
        },
    }));
});
