<?php

use App\Http\Controllers\AssetPrintController;
use App\Http\Controllers\AssetQrCodeController;
use App\Http\Controllers\AssetScanController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Documents\AssetPdfController;
use App\Http\Controllers\Documents\BillingInvoicePdfController;
use App\Http\Controllers\Documents\MaintenanceOrderPdfController;
use App\Http\Controllers\Documents\RentalContractPdfController;
use App\Http\Controllers\Documents\RentalPdfController;
use App\Http\Controllers\Reports\RentalPanelExportController;
use App\Http\Controllers\Finance\AccountingExportController;
use App\Http\Controllers\Finance\BillingEntryExportController;
use App\Http\Controllers\Finance\ReceivableExportController;
use App\Http\Controllers\Finance\ReceivableTitleExportController;
use App\Http\Controllers\Reports\CommercialReportExportController;
use App\Livewire\Finance\BillingQueueIndex;
use App\Livewire\Finance\CashFlowIndex;
use App\Livewire\Finance\DelinquencyReportIndex;
use App\Livewire\Finance\ReceivableIndex;
use App\Livewire\Admin\AgentLogIndex;
use App\Livewire\Admin\AuditIndex;
use App\Livewire\Admin\OperatingCompanyIndex;
use App\Livewire\Admin\UserIndex;
use App\Livewire\Copilot\CopilotIndex;
use App\Livewire\Customer\CustomerIndex;
use App\Livewire\Customer\CustomerShow;
use App\Livewire\Person\CompanyIndex;
use App\Livewire\Person\PersonIndex;
use App\Livewire\Person\PersonShow;
use App\Livewire\Dashboard\DashboardIndex;
use App\Livewire\Layout\GlobalSearchResults;
use App\Livewire\Logistics\LogisticsDailyIndex;
use App\Livewire\Logistics\YardIndex;
use App\Livewire\Fleet\AssetIndex;
use App\Livewire\Fleet\AssetShow;
use App\Livewire\Fleet\CategoryIndex;
use App\Livewire\Fleet\CategoryShow;
use App\Livewire\Fleet\ModelIndex;
use App\Livewire\Pricing\PricingIndex;
use App\Livewire\Maintenance\MaintenanceOrderIndex;
use App\Livewire\Maintenance\MaintenanceOrderShow;
use App\Livewire\Maintenance\PartCatalogIndex;
use App\Livewire\Maintenance\PreventiveRuleIndex;
use App\Livewire\Reports\CommercialReportIndex;
use App\Livewire\Reports\FinancialAnalysisIndex;
use App\Livewire\Rental\QuoteIndex;
use App\Livewire\Rental\RentalIndex;
use App\Livewire\Rental\RentalShow;
use App\Livewire\Yard\AssetYardScan;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth'])->group(function () {
    Route::post('empresa/selecionar', function (Illuminate\Http\Request $request) {
        $request->validate(['company_id' => 'required|integer|exists:operating_companies,id']);
        \App\Support\ActiveOperatingCompany::set((int) $request->input('company_id'));
        return redirect()->back();
    })->name('operating-company.set');
    Route::get('dashboard', DashboardIndex::class)->name('dashboard');

    Route::get('copiloto', CopilotIndex::class)
        ->middleware('permission:agent.api')
        ->name('copilot.index');

    Route::get('busca', GlobalSearchResults::class)->name('search.results');

    Route::view('profile', 'profile')->name('profile');

    Route::prefix('frota')->name('fleet.')->group(function () {
        Route::get('categorias', CategoryIndex::class)->name('categories.index');
        Route::get('categorias/{category}', CategoryShow::class)->name('categories.show');
        Route::get('modelos', ModelIndex::class)->name('models.index');
        Route::get('precos', PricingIndex::class)->name('pricing.index');
    });

    Route::get('patrimonios', AssetIndex::class)->name('assets.index');
    Route::get('patrimonios/scan/{codigo}', AssetScanController::class)->name('assets.scan');
    Route::get('patio/{codigo}', AssetYardScan::class)->name('yard.scan')->middleware('permission:rentals.view');
    Route::get('patrimonios/{asset}/imprimir', AssetPrintController::class)->name('assets.print');
    Route::get('patrimonios/{asset}/pdf', AssetPdfController::class)->name('assets.pdf');
    Route::get('patrimonios/{asset}/qr', [AssetQrCodeController::class, 'show'])->name('assets.qr-image');
    Route::get('patrimonios/{asset}', AssetShow::class)->name('assets.show');

    Route::get('clientes', CustomerIndex::class)->name('customers.index');
    Route::get('clientes/{customer}', CustomerShow::class)->name('customers.show');

    Route::get('pessoas', PersonIndex::class)->name('people.index');
    Route::get('pessoas/{person}', PersonShow::class)->name('people.show');
    Route::get('empresas', CompanyIndex::class)->name('companies.index');

    Route::get('locacoes', RentalIndex::class)->name('rentals.index');
    Route::get('orcamentos', QuoteIndex::class)->name('quotes.index');
    Route::get('locacoes/painel/exportar', RentalPanelExportController::class)->name('rentals.panel.export');
    Route::get('locacoes/{rental}/pdf', RentalPdfController::class)->name('rentals.pdf');
    Route::get('locacoes/{rental}/contrato', RentalContractPdfController::class)->name('rentals.contract.pdf');
    Route::get('locacoes/{rental}', RentalShow::class)->name('rentals.show');

    Route::prefix('logistica')->name('logistics.')->group(function () {
        Route::get('lista-do-dia', LogisticsDailyIndex::class)->name('daily');
        Route::get('patios', YardIndex::class)->name('yards.index');
    });

    Route::get('relatorios/comercial', CommercialReportIndex::class)->name('reports.commercial');
    Route::get('relatorios/comercial/exportar', CommercialReportExportController::class)->name('reports.commercial.export');
    Route::get('relatorios/analise-financeira', FinancialAnalysisIndex::class)->name('reports.financial-analysis');

    Route::prefix('financeiro')->name('finance.')->group(function () {
        Route::get('titulos', ReceivableIndex::class)->name('receivables');
        Route::get('a-faturar', BillingQueueIndex::class)->name('billing-queue');
        Route::get('inadimplencia', DelinquencyReportIndex::class)->name('delinquency');
        Route::get('fluxo-caixa', CashFlowIndex::class)->name('cashflow');
        Route::get('exportar', ReceivableExportController::class)->name('export');
        Route::get('exportar-contabil', AccountingExportController::class)->name('accounting.export');
        Route::get('faturas/{entry}/pdf', BillingInvoicePdfController::class)->name('billing.pdf');
        Route::get('faturas/{entry}/exportar', BillingEntryExportController::class)->name('billing.export');
        Route::get('titulos/{title}/exportar', ReceivableTitleExportController::class)->name('receivable.export');
    });

    Route::get('manutencao', MaintenanceOrderIndex::class)->name('maintenance.index');
    Route::get('manutencao/pecas', PartCatalogIndex::class)->name('maintenance.parts.index');
    Route::get('manutencao/preventiva', PreventiveRuleIndex::class)->name('maintenance.preventive.index');
    Route::get('manutencao/{order}/pdf', MaintenanceOrderPdfController::class)->name('maintenance.pdf');
    Route::get('manutencao/{order}', MaintenanceOrderShow::class)->name('maintenance.show');

    Route::prefix('admin')->name('admin.')->middleware('permission:admin.users.view|admin.companies.manage|audit.view')->group(function () {
        Route::get('usuarios', UserIndex::class)->name('users.index')->middleware('permission:admin.users.view');
        Route::get('empresas', OperatingCompanyIndex::class)->name('companies.index')->middleware('permission:admin.companies.manage');
        Route::get('auditoria', AuditIndex::class)->name('audit.index')->middleware('permission:audit.view');
        Route::get('copiloto-logs', AgentLogIndex::class)->name('agent-logs.index')->middleware('permission:audit.view');
    });

    Route::get('anexos/{attachment}/download', [AttachmentController::class, 'download'])
        ->name('attachments.download');
});

require __DIR__.'/auth.php';
