<?php

use App\Http\Controllers\AssetPrintController;
use App\Http\Controllers\AssetQrCodeController;
use App\Http\Controllers\AssetScanController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Documents\AssetPdfController;
use App\Http\Controllers\Documents\MaintenanceOrderPdfController;
use App\Http\Controllers\Documents\RentalPdfController;
use App\Http\Controllers\Reports\CommercialReportExportController;
use App\Livewire\Admin\AuditIndex;
use App\Livewire\Admin\UserIndex;
use App\Livewire\Customer\CustomerIndex;
use App\Livewire\Customer\CustomerShow;
use App\Livewire\Dashboard\DashboardIndex;
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
use App\Livewire\Rental\RentalIndex;
use App\Livewire\Rental\RentalShow;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardIndex::class)->name('dashboard');

    Route::view('profile', 'profile')->name('profile');

    Route::prefix('frota')->name('fleet.')->group(function () {
        Route::get('categorias', CategoryIndex::class)->name('categories.index');
        Route::get('categorias/{category}', CategoryShow::class)->name('categories.show');
        Route::get('modelos', ModelIndex::class)->name('models.index');
        Route::get('precos', PricingIndex::class)->name('pricing.index');
    });

    Route::get('patrimonios', AssetIndex::class)->name('assets.index');
    Route::get('patrimonios/scan/{codigo}', AssetScanController::class)->name('assets.scan');
    Route::get('patrimonios/{asset}/imprimir', AssetPrintController::class)->name('assets.print');
    Route::get('patrimonios/{asset}/pdf', AssetPdfController::class)->name('assets.pdf');
    Route::get('patrimonios/{asset}/qr', [AssetQrCodeController::class, 'show'])->name('assets.qr-image');
    Route::get('patrimonios/{asset}', AssetShow::class)->name('assets.show');

    Route::get('clientes', CustomerIndex::class)->name('customers.index');
    Route::get('clientes/{customer}', CustomerShow::class)->name('customers.show');

    Route::get('locacoes', RentalIndex::class)->name('rentals.index');
    Route::get('locacoes/{rental}/pdf', RentalPdfController::class)->name('rentals.pdf');
    Route::get('locacoes/{rental}', RentalShow::class)->name('rentals.show');

    Route::get('relatorios/comercial', CommercialReportIndex::class)->name('reports.commercial');
    Route::get('relatorios/comercial/exportar', CommercialReportExportController::class)->name('reports.commercial.export');

    Route::get('manutencao', MaintenanceOrderIndex::class)->name('maintenance.index');
    Route::get('manutencao/pecas', PartCatalogIndex::class)->name('maintenance.parts.index');
    Route::get('manutencao/preventiva', PreventiveRuleIndex::class)->name('maintenance.preventive.index');
    Route::get('manutencao/{order}/pdf', MaintenanceOrderPdfController::class)->name('maintenance.pdf');
    Route::get('manutencao/{order}', MaintenanceOrderShow::class)->name('maintenance.show');

    Route::prefix('admin')->name('admin.')->middleware('permission:admin.users.view|audit.view')->group(function () {
        Route::get('usuarios', UserIndex::class)->name('users.index')->middleware('permission:admin.users.view');
        Route::get('auditoria', AuditIndex::class)->name('audit.index')->middleware('permission:audit.view');
    });

    Route::get('anexos/{attachment}/download', [AttachmentController::class, 'download'])
        ->name('attachments.download');
});

require __DIR__.'/auth.php';
