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
    $navComercialAlerts = $navOverdueRentals + $navFinanceOverdue;
    $navShowComercial = auth()->user()?->can('viewAny', App\Models\Domain\Rental\Rental::class)
        || auth()->user()?->can('dashboard.analytics')
        || auth()->user()?->can('viewAny', App\Models\Domain\Finance\ReceivableTitle::class);
@endphp

<nav x-data="{ open: false, comercialOpen: false, cadastrosOpen: false, adminOpen: false }" class="border-b border-gray-200 bg-white shadow-sm">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-14 items-center justify-between gap-4">
            <div class="flex min-w-0 flex-1 items-center gap-6">
                @php
                    $operatingCompanies = \App\Models\Domain\Organization\OperatingCompany::query()->where('ativo', true)->orderBy('id')->get();
                    $activeCompanyId = \App\Support\ActiveOperatingCompany::id();
                @endphp

                <div class="shrink-0 flex flex-col gap-1">
                    <span class="text-lg font-bold text-indigo-700 leading-tight">{{ config('app.name') }}</span>
                    <div class="flex rounded-lg border border-indigo-200 bg-indigo-50 p-0.5">
                        @foreach($operatingCompanies as $oc)
                            <form method="POST" action="{{ route('operating-company.set') }}" class="inline">
                                @csrf
                                <input type="hidden" name="company_id" value="{{ $oc->id }}">
                                <button
                                    type="submit"
                                    title="@if($oc->formattedCnpj()){{ $oc->formattedCnpj() }}@endif"
                                    @class([
                                        'px-2.5 py-1 text-xs font-semibold rounded-md transition whitespace-nowrap',
                                        'bg-white text-indigo-700 shadow-sm' => $oc->id === $activeCompanyId,
                                        'text-indigo-600 hover:text-indigo-800' => $oc->id !== $activeCompanyId,
                                    ])
                                >
                                    {{ $oc->nome }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>

                <div class="hidden items-center gap-1 md:flex">
                    @can('viewAny', App\Models\Domain\Fleet\Asset::class)
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate data-tab-title="Dashboard">Dashboard</x-nav-link>
                        @can('agent.api')
                            <x-nav-link :href="route('copilot.index')" :active="request()->routeIs('copilot.*')" wire:navigate data-tab-title="Copiloto">Copiloto</x-nav-link>
                        @endcan
                        <x-nav-link :href="route('assets.index')" :active="request()->routeIs('assets.*')" wire:navigate data-tab-title="Patrimônios">Patrimônios</x-nav-link>
                    @endcan
                    @if($navShowComercial)
                        <div class="relative" @click.outside="comercialOpen = false">
                            <button
                                @click="comercialOpen = !comercialOpen"
                                @class([
                                    'inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium transition',
                                    'bg-indigo-50 text-indigo-700' => request()->routeIs('rentals.*', 'reports.*', 'finance.*'),
                                    'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => !request()->routeIs('rentals.*', 'reports.*', 'finance.*'),
                                ])
                            >
                                Comercial
                                @if($navComercialAlerts > 0)
                                    <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">{{ $navComercialAlerts }}</span>
                                @endif
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="comercialOpen" x-cloak class="absolute left-0 z-40 mt-1 w-52 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                                @can('viewAny', App\Models\Domain\Rental\Rental::class)
                                    <a href="{{ route('rentals.index') }}" wire:navigate data-tab-title="Locações" class="flex items-center justify-between px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">
                                        <span>Locações</span>
                                        @if($navOverdueRentals > 0)
                                            <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">{{ $navOverdueRentals }}</span>
                                        @endif
                                    </a>
                                    @can('viewAny', App\Models\Domain\Rental\RentalQuote::class)
                                        <a href="{{ route('quotes.index') }}" wire:navigate data-tab-title="Orçamentos" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Orçamentos</a>
                                    @endcan
                                @endcan
                                @can('dashboard.analytics')
                                    <a href="{{ route('reports.commercial') }}" wire:navigate data-tab-title="Relatório comercial" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Relatório comercial</a>
                                    <a href="{{ route('reports.financial-analysis') }}" wire:navigate data-tab-title="Análise financeira" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Análise financeira</a>
                                @endcan
                                @can('viewAny', App\Models\Domain\Finance\ReceivableTitle::class)
                                    <div class="my-1 border-t border-gray-100"></div>
                                    <p class="px-4 py-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Financeiro</p>
                                    <a href="{{ route('finance.receivables') }}" wire:navigate data-tab-title="Títulos" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Títulos a receber</a>
                                    <a href="{{ route('finance.billing-queue') }}" wire:navigate data-tab-title="A faturar" class="flex items-center justify-between px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">
                                        <span>A faturar</span>
                                        @if($navBillingPending > 0)
                                            <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-500 px-1.5 text-[10px] font-bold text-white">{{ $navBillingPending }}</span>
                                        @endif
                                    </a>
                                    <a href="{{ route('finance.delinquency') }}" wire:navigate data-tab-title="Inadimplência" class="flex items-center justify-between px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">
                                        <span>Inadimplência</span>
                                        @if($navFinanceOverdue > 0)
                                            <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">{{ $navFinanceOverdue }}</span>
                                        @endif
                                    </a>
                                    <a href="{{ route('finance.cashflow') }}" wire:navigate data-tab-title="Fluxo de caixa" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Fluxo de caixa</a>
                                @endcan
                            </div>
                        </div>
                    @endif
                    @can('viewAny', App\Models\Domain\Maintenance\MaintenanceOrder::class)
                        @php $navOverdueOrders = \App\Models\Domain\Maintenance\MaintenanceOrder::query()->overdue()->count(); @endphp
                        <x-nav-link :href="route('maintenance.index')" :active="request()->routeIs('maintenance.*')" wire:navigate data-tab-title="Manutenção">
                            Manutenção
                            @if($navOverdueOrders > 0)
                                <span class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-500 px-1.5 text-[10px] font-bold text-white">{{ $navOverdueOrders }}</span>
                            @endif
                        </x-nav-link>
                    @endcan

                    @if(auth()->user()->can('viewAny', App\Models\Domain\Fleet\EquipmentCategory::class)
                        || auth()->user()->can('viewAny', App\Models\Domain\Fleet\EquipmentModel::class)
                        || auth()->user()->can('viewAny', App\Models\Domain\Fleet\EquipmentPricing::class)
                        || auth()->user()->can('viewAny', App\Models\Domain\Customer\Customer::class)
                        || auth()->user()->can('viewAny', App\Models\Domain\Person\Person::class)
                        || auth()->user()->can('viewAny', App\Models\Domain\Person\Company::class))
                        <div class="relative" @click.outside="cadastrosOpen = false">
                            <button
                                @click="cadastrosOpen = !cadastrosOpen"
                                @class([
                                    'inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium transition',
                                    'bg-indigo-50 text-indigo-700' => request()->routeIs('fleet.*', 'customers.*', 'people.*', 'companies.*'),
                                    'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => !request()->routeIs('fleet.*', 'customers.*', 'people.*', 'companies.*'),
                                ])
                            >
                                Cadastros
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="cadastrosOpen" x-cloak class="absolute left-0 z-40 mt-1 w-44 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                                @can('viewAny', App\Models\Domain\Fleet\EquipmentCategory::class)
                                    <a href="{{ route('fleet.categories.index') }}" wire:navigate class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Categorias</a>
                                @endcan
                                @can('viewAny', App\Models\Domain\Fleet\EquipmentModel::class)
                                    <a href="{{ route('fleet.models.index') }}" wire:navigate class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Modelos</a>
                                @endcan
                                @can('viewAny', App\Models\Domain\Fleet\EquipmentPricing::class)
                                    <a href="{{ route('fleet.pricing.index') }}" wire:navigate class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Tabela de preços</a>
                                @endcan
                                @can('viewAny', App\Models\Domain\Customer\Customer::class)
                                    <a href="{{ route('customers.index') }}" wire:navigate class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Clientes</a>
                                @endcan
                                @can('viewAny', App\Models\Domain\Person\Person::class)
                                    <a href="{{ route('people.index') }}" wire:navigate class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Pessoas</a>
                                @endcan
                                @can('viewAny', App\Models\Domain\Person\Company::class)
                                    <a href="{{ route('companies.index') }}" wire:navigate class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Empresas</a>
                                @endcan
                            </div>
                        </div>
                    @endif

                    @if(auth()->user()->can('viewAny', App\Models\User::class) || auth()->user()->can('viewAny', App\Models\Domain\Organization\OperatingCompany::class) || auth()->user()->can('viewAny', App\Models\Domain\Audit\AuditLog::class))
                        <div class="relative" @click.outside="adminOpen = false">
                            <button
                                @click="adminOpen = !adminOpen"
                                @class([
                                    'inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium transition',
                                    'bg-indigo-50 text-indigo-700' => request()->routeIs('admin.*'),
                                    'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => !request()->routeIs('admin.*'),
                                ])
                            >
                                Admin
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="adminOpen" x-cloak class="absolute left-0 z-40 mt-1 w-44 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                                @can('viewAny', App\Models\User::class)
                                    <a href="{{ route('admin.users.index') }}" wire:navigate class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Usuários</a>
                                @endcan
                                @can('viewAny', App\Models\Domain\Organization\OperatingCompany::class)
                                    <a href="{{ route('admin.companies.index') }}" wire:navigate class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Empresas (CNPJ)</a>
                                @endcan
                                @can('viewAny', App\Models\Domain\Audit\AuditLog::class)
                                    <a href="{{ route('admin.audit.index') }}" wire:navigate class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Auditoria</a>
                                    <a href="{{ route('admin.agent-logs.index') }}" wire:navigate class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Copiloto (logs)</a>
                                @endcan
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="hidden shrink-0 items-center gap-3 sm:flex">
                @auth
                    <livewire:layout.global-search />
                @endauth

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                            <span x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></span>
                            <svg class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate>Perfil</x-dropdown-link>
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>Sair</x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <button @click="open = !open" class="rounded-md p-2 text-gray-500 hover:bg-gray-100 md:hidden">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path :class="{'hidden': open, 'inline-flex': !open}" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    <path :class="{'hidden': !open, 'inline-flex': open}" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': !open}" class="border-t border-gray-100 md:hidden">
        @php
            $mobileOperatingCompanies = \App\Models\Domain\Organization\OperatingCompany::query()->where('ativo', true)->orderBy('id')->get();
            $mobileActiveCompanyId = \App\Support\ActiveOperatingCompany::id();
        @endphp
        <div class="px-4 py-3 border-b border-gray-100">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">{{ config('app.name') }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach($mobileOperatingCompanies as $oc)
                    <form method="POST" action="{{ route('operating-company.set') }}">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $oc->id }}">
                        <button
                            type="submit"
                            @class([
                                'rounded-full px-3 py-1.5 text-xs font-semibold border',
                                'bg-indigo-600 text-white border-indigo-600' => $oc->id === $mobileActiveCompanyId,
                                'bg-white text-indigo-700 border-indigo-200' => $oc->id !== $mobileActiveCompanyId,
                            ])
                        >
                            {{ $oc->nome }}
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
        @auth
            <div class="px-4 py-3">
                <livewire:layout.global-search />
            </div>
        @endauth
        <div class="space-y-1 px-2 pb-3">
            <x-responsive-nav-link :href="route('dashboard')" wire:navigate>Dashboard</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('assets.index')" wire:navigate>Patrimônios</x-responsive-nav-link>
            @if($navShowComercial)
                <p class="px-3 pt-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Comercial</p>
                @can('viewAny', App\Models\Domain\Rental\Rental::class)
                    <x-responsive-nav-link :href="route('rentals.index')" wire:navigate>Locações</x-responsive-nav-link>
                @endcan
                @can('dashboard.analytics')
                    <x-responsive-nav-link :href="route('reports.commercial')" wire:navigate>Relatório comercial</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('reports.financial-analysis')" wire:navigate>Análise financeira</x-responsive-nav-link>
                @endcan
                @can('viewAny', App\Models\Domain\Finance\ReceivableTitle::class)
                    <x-responsive-nav-link :href="route('finance.receivables')" wire:navigate>Títulos a receber</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('finance.billing-queue')" wire:navigate>A faturar</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('finance.delinquency')" wire:navigate>Inadimplência</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('finance.cashflow')" wire:navigate>Fluxo de caixa</x-responsive-nav-link>
                @endcan
            @endif
            @can('viewAny', App\Models\Domain\Maintenance\MaintenanceOrder::class)
                <x-responsive-nav-link :href="route('maintenance.index')" wire:navigate>Manutenção</x-responsive-nav-link>
            @endcan
            <x-responsive-nav-link :href="route('fleet.models.index')" wire:navigate>Modelos</x-responsive-nav-link>
            @can('viewAny', App\Models\Domain\Fleet\EquipmentPricing::class)
                <x-responsive-nav-link :href="route('fleet.pricing.index')" wire:navigate>Tabela de preços</x-responsive-nav-link>
            @endcan
            <x-responsive-nav-link :href="route('customers.index')" wire:navigate>Clientes</x-responsive-nav-link>
            @can('viewAny', App\Models\Domain\Person\Person::class)
                <x-responsive-nav-link :href="route('people.index')" wire:navigate>Pessoas</x-responsive-nav-link>
            @endcan
            @can('viewAny', App\Models\Domain\Person\Company::class)
                <x-responsive-nav-link :href="route('companies.index')" wire:navigate>Empresas</x-responsive-nav-link>
            @endcan
        </div>
    </div>
</nav>
