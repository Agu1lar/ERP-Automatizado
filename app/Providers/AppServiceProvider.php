<?php

namespace App\Providers;

use App\Models\Domain\Audit\AuditLog;
use App\Models\Domain\Crm\CommercialOpportunity;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Finance\PayableTitle;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Fiscal\FiscalDocument;
use App\Models\Domain\Fleet\EquipmentPricing;
use App\Models\Domain\CustomField\CustomFieldDefinition;
use App\Models\Domain\Logistics\DeliveryDriver;
use App\Models\Domain\Logistics\DeliveryManifest;
use App\Models\Domain\Logistics\DeliveryVehicle;
use App\Models\Domain\Logistics\Yard;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Maintenance\PartCatalogItem;
use App\Models\Domain\Maintenance\PartPurchaseOrder;
use App\Models\Domain\Maintenance\PreventiveMaintenanceRule;
use App\Models\Domain\Person\Company;
use App\Models\Domain\Person\Person;
use App\Models\Domain\Organization\OperatingCompany;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use App\Models\Domain\Rental\RentalQuote;
use App\Agent\AgentCommandRegistry;
use App\Models\User;
use App\Services\AgentTaskService;
use App\Observers\AssetObserver;
use App\Observers\CompanyObserver;
use App\Observers\CustomerObserver;
use App\Observers\EquipmentModelObserver;
use App\Observers\PersonObserver;
use App\Observers\UserObserver;
use App\Policies\AssetPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\DeliveryDriverPolicy;
use App\Policies\DeliveryManifestPolicy;
use App\Policies\DeliveryVehiclePolicy;
use App\Policies\CommercialOpportunityPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\EquipmentCategoryPolicy;
use App\Policies\EquipmentModelPolicy;
use App\Policies\EquipmentPricingPolicy;
use App\Policies\PayableTitlePolicy;
use App\Policies\ReceivableTitlePolicy;
use App\Policies\FiscalDocumentPolicy;
use App\Policies\CustomFieldDefinitionPolicy;
use App\Policies\MaintenanceOrderPolicy;
use App\Policies\OperatingCompanyPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\PartCatalogItemPolicy;
use App\Policies\PartPurchaseOrderPolicy;
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
        Gate::policy(CommercialOpportunity::class, CommercialOpportunityPolicy::class);
        Gate::policy(Person::class, PersonPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(EquipmentCategory::class, EquipmentCategoryPolicy::class);
        Gate::policy(EquipmentModel::class, EquipmentModelPolicy::class);
        Gate::policy(EquipmentPricing::class, EquipmentPricingPolicy::class);
        Gate::policy(ReceivableTitle::class, ReceivableTitlePolicy::class);
        Gate::policy(PayableTitle::class, PayableTitlePolicy::class);
        Gate::policy(FiscalDocument::class, FiscalDocumentPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Rental::class, RentalPolicy::class);
        Gate::policy(RentalQuote::class, RentalQuotePolicy::class);
        Gate::policy(MaintenanceOrder::class, MaintenanceOrderPolicy::class);
        Gate::policy(CustomFieldDefinition::class, CustomFieldDefinitionPolicy::class);
        Gate::policy(PartCatalogItem::class, PartCatalogItemPolicy::class);
        Gate::policy(PartPurchaseOrder::class, PartPurchaseOrderPolicy::class);
        Gate::policy(PreventiveMaintenanceRule::class, PreventiveMaintenanceRulePolicy::class);
        Gate::policy(OperatingCompany::class, OperatingCompanyPolicy::class);
        Gate::policy(Yard::class, YardPolicy::class);
        Gate::policy(DeliveryManifest::class, DeliveryManifestPolicy::class);
        Gate::policy(DeliveryDriver::class, DeliveryDriverPolicy::class);
        Gate::policy(DeliveryVehicle::class, DeliveryVehiclePolicy::class);

        Asset::observe(AssetObserver::class);
        Customer::observe(CustomerObserver::class);
        Person::observe(PersonObserver::class);
        Company::observe(CompanyObserver::class);
        EquipmentModel::observe(EquipmentModelObserver::class);
        User::observe(UserObserver::class);

        Event::listen(Login::class, function (Login $event) {
            $event->user->update(['ultimo_login' => now()]);
        });

        $invalidateAgentTasks = function (string $type, object $model): void {
            app(AgentTaskService::class)->notifyResourceChanged($type, (int) $model->getKey());
        };

        Event::listen('eloquent.updated: '.Rental::class, fn (Rental $r) => $invalidateAgentTasks('rental', $r));
        Event::listen('eloquent.updated: '.Asset::class, fn (Asset $a) => $invalidateAgentTasks('asset', $a));
        Event::listen('eloquent.updated: '.Customer::class, fn (Customer $c) => $invalidateAgentTasks('customer', $c));
        Event::listen('eloquent.updated: '.MaintenanceOrder::class, fn (MaintenanceOrder $o) => $invalidateAgentTasks('maintenance_order', $o));
        Event::listen('eloquent.updated: '.RentalBillingQueueEntry::class, fn (RentalBillingQueueEntry $e) => $invalidateAgentTasks('billing_entry', $e));
        Event::listen('eloquent.updated: '.ReceivableTitle::class, fn (ReceivableTitle $t) => $invalidateAgentTasks('receivable_title', $t));
    }
}
