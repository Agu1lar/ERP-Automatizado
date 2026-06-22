<nav
    class="space-y-0.5"
    aria-label="Menu principal"
    @click="if (!isDesktop && $event.target.closest('a')) sidebarOpen = false"
>
    @if($navShowDashboard)
        <x-sidebar-nav-group id="dashboard" label="Dashboard" :active="request()->routeIs('dashboard', 'reports.*')">
            @can('viewAny', App\Models\Domain\Fleet\Asset::class)
                <x-sidebar-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate data-tab-title="Dashboard">Painel principal</x-sidebar-nav-link>
            @endcan
            @can('dashboard.analytics')
                <x-sidebar-nav-link :href="route('reports.commercial')" :active="request()->routeIs('reports.commercial')" wire:navigate data-tab-title="Relatório comercial">Relatório comercial</x-sidebar-nav-link>
                <x-sidebar-nav-link :href="route('reports.financial-analysis')" :active="request()->routeIs('reports.financial-analysis')" wire:navigate data-tab-title="Análise financeira">Análise financeira</x-sidebar-nav-link>
                <x-sidebar-nav-link :href="route('reports.fleet')" :active="request()->routeIs('reports.fleet')" wire:navigate data-tab-title="Indicadores de frota">Indicadores de frota</x-sidebar-nav-link>
                <x-sidebar-nav-link :href="route('reports.maintenance-cost')" :active="request()->routeIs('reports.maintenance-cost')" wire:navigate data-tab-title="Custo OS vs faturamento">Custo OS vs faturamento</x-sidebar-nav-link>
            @endcan
        </x-sidebar-nav-group>
    @endif

    @if($navShowComercial)
        <x-sidebar-nav-group
            id="comercial"
            label="Comercial"
            :active="request()->routeIs('rentals.*', 'quotes.*', 'crm.*', 'customers.*', 'people.*', 'companies.*', 'fleet.pricing.*')"
            :badge="$navOverdueRentals > 0 ? $navOverdueRentals : null"
        >
            @can('viewAny', App\Models\Domain\Rental\Rental::class)
                <x-sidebar-nav-link :href="route('rentals.index')" :active="request()->routeIs('rentals.*')" wire:navigate data-tab-title="Locações" :badge="$navOverdueRentals > 0 ? $navOverdueRentals : null">Locações</x-sidebar-nav-link>
                @can('viewAny', App\Models\Domain\Rental\RentalQuote::class)
                    <x-sidebar-nav-link :href="route('quotes.index')" :active="request()->routeIs('quotes.*')" wire:navigate data-tab-title="Orçamentos">Orçamentos</x-sidebar-nav-link>
                @endcan
            @endcan
            @can('crm.view')
                <x-sidebar-nav-link :href="route('crm.pipeline')" :active="request()->routeIs('crm.pipeline')" wire:navigate data-tab-title="Pipeline CRM">Pipeline CRM</x-sidebar-nav-link>
                <x-sidebar-nav-link :href="route('crm.inactive')" :active="request()->routeIs('crm.inactive')" wire:navigate data-tab-title="Clientes inativos">Campanha inativos</x-sidebar-nav-link>
                <x-sidebar-nav-link :href="route('crm.messages')" :active="request()->routeIs('crm.messages')" wire:navigate data-tab-title="Mensagens CRM">Mensagens</x-sidebar-nav-link>
            @endcan
            @can('viewAny', App\Models\Domain\Customer\Customer::class)
                <x-sidebar-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.*')" wire:navigate data-tab-title="Clientes">Clientes</x-sidebar-nav-link>
            @endcan
            @can('viewAny', App\Models\Domain\Person\Person::class)
                <x-sidebar-nav-link :href="route('people.index')" :active="request()->routeIs('people.*')" wire:navigate data-tab-title="Pessoas">Pessoas</x-sidebar-nav-link>
            @endcan
            @can('viewAny', App\Models\Domain\Person\Company::class)
                <x-sidebar-nav-link :href="route('companies.index')" :active="request()->routeIs('companies.*')" wire:navigate data-tab-title="Empresas">Empresas</x-sidebar-nav-link>
            @endcan
            @can('viewAny', App\Models\Domain\Fleet\EquipmentPricing::class)
                <x-sidebar-nav-link :href="route('fleet.pricing.index')" :active="request()->routeIs('fleet.pricing.*')" wire:navigate data-tab-title="Tabela de preços">Tabela de preços</x-sidebar-nav-link>
            @endcan
        </x-sidebar-nav-group>
    @endif

    @if($navShowLogistica)
        <x-sidebar-nav-group id="logistica" label="Logística" :active="request()->routeIs('logistics.*')">
            @can('viewAny', App\Models\Domain\Rental\Rental::class)
                <x-sidebar-nav-link :href="route('logistics.daily')" :active="request()->routeIs('logistics.daily')" wire:navigate data-tab-title="Lista do dia">Lista do dia</x-sidebar-nav-link>
                <x-sidebar-nav-link :href="route('logistics.works-map')" :active="request()->routeIs('logistics.works-map')" wire:navigate data-tab-title="Mapa de obras">Mapa de obras</x-sidebar-nav-link>
                <x-sidebar-nav-link :href="route('logistics.fleet.index')" :active="request()->routeIs('logistics.fleet.*')" wire:navigate data-tab-title="Frota de entrega">Motoristas e veículos</x-sidebar-nav-link>
            @endcan
            @can('viewAny', App\Models\Domain\Logistics\Yard::class)
                <x-sidebar-nav-link :href="route('logistics.yards.index')" :active="request()->routeIs('logistics.yards.*')" wire:navigate data-tab-title="Pátios">Pátios</x-sidebar-nav-link>
            @endcan
        </x-sidebar-nav-group>
    @endif

    @if($navShowEstoque)
        <x-sidebar-nav-group
            id="estoque"
            label="Estoque"
            :active="request()->routeIs('assets.*', 'maintenance.*', 'fleet.categories.*', 'fleet.models.*')"
            :badge="$navOverdueOrders > 0 ? $navOverdueOrders : null"
            badge-color="bg-amber-500"
        >
            @can('viewAny', App\Models\Domain\Fleet\Asset::class)
                <x-sidebar-nav-link :href="route('assets.index')" :active="request()->routeIs('assets.*')" wire:navigate data-tab-title="Patrimônios">Patrimônios</x-sidebar-nav-link>
            @endcan
            @can('viewAny', App\Models\Domain\Maintenance\MaintenanceOrder::class)
                <x-sidebar-nav-link :href="route('maintenance.index')" :active="request()->routeIs(['maintenance.index', 'maintenance.show'])" wire:navigate data-tab-title="Manutenção" :badge="$navOverdueOrders > 0 ? $navOverdueOrders : null" badge-color="bg-amber-500">Ordens de serviço</x-sidebar-nav-link>
                <x-sidebar-nav-link :href="route('maintenance.parts.index')" :active="request()->routeIs('maintenance.parts.*')" wire:navigate data-tab-title="Catálogo de peças">Catálogo de peças</x-sidebar-nav-link>
                <x-sidebar-nav-link :href="route('maintenance.purchase-orders.index')" :active="request()->routeIs('maintenance.purchase-orders.*')" wire:navigate data-tab-title="Pedidos de compra">Pedidos de compra</x-sidebar-nav-link>
                <x-sidebar-nav-link :href="route('maintenance.preventive.index')" :active="request()->routeIs('maintenance.preventive.*')" wire:navigate data-tab-title="Preventiva">Preventiva</x-sidebar-nav-link>
            @endcan
            @can('viewAny', App\Models\Domain\Fleet\EquipmentCategory::class)
                <x-sidebar-nav-link :href="route('fleet.categories.index')" :active="request()->routeIs('fleet.categories.*')" wire:navigate data-tab-title="Categorias">Categorias</x-sidebar-nav-link>
            @endcan
            @can('viewAny', App\Models\Domain\Fleet\EquipmentModel::class)
                <x-sidebar-nav-link :href="route('fleet.models.index')" :active="request()->routeIs('fleet.models.*')" wire:navigate data-tab-title="Modelos">Modelos</x-sidebar-nav-link>
            @endcan
        </x-sidebar-nav-group>
    @endif

    @if($navShowFinanceiro)
        <x-sidebar-nav-group
            id="financeiro"
            label="Financeiro"
            :active="request()->routeIs('finance.*')"
            :badge="$navFinanceAlerts > 0 ? $navFinanceAlerts : null"
        >
            <x-sidebar-nav-link :href="route('finance.receivables')" :active="request()->routeIs('finance.receivables')" wire:navigate data-tab-title="Títulos">Títulos a receber</x-sidebar-nav-link>
            <x-sidebar-nav-link :href="route('finance.payables')" :active="request()->routeIs('finance.payables')" wire:navigate data-tab-title="A pagar">Contas a pagar</x-sidebar-nav-link>
            <x-sidebar-nav-link :href="route('finance.billing-queue')" :active="request()->routeIs('finance.billing-queue')" wire:navigate data-tab-title="A faturar" :badge="$navBillingPending > 0 ? $navBillingPending : null" badge-color="bg-amber-500">A faturar</x-sidebar-nav-link>
            <x-sidebar-nav-link :href="route('finance.delinquency')" :active="request()->routeIs('finance.delinquency')" wire:navigate data-tab-title="Inadimplência" :badge="$navFinanceOverdue > 0 ? $navFinanceOverdue : null">Inadimplência</x-sidebar-nav-link>
            <x-sidebar-nav-link :href="route('finance.cashflow')" :active="request()->routeIs('finance.cashflow')" wire:navigate data-tab-title="Fluxo de caixa">Fluxo de caixa</x-sidebar-nav-link>
            <x-sidebar-nav-link :href="route('finance.fiscal')" :active="request()->routeIs('finance.fiscal')" wire:navigate data-tab-title="Fiscal ERP">Fiscal (ERP)</x-sidebar-nav-link>
        </x-sidebar-nav-group>
    @endif

    @if($navShowConfiguracoes)
        <x-sidebar-nav-group id="config" label="Configurações" :active="request()->routeIs('admin.*')">
            @can('viewAny', App\Models\User::class)
                <x-sidebar-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" wire:navigate data-tab-title="Usuários">Usuários</x-sidebar-nav-link>
            @endcan
            @can('viewAny', App\Models\Domain\Organization\OperatingCompany::class)
                <x-sidebar-nav-link :href="route('admin.companies.index')" :active="request()->routeIs('admin.companies.*')" wire:navigate data-tab-title="Empresas operacionais">Empresas (CNPJ)</x-sidebar-nav-link>
            @endcan
            @can('viewAny', App\Models\Domain\Audit\AuditLog::class)
                <x-sidebar-nav-link :href="route('admin.audit.index')" :active="request()->routeIs('admin.audit.*')" wire:navigate data-tab-title="Auditoria">Auditoria</x-sidebar-nav-link>
                <x-sidebar-nav-link :href="route('admin.agent-logs.index')" :active="request()->routeIs('admin.agent-logs.*')" wire:navigate data-tab-title="Copiloto logs">Copiloto (logs)</x-sidebar-nav-link>
                <x-sidebar-nav-link :href="route('admin.agent-metrics.index')" :active="request()->routeIs('admin.agent-metrics.*')" wire:navigate data-tab-title="Copiloto métricas">Copiloto (métricas)</x-sidebar-nav-link>
            @endcan
        </x-sidebar-nav-group>
    @endif
</nav>
