<?php

namespace App\Providers;

use App\Models\Domain\Audit\AuditLog;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Fleet\EquipmentPricing;
use App\Models\Domain\CustomField\CustomFieldDefinition;
use App\Models\Domain\Logistics\Yard;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Maintenance\PartCatalogItem;
use App\Models\Domain\Maintenance\PreventiveMaintenanceRule;
use App\Models\Domain\Person\Company;
use App\Models\Domain\Person\Person;
use App\Models\Domain\Organization\OperatingCompany;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalQuote;
use App\Agent\AgentCommandRegistry;
use App\Models\User;
use App\Observers\AssetObserver;
use App\Observers\CompanyObserver;
use App\Observers\CustomerObserver;
use App\Observers\EquipmentModelObserver;
use App\Observers\PersonObserver;
use App\Observers\UserObserver;
use App\Policies\AssetPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\EquipmentCategoryPolicy;
use App\Policies\EquipmentModelPolicy;
use App\Policies\EquipmentPricingPolicy;
use App\Policies\ReceivableTitlePolicy;
use App\Policies\CustomFieldDefinitionPolicy;
use App\Policies\MaintenanceOrderPolicy;
use App\Policies\OperatingCompanyPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\PartCatalogItemPolicy;
use App\Policies\PersonPolicy;
use App\Policies\PreventiveMaintenanceRulePolicy;
use App\Policies\RentalPolicy;
use App\Policies\RentalQuotePolicy;
use App\Policies\UserPolicy;
use App\Policies\YardPolicy;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AgentCommandRegistry::class, function () {
            $registry = new AgentCommandRegistry;
            $registry->registerMany(config('agent.commands', []));

            return $registry;
        });
    }

    public function boot(): void
    {
        Gate::policy(Asset::class, AssetPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Person::class, PersonPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(EquipmentCategory::class, EquipmentCategoryPolicy::class);
        Gate::policy(EquipmentModel::class, EquipmentModelPolicy::class);
        Gate::policy(EquipmentPricing::class, EquipmentPricingPolicy::class);
        Gate::policy(ReceivableTitle::class, ReceivableTitlePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Rental::class, RentalPolicy::class);
        Gate::policy(RentalQuote::class, RentalQuotePolicy::class);
        Gate::policy(MaintenanceOrder::class, MaintenanceOrderPolicy::class);
        Gate::policy(CustomFieldDefinition::class, CustomFieldDefinitionPolicy::class);
        Gate::policy(PartCatalogItem::class, PartCatalogItemPolicy::class);
        Gate::policy(PreventiveMaintenanceRule::class, PreventiveMaintenanceRulePolicy::class);
        Gate::policy(OperatingCompany::class, OperatingCompanyPolicy::class);
        Gate::policy(Yard::class, YardPolicy::class);

        Asset::observe(AssetObserver::class);
        Customer::observe(CustomerObserver::class);
        Person::observe(PersonObserver::class);
        Company::observe(CompanyObserver::class);
        EquipmentModel::observe(EquipmentModelObserver::class);
        User::observe(UserObserver::class);

        Event::listen(Login::class, function (Login $event) {
            $event->user->update(['ultimo_login' => now()]);
        });
    }
}
