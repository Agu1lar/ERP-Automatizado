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
@endphp

<nav x-data="{ open: false, dashboardOpen: false, comercialOpen: false, logisticaOpen: false, estoqueOpen: false, financeiroOpen: false, configOpen: false }" class="border-b border-gray-200 bg-white shadow-sm">
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
                    @if($navShowDashboard)
                        <div class="relative" @click.outside="dashboardOpen = false">
                            <button
                                @click="dashboardOpen = !dashboardOpen"
                                @class([
                                    'inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium transition',
                                    'bg-indigo-50 text-indigo-700' => request()->routeIs('dashboard', 'reports.*'),
                                    'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => !request()->routeIs('dashboard', 'reports.*'),
                                ])
                            >
                                Dashboard
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="dashboardOpen" x-cloak class="absolute left-0 z-40 mt-1 w-52 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                                @can('viewAny', App\Models\Domain\Fleet\Asset::class)
                                    <a href="{{ route('dashboard') }}" wire:navigate data-tab-title="Dashboard" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Painel principal</a>
                                @endcan
                                @can('dashboard.analytics')
                                    <p class="px-4 py-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Relatórios</p>
                                    <a href="{{ route('reports.commercial') }}" wire:navigate data-tab-title="Relatório comercial" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Relatório comercial</a>
                                    <a href="{{ route('reports.financial-analysis') }}" wire:navigate data-tab-title="Análise financeira" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Análise financeira</a>
                                    <a href="{{ route('reports.fleet') }}" wire:navigate data-tab-title="Indicadores de frota" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Indicadores de frota</a>
                                    <a href="{{ route('reports.maintenance-cost') }}" wire:navigate data-tab-title="Custo OS vs faturamento" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Custo OS vs faturamento</a>
                                @endcan
                            </div>
                        </div>
                    @endif

                    @if($navShowComercial)
                        <div class="relative" @click.outside="comercialOpen = false">
                            <button
                                @click="comercialOpen = !comercialOpen"
                                @class([
                                    'inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium transition',
                                    'bg-indigo-50 text-indigo-700' => request()->routeIs('rentals.*', 'quotes.*', 'crm.*', 'customers.*', 'people.*', 'companies.*', 'fleet.pricing.*'),
                                    'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => !request()->routeIs('rentals.*', 'quotes.*', 'crm.*', 'customers.*', 'people.*', 'companies.*', 'fleet.pricing.*'),
                                ])
                            >
                                Comercial
                                @if($navOverdueRentals > 0)
                                    <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">{{ $navOverdueRentals }}</span>
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
                                @can('crm.view')
                                    <a href="{{ route('crm.pipeline') }}" wire:navigate data-tab-title="Pipeline CRM" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Pipeline CRM</a>
                                    <a href="{{ route('crm.inactive') }}" wire:navigate data-tab-title="Clientes inativos" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Campanha inativos</a>
                                    <a href="{{ route('crm.messages') }}" wire:navigate data-tab-title="Mensagens CRM" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Mensagens</a>
                                @endcan
                                @can('viewAny', App\Models\Domain\Customer\Customer::class)
                                    <a href="{{ route('customers.index') }}" wire:navigate data-tab-title="Clientes" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Clientes</a>
                                @endcan
                                @can('viewAny', App\Models\Domain\Person\Person::class)
                                    <a href="{{ route('people.index') }}" wire:navigate data-tab-title="Pessoas" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Pessoas</a>
                                @endcan
                                @can('viewAny', App\Models\Domain\Person\Company::class)
                                    <a href="{{ route('companies.index') }}" wire:navigate data-tab-title="Empresas" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Empresas</a>
                                @endcan
                                @can('viewAny', App\Models\Domain\Fleet\EquipmentPricing::class)
                                    <a href="{{ route('fleet.pricing.index') }}" wire:navigate data-tab-title="Tabela de preços" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Tabela de preços</a>
                                @endcan
                            </div>
                        </div>
                    @endif

                    @if($navShowLogistica)
                        <div class="relative" @click.outside="logisticaOpen = false">
                            <button
                                @click="logisticaOpen = !logisticaOpen"
                                @class([
                                    'inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium transition',
                                    'bg-indigo-50 text-indigo-700' => request()->routeIs('logistics.*'),
                                    'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => !request()->routeIs('logistics.*'),
                                ])
                            >
                                Logística
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="logisticaOpen" x-cloak class="absolute left-0 z-40 mt-1 w-52 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                                @can('viewAny', App\Models\Domain\Rental\Rental::class)
                                    <a href="{{ route('logistics.daily') }}" wire:navigate data-tab-title="Lista do dia" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Lista do dia</a>
                                    <a href="{{ route('logistics.works-map') }}" wire:navigate data-tab-title="Mapa de obras" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Mapa de obras</a>
                                    <a href="{{ route('logistics.fleet.index') }}" wire:navigate data-tab-title="Frota de entrega" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Motoristas e veículos</a>
                                @endcan
                                @can('viewAny', App\Models\Domain\Logistics\Yard::class)
                                    <a href="{{ route('logistics.yards.index') }}" wire:navigate data-tab-title="Pátios" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Pátios</a>
                                @endcan
                            </div>
                        </div>
                    @endif

                    @if($navShowEstoque)
                        <div class="relative" @click.outside="estoqueOpen = false">
                            <button
                                @click="estoqueOpen = !estoqueOpen"
                                @class([
                                    'inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium transition',
                                    'bg-indigo-50 text-indigo-700' => request()->routeIs('assets.*', 'maintenance.*', 'fleet.categories.*', 'fleet.models.*'),
                                    'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => !request()->routeIs('assets.*', 'maintenance.*', 'fleet.categories.*', 'fleet.models.*'),
                                ])
                            >
                                Estoque
                                @if($navOverdueOrders > 0)
                                    <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-500 px-1.5 text-[10px] font-bold text-white">{{ $navOverdueOrders }}</span>
                                @endif
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="estoqueOpen" x-cloak class="absolute left-0 z-40 mt-1 w-52 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                                @can('viewAny', App\Models\Domain\Fleet\Asset::class)
                                    <a href="{{ route('assets.index') }}" wire:navigate data-tab-title="Patrimônios" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Patrimônios</a>
                                @endcan
                                @can('viewAny', App\Models\Domain\Maintenance\MaintenanceOrder::class)
                                    <a href="{{ route('maintenance.index') }}" wire:navigate data-tab-title="Manutenção" class="flex items-center justify-between px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">
                                        <span>Ordens de serviço</span>
                                        @if($navOverdueOrders > 0)
                                            <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-500 px-1.5 text-[10px] font-bold text-white">{{ $navOverdueOrders }}</span>
                                        @endif
                                    </a>
                                    <a href="{{ route('maintenance.parts.index') }}" wire:navigate data-tab-title="Catálogo de peças" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Catálogo de peças</a>
                                    <a href="{{ route('maintenance.purchase-orders.index') }}" wire:navigate data-tab-title="Pedidos de compra" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Pedidos de compra</a>
                                    <a href="{{ route('maintenance.preventive.index') }}" wire:navigate data-tab-title="Preventiva" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Preventiva</a>
                                @endcan
                                @can('viewAny', App\Models\Domain\Fleet\EquipmentCategory::class)
                                    <a href="{{ route('fleet.categories.index') }}" wire:navigate data-tab-title="Categorias" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Categorias</a>
                                @endcan
                                @can('viewAny', App\Models\Domain\Fleet\EquipmentModel::class)
                                    <a href="{{ route('fleet.models.index') }}" wire:navigate data-tab-title="Modelos" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Modelos</a>
                                @endcan
                            </div>
                        </div>
                    @endif

                    @if($navShowFinanceiro)
                        <div class="relative" @click.outside="financeiroOpen = false">
                            <button
                                @click="financeiroOpen = !financeiroOpen"
                                @class([
                                    'inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium transition',
                                    'bg-indigo-50 text-indigo-700' => request()->routeIs('finance.*'),
                                    'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => !request()->routeIs('finance.*'),
                                ])
                            >
                                Financeiro
                                @if($navFinanceAlerts > 0)
                                    <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">{{ $navFinanceAlerts }}</span>
                                @endif
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="financeiroOpen" x-cloak class="absolute left-0 z-40 mt-1 w-52 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                                <a href="{{ route('finance.receivables') }}" wire:navigate data-tab-title="Títulos" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Títulos a receber</a>
                                <a href="{{ route('finance.payables') }}" wire:navigate data-tab-title="A pagar" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Contas a pagar</a>
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
                                <a href="{{ route('finance.fiscal') }}" wire:navigate data-tab-title="Fiscal ERP" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Fiscal (ERP)</a>
                            </div>
                        </div>
                    @endif

                    @if($navShowConfiguracoes)
                        <div class="relative" @click.outside="configOpen = false">
                            <button
                                @click="configOpen = !configOpen"
                                @class([
                                    'inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium transition',
                                    'bg-indigo-50 text-indigo-700' => request()->routeIs('admin.*'),
                                    'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => !request()->routeIs('admin.*'),
                                ])
                            >
                                Configurações
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="configOpen" x-cloak class="absolute left-0 z-40 mt-1 w-48 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                                @can('viewAny', App\Models\User::class)
                                    <a href="{{ route('admin.users.index') }}" wire:navigate data-tab-title="Usuários" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Usuários</a>
                                @endcan
                                @can('viewAny', App\Models\Domain\Organization\OperatingCompany::class)
                                    <a href="{{ route('admin.companies.index') }}" wire:navigate data-tab-title="Empresas operacionais" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Empresas (CNPJ)</a>
                                @endcan
                                @can('viewAny', App\Models\Domain\Audit\AuditLog::class)
                                    <a href="{{ route('admin.audit.index') }}" wire:navigate data-tab-title="Auditoria" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Auditoria</a>
                                    <a href="{{ route('admin.agent-logs.index') }}" wire:navigate data-tab-title="Copiloto logs" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Copiloto (logs)</a>
                                    <a href="{{ route('admin.agent-metrics.index') }}" wire:navigate data-tab-title="Copiloto métricas" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Copiloto (métricas)</a>
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
            @if($navShowDashboard)
                <p class="px-3 pt-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Dashboard</p>
                @can('viewAny', App\Models\Domain\Fleet\Asset::class)
                    <x-responsive-nav-link :href="route('dashboard')" wire:navigate>Painel principal</x-responsive-nav-link>
                @endcan
                @can('dashboard.analytics')
                    <x-responsive-nav-link :href="route('reports.commercial')" wire:navigate>Relatório comercial</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('reports.financial-analysis')" wire:navigate>Análise financeira</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('reports.fleet')" wire:navigate>Indicadores de frota</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('reports.maintenance-cost')" wire:navigate>Custo OS vs faturamento</x-responsive-nav-link>
                @endcan
            @endif

            @if($navShowComercial)
                <p class="px-3 pt-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Comercial</p>
                @can('viewAny', App\Models\Domain\Rental\Rental::class)
                    <x-responsive-nav-link :href="route('rentals.index')" wire:navigate>Locações</x-responsive-nav-link>
                    @can('viewAny', App\Models\Domain\Rental\RentalQuote::class)
                        <x-responsive-nav-link :href="route('quotes.index')" wire:navigate>Orçamentos</x-responsive-nav-link>
                    @endcan
                @endcan
                @can('crm.view')
                    <x-responsive-nav-link :href="route('crm.pipeline')" wire:navigate>Pipeline CRM</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('crm.inactive')" wire:navigate>Campanha inativos</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('crm.messages')" wire:navigate>Mensagens</x-responsive-nav-link>
                @endcan
                @can('viewAny', App\Models\Domain\Customer\Customer::class)
                    <x-responsive-nav-link :href="route('customers.index')" wire:navigate>Clientes</x-responsive-nav-link>
                @endcan
                @can('viewAny', App\Models\Domain\Person\Person::class)
                    <x-responsive-nav-link :href="route('people.index')" wire:navigate>Pessoas</x-responsive-nav-link>
                @endcan
                @can('viewAny', App\Models\Domain\Person\Company::class)
                    <x-responsive-nav-link :href="route('companies.index')" wire:navigate>Empresas</x-responsive-nav-link>
                @endcan
                @can('viewAny', App\Models\Domain\Fleet\EquipmentPricing::class)
                    <x-responsive-nav-link :href="route('fleet.pricing.index')" wire:navigate>Tabela de preços</x-responsive-nav-link>
                @endcan
            @endif

            @if($navShowLogistica)
                <p class="px-3 pt-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Logística</p>
                @can('viewAny', App\Models\Domain\Rental\Rental::class)
                    <x-responsive-nav-link :href="route('logistics.daily')" wire:navigate>Lista do dia</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('logistics.works-map')" wire:navigate>Mapa de obras</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('logistics.fleet.index')" wire:navigate>Motoristas e veículos</x-responsive-nav-link>
                @endcan
                @can('viewAny', App\Models\Domain\Logistics\Yard::class)
                    <x-responsive-nav-link :href="route('logistics.yards.index')" wire:navigate>Pátios</x-responsive-nav-link>
                @endcan
            @endif

            @if($navShowEstoque)
                <p class="px-3 pt-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Estoque</p>
                @can('viewAny', App\Models\Domain\Fleet\Asset::class)
                    <x-responsive-nav-link :href="route('assets.index')" wire:navigate>Patrimônios</x-responsive-nav-link>
                @endcan
                @can('viewAny', App\Models\Domain\Maintenance\MaintenanceOrder::class)
                    <x-responsive-nav-link :href="route('maintenance.index')" wire:navigate>Ordens de serviço</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('maintenance.parts.index')" wire:navigate>Catálogo de peças</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('maintenance.purchase-orders.index')" wire:navigate>Pedidos de compra</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('maintenance.preventive.index')" wire:navigate>Preventiva</x-responsive-nav-link>
                @endcan
                @can('viewAny', App\Models\Domain\Fleet\EquipmentCategory::class)
                    <x-responsive-nav-link :href="route('fleet.categories.index')" wire:navigate>Categorias</x-responsive-nav-link>
                @endcan
                @can('viewAny', App\Models\Domain\Fleet\EquipmentModel::class)
                    <x-responsive-nav-link :href="route('fleet.models.index')" wire:navigate>Modelos</x-responsive-nav-link>
                @endcan
            @endif

            @if($navShowFinanceiro)
                <p class="px-3 pt-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Financeiro</p>
                <x-responsive-nav-link :href="route('finance.receivables')" wire:navigate>Títulos a receber</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('finance.payables')" wire:navigate>Contas a pagar</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('finance.billing-queue')" wire:navigate>A faturar</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('finance.delinquency')" wire:navigate>Inadimplência</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('finance.cashflow')" wire:navigate>Fluxo de caixa</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('finance.fiscal')" wire:navigate>Fiscal (ERP)</x-responsive-nav-link>
            @endif

            @if($navShowConfiguracoes)
                <p class="px-3 pt-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Configurações</p>
                @can('viewAny', App\Models\User::class)
                    <x-responsive-nav-link :href="route('admin.users.index')" wire:navigate>Usuários</x-responsive-nav-link>
                @endcan
                @can('viewAny', App\Models\Domain\Organization\OperatingCompany::class)
                    <x-responsive-nav-link :href="route('admin.companies.index')" wire:navigate>Empresas (CNPJ)</x-responsive-nav-link>
                @endcan
                @can('viewAny', App\Models\Domain\Audit\AuditLog::class)
                    <x-responsive-nav-link :href="route('admin.audit.index')" wire:navigate>Auditoria</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.agent-logs.index')" wire:navigate>Copiloto (logs)</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.agent-metrics.index')" wire:navigate>Copiloto (métricas)</x-responsive-nav-link>
                @endcan
            @endif
        </div>
    </div>
</nav>
