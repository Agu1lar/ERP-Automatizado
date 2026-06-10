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

<nav x-data="{ open: false, cadastrosOpen: false, adminOpen: false }" class="border-b border-gray-200 bg-white shadow-sm">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-14 items-center justify-between gap-4">
            <div class="flex min-w-0 flex-1 items-center gap-6">
                <a href="{{ route('dashboard') }}" wire:navigate class="shrink-0 text-lg font-bold text-indigo-700">
                    Linha Leve
                </a>

                <div class="hidden items-center gap-1 md:flex">
                    @can('viewAny', App\Models\Domain\Fleet\Asset::class)
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate data-tab-title="Dashboard">Dashboard</x-nav-link>
                        <x-nav-link :href="route('assets.index')" :active="request()->routeIs('assets.*')" wire:navigate data-tab-title="Patrimônios">Patrimônios</x-nav-link>
                    @endcan
                    @can('viewAny', App\Models\Domain\Rental\Rental::class)
                        @php $navOverdueRentals = \App\Models\Domain\Rental\Rental::query()->overdueReturns()->count(); @endphp
                        <x-nav-link :href="route('rentals.index')" :active="request()->routeIs('rentals.*')" wire:navigate data-tab-title="Locações">
                            Locações
                            @if($navOverdueRentals > 0)
                                <span class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">{{ $navOverdueRentals }}</span>
                            @endif
                        </x-nav-link>
                    @endcan
                    @can('viewAny', App\Models\Domain\Maintenance\MaintenanceOrder::class)
                        @php $navOverdueOrders = \App\Models\Domain\Maintenance\MaintenanceOrder::query()->overdue()->count(); @endphp
                        <x-nav-link :href="route('maintenance.index')" :active="request()->routeIs('maintenance.*')" wire:navigate data-tab-title="Manutenção">
                            Manutenção
                            @if($navOverdueOrders > 0)
                                <span class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-500 px-1.5 text-[10px] font-bold text-white">{{ $navOverdueOrders }}</span>
                            @endif
                        </x-nav-link>
                    @endcan
                    @can('dashboard.analytics')
                        <x-nav-link :href="route('reports.commercial')" :active="request()->routeIs('reports.*')" wire:navigate data-tab-title="Relatórios">Relatórios</x-nav-link>
                    @endcan

                    @if(auth()->user()->can('viewAny', App\Models\Domain\Fleet\EquipmentCategory::class)
                        || auth()->user()->can('viewAny', App\Models\Domain\Fleet\EquipmentModel::class)
                        || auth()->user()->can('viewAny', App\Models\Domain\Fleet\EquipmentPricing::class)
                        || auth()->user()->can('viewAny', App\Models\Domain\Customer\Customer::class))
                        <div class="relative" @click.outside="cadastrosOpen = false">
                            <button
                                @click="cadastrosOpen = !cadastrosOpen"
                                @class([
                                    'inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium transition',
                                    'bg-indigo-50 text-indigo-700' => request()->routeIs('fleet.*', 'customers.*'),
                                    'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => !request()->routeIs('fleet.*', 'customers.*'),
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
                            </div>
                        </div>
                    @endif

                    @if(auth()->user()->can('viewAny', App\Models\User::class) || auth()->user()->can('viewAny', App\Models\Domain\Audit\AuditLog::class))
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
                                @can('viewAny', App\Models\Domain\Audit\AuditLog::class)
                                    <a href="{{ route('admin.audit.index') }}" wire:navigate class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Auditoria</a>
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
        @auth
            <div class="px-4 py-3">
                <livewire:layout.global-search />
            </div>
        @endauth
        <div class="space-y-1 px-2 pb-3">
            <x-responsive-nav-link :href="route('dashboard')" wire:navigate>Dashboard</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('assets.index')" wire:navigate>Patrimônios</x-responsive-nav-link>
            @can('viewAny', App\Models\Domain\Rental\Rental::class)
                <x-responsive-nav-link :href="route('rentals.index')" wire:navigate>Locações</x-responsive-nav-link>
            @endcan
            @can('viewAny', App\Models\Domain\Maintenance\MaintenanceOrder::class)
                <x-responsive-nav-link :href="route('maintenance.index')" wire:navigate>Manutenção</x-responsive-nav-link>
            @endcan
            <x-responsive-nav-link :href="route('fleet.models.index')" wire:navigate>Modelos</x-responsive-nav-link>
            @can('viewAny', App\Models\Domain\Fleet\EquipmentPricing::class)
                <x-responsive-nav-link :href="route('fleet.pricing.index')" wire:navigate>Tabela de preços</x-responsive-nav-link>
            @endcan
            <x-responsive-nav-link :href="route('customers.index')" wire:navigate>Clientes</x-responsive-nav-link>
        </div>
    </div>
</nav>
