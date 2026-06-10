const STORAGE_KEY = 'linha_leve_workspace_tabs';

const ROUTE_LABELS = {
    '/dashboard': 'Dashboard',
    '/patrimonios': 'Patrimônios',
    '/locacoes': 'Locações',
    '/manutencao': 'Manutenção',
    '/manutencao/pecas': 'Catálogo de peças',
    '/manutencao/preventiva': 'Preventiva',
    '/relatorios/comercial': 'Relatório comercial',
    '/clientes': 'Clientes',
    '/frota/categorias': 'Categorias',
    '/frota/modelos': 'Modelos',
    '/admin/usuarios': 'Usuários',
    '/admin/auditoria': 'Auditoria',
    '/profile': 'Perfil',
};

function normalizeUrl(url) {
    try {
        const parsed = new URL(url, window.location.origin);

        return parsed.pathname + parsed.search;
    } catch {
        return url;
    }
}

function titleFromUrl(url) {
    const path = normalizeUrl(url).split('?')[0];

    if (ROUTE_LABELS[path]) {
        return ROUTE_LABELS[path];
    }

    if (/^\/patrimonios\/\d+/.test(path)) {
        return 'Patrimônio';
    }

    if (/^\/locacoes\/\d+/.test(path)) {
        return 'Locação';
    }

    if (/^\/manutencao\/\d+/.test(path)) {
        return 'Ordem de serviço';
    }

    const segment = path.split('/').filter(Boolean).pop();

    return segment ? segment.charAt(0).toUpperCase() + segment.slice(1) : 'Página';
}

function titleFromPage() {
    const heading = document.querySelector(
        'main h2.text-2xl, main h2.text-xl, main h2.font-bold, main h2.font-semibold, main h2'
    );

    const text = heading?.textContent?.trim();

    return text ? text.slice(0, 48) : null;
}

function navigateTo(url) {
    const destination = normalizeUrl(url);

    if (window.Alpine?.navigate) {
        window.Alpine.navigate(destination);

        return;
    }

    window.location.href = destination;
}

function isNavigableLink(link) {
    if (!link || link.tagName !== 'A') {
        return false;
    }

    const href = link.getAttribute('href');

    if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) {
        return false;
    }

    if (link.target === '_blank' || link.hasAttribute('download')) {
        return false;
    }

    if (link.hasAttribute('wire:navigate') || link.hasAttribute('wire-navigate')) {
        return true;
    }

    try {
        const parsed = new URL(href, window.location.origin);

        return parsed.origin === window.location.origin;
    } catch {
        return href.startsWith('/');
    }
}

document.addEventListener('alpine:init', () => {
    window.Alpine.store('workspace', {
        tabs: [],
        activeId: null,
        _openingNewTab: false,

        init() {
            this.load();

            const currentUrl = normalizeUrl(window.location.pathname + window.location.search);

            if (this.tabs.length === 0) {
                this.addTab(currentUrl, titleFromPage() || titleFromUrl(currentUrl), true, false);
            } else if (!this.tabs.some((tab) => tab.id === this.activeId)) {
                this.activeId = this.tabs[0]?.id ?? null;
            }

            const active = this.active();
            if (active) {
                active.url = currentUrl;
                active.title = titleFromPage() || titleFromUrl(currentUrl);
            }

            this.save();

            document.addEventListener('livewire:navigated', () => {
                const url = normalizeUrl(window.location.pathname + window.location.search);

                if (!this._openingNewTab) {
                    this.updateActiveTab(url);
                }

                this._openingNewTab = false;
                this.save();
            });
        },

        load() {
            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                if (!raw) {
                    return;
                }

                const data = JSON.parse(raw);
                this.tabs = Array.isArray(data.tabs) ? data.tabs : [];
                this.activeId = data.activeId ?? null;
            } catch {
                this.tabs = [];
                this.activeId = null;
            }
        },

        save() {
            localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify({ tabs: this.tabs, activeId: this.activeId })
            );
        },

        active() {
            return this.tabs.find((tab) => tab.id === this.activeId) ?? null;
        },

        addTab(url, title, makeActive = true, shouldNavigate = true) {
            const normalized = normalizeUrl(url);
            const existing = this.tabs.find((tab) => tab.url === normalized);

            if (existing) {
                if (makeActive) {
                    this.activeId = existing.id;
                    this.save();

                    if (shouldNavigate) {
                        this.navigate(normalized);
                    }
                }

                return existing;
            }

            const tab = {
                id: crypto.randomUUID(),
                url: normalized,
                title: title || titleFromUrl(normalized),
            };

            this.tabs.push(tab);

            if (makeActive) {
                this.activeId = tab.id;
            }

            this.save();

            if (makeActive && shouldNavigate) {
                this.navigate(normalized);
            }

            return tab;
        },

        openInNewTab(url, title = null) {
            this._openingNewTab = true;
            this.addTab(url, title, true, true);
        },

        openBlankTab() {
            this.openInNewTab('/dashboard', 'Dashboard');
        },

        switchTab(id) {
            const tab = this.tabs.find((item) => item.id === id);

            if (!tab || tab.id === this.activeId) {
                return;
            }

            this.activeId = id;
            this._openingNewTab = false;
            this.save();
            this.navigate(tab.url);
        },

        closeTab(id) {
            if (this.tabs.length <= 1) {
                return;
            }

            const index = this.tabs.findIndex((tab) => tab.id === id);

            if (index === -1) {
                return;
            }

            const wasActive = this.activeId === id;
            this.tabs.splice(index, 1);

            if (wasActive) {
                const next = this.tabs[Math.max(0, index - 1)];
                this.activeId = next.id;
                this.save();
                this.navigate(next.url);
            } else {
                this.save();
            }
        },

        updateActiveTab(url) {
            const tab = this.active();

            if (!tab) {
                this.addTab(url, titleFromPage() || titleFromUrl(url), true, false);

                return;
            }

            tab.url = normalizeUrl(url);
            tab.title = titleFromPage() || titleFromUrl(tab.url);
        },

        navigate(url) {
            navigateTo(url);
        },

        clear() {
            this.tabs = [];
            this.activeId = null;
            localStorage.removeItem(STORAGE_KEY);
        },
    });

    window.Alpine.store('workspace').init();

    const openLinkInNewTab = (event, link) => {
        event.preventDefault();
        event.stopImmediatePropagation();

        const title =
            link.getAttribute('data-tab-title') ||
            link.getAttribute('title') ||
            link.textContent.trim() ||
            null;

        window.Alpine.store('workspace').openInNewTab(link.getAttribute('href'), title);
    };

    document.addEventListener(
        'click',
        (event) => {
            const link = event.target.closest('a[href]');

            if (!isNavigableLink(link)) {
                return;
            }

            if (!event.ctrlKey && !event.metaKey && !event.shiftKey) {
                return;
            }

            openLinkInNewTab(event, link);
        },
        true
    );

    document.addEventListener('auxclick', (event) => {
        if (event.button !== 1) {
            return;
        }

        const link = event.target.closest('a[href]');

        if (!isNavigableLink(link)) {
            return;
        }

        openLinkInNewTab(event, link);
    });
});
