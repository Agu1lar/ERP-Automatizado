<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();

        $this->js('window.Alpine?.store(\'workspace\')?.clear()');

        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    $navOverdueRentals = auth()->user()?->can('viewAny', App\Models\Domain\Rental\Rental::class)
        ? \App\Models\Domain\Rental\Rental::query()->overdueReturns()->count()
        : 0;
    $navFinanceOverdue = auth()->user()?->can('viewAny', App\Models\Domain\Finance\ReceivableTitle::class)
        ? app(\App\Support\DelinquencyReportQuery::class)->overdueTitlesCount()
        : 0;
    $navBillingPending = auth()->user()?->can('viewAny', App\Models\Domain\Finance\ReceivableTitle::class)
        ? app(\App\Support\BillingQueueReportQuery::class)->pendingCount()
        : 0;
    $navFinanceAlerts = $navBillingPending + $navFinanceOverdue;
    $navShowDashboard = auth()->user()?->can('viewAny', App\Models\Domain\Fleet\Asset::class)
        || auth()->user()?->can('dashboard.analytics');
    $navShowComercial = auth()->user()?->can('viewAny', App\Models\Domain\Rental\Rental::class)
        || auth()->user()?->can('viewAny', App\Models\Domain\Customer\Customer::class)
        || auth()->user()?->can('crm.view')
        || auth()->user()?->can('viewAny', App\Models\Domain\Fleet\EquipmentPricing::class);
    $navShowLogistica = auth()->user()?->can('viewAny', App\Models\Domain\Rental\Rental::class)
        || auth()->user()?->can('viewAny', App\Models\Domain\Logistics\Yard::class);
    $navShowEstoque = auth()->user()?->can('viewAny', App\Models\Domain\Fleet\Asset::class)
        || auth()->user()?->can('viewAny', App\Models\Domain\Maintenance\MaintenanceOrder::class)
        || auth()->user()?->can('viewAny', App\Models\Domain\Fleet\EquipmentCategory::class)
        || auth()->user()?->can('viewAny', App\Models\Domain\Fleet\EquipmentModel::class);
    $navShowFinanceiro = auth()->user()?->can('viewAny', App\Models\Domain\Finance\ReceivableTitle::class);
    $navShowConfiguracoes = auth()->user()?->can('viewAny', App\Models\User::class)
        || auth()->user()?->can('viewAny', App\Models\Domain\Organization\OperatingCompany::class)
        || auth()->user()?->can('viewAny', App\Models\Domain\Audit\AuditLog::class);
    $navOverdueOrders = auth()->user()?->can('viewAny', App\Models\Domain\Maintenance\MaintenanceOrder::class)
        ? \App\Models\Domain\Maintenance\MaintenanceOrder::query()->overdue()->count()
        : 0;

    $operatingCompanies = \App\Models\Domain\Organization\OperatingCompany::query()->where('ativo', true)->orderBy('id')->get();
    $activeCompanyId = \App\Support\ActiveOperatingCompany::id();
@endphp

<div
    x-data="{
        sidebarOpen: false,
        hoveredGroup: null,
        mobileGroup: null,
        flyoutTop: 0,
        closeTimer: null,
        isDesktop: window.matchMedia('(min-width: 1024px)').matches,
        init() {
            window.matchMedia('(min-width: 1024px)').addEventListener('change', (e) => {
                this.isDesktop = e.matches;
                this.hoveredGroup = null;
                this.mobileGroup = null;
            });
        },
        showFlyout(id, event) {
            if (! this.isDesktop) {
                return;
            }
            this.cancelClose();
            this.hoveredGroup = id;
            const top = event.currentTarget.getBoundingClientRect().top;
            const maxTop = window.innerHeight - 280;
            this.flyoutTop = Math.max(56, Math.min(top, maxTop));
        },
        scheduleClose() {
            if (! this.isDesktop) {
                return;
            }
            this.closeTimer = setTimeout(() => { this.hoveredGroup = null; }, 120);
        },
        cancelClose() {
            if (this.closeTimer) {
                clearTimeout(this.closeTimer);
                this.closeTimer = null;
            }
        },
        mobileOpen(id, active) {
            return ! this.isDesktop && (this.mobileGroup === id || active);
        },
    }"
    @keydown.window.escape="sidebarOpen = false; hoveredGroup = null"
>
    {{-- Overlay mobile --}}
    <div
        x-show="sidebarOpen"
        x-cloak
        x-transition:enter="transition-opacity ease-linear duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden"
        @click="sidebarOpen = false"
        aria-hidden="true"
    ></div>

    {{-- Sidebar — altura total; scroll só na página, não isolado --}}
    <aside
        class="fixed inset-y-0 left-0 z-50 flex h-dvh w-64 flex-col overflow-y-auto border-r border-gray-200 bg-white transition-transform duration-200 ease-in-out lg:translate-x-0"
        :class="sidebarOpen ? 'translate-x-0 max-lg:pointer-events-auto' : '-translate-x-full lg:translate-x-0 max-lg:pointer-events-none'"
        aria-label="Navegação lateral"
    >
        <div class="shrink-0 border-b border-gray-100 px-3 py-3">
            <div class="flex items-center justify-between gap-2">
                <span class="text-base font-bold leading-tight text-indigo-700">{{ config('app.name') }}</span>
                <button
                    type="button"
                    class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 lg:hidden"
                    @click="sidebarOpen = false"
                    aria-label="Fechar menu"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <p class="mt-2.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Empresa operacional</p>
            <div class="mt-1 flex flex-col gap-1">
                @foreach($operatingCompanies as $oc)
                    <form method="POST" action="{{ route('operating-company.set') }}">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $oc->id }}">
                        <button
                            type="submit"
                            title="@if($oc->formattedCnpj()){{ $oc->formattedCnpj() }}@endif"
                            @class([
                                'w-full rounded-md px-2.5 py-2 text-left text-xs font-semibold transition',
                                'bg-indigo-600 text-white' => $oc->id === $activeCompanyId,
                                'bg-gray-50 text-gray-700 hover:bg-indigo-50 hover:text-indigo-800' => $oc->id !== $activeCompanyId,
                            ])
                        >
                            {{ $oc->nome }}
                        </button>
                    </form>
                @endforeach
            </div>
        </div>

        <div class="flex-1 px-2 py-2">
            @include('livewire.layout.partials.sidebar-menu')
        </div>

        <div class="shrink-0 border-t border-gray-100 px-3 py-2 text-[10px] text-gray-400">
            Ctrl+clique abre nova aba
        </div>
    </aside>

    {{-- Barra superior (área de conteúdo) — z acima da sidebar para cliques funcionarem --}}
    <header class="fixed top-0 right-0 z-[60] flex h-14 w-full items-center gap-2 border-b border-gray-200 bg-white px-3 shadow-sm sm:gap-3 sm:px-4 lg:left-64 lg:w-[calc(100%-16rem)]">
        <button
            type="button"
            class="shrink-0 rounded-md p-2 text-gray-500 hover:bg-gray-100 lg:hidden"
            @click.stop="sidebarOpen = true"
            aria-label="Abrir menu"
        >
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        @auth
            <div class="min-w-0 flex-1">
                <livewire:layout.global-search />
            </div>

            <div class="relative z-[70] shrink-0">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                            <span x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></span>
                            <svg class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate>Perfil</x-dropdown-link>
                        <button type="button" wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>Sair</x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>
        @endauth
    </header>
</div>
