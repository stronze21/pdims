<?php

use App\Livewire\Dashboard\CriticalStockItems;
use App\Livewire\Dashboard\EmergencyPurchases;
use App\Livewire\Dashboard\ExpiredItems;
use App\Livewire\Dashboard\IssuedOrders;
use App\Livewire\Dashboard\NearExpiryItems;
use App\Livewire\Dashboard\PendingOrders;
use App\Livewire\Dashboard\QueueDetails;
use App\Livewire\Dashboard\ReturnedOrders;
use App\Livewire\DashboardExecutive;
use App\Livewire\Permissions\ManagePermissions;
use App\Livewire\Pharmacy\Dispensing\DispensingEncounter;
use App\Livewire\Pharmacy\Dispensing\ReturnSlip;
use App\Livewire\Pharmacy\Dispensing\RxoChargeSlip;
use App\Livewire\Pharmacy\Drugs\IoTransactions;
use App\Livewire\Pharmacy\Drugs\ReorderLevel;
use App\Livewire\Pharmacy\Drugs\ReorderLevelComputed;
use App\Livewire\Pharmacy\Drugs\StockCard;
use App\Livewire\Pharmacy\Drugs\StockList;
use App\Livewire\Pharmacy\Drugs\StockSummary;
use App\Livewire\Pharmacy\Drugs\ViewIoTransDate;
use App\Livewire\Pharmacy\Drugs\ViewIoTransRef;
use App\Livewire\Pharmacy\Drugs\ViewWardRisDate;
use App\Livewire\Pharmacy\Drugs\ViewWardRisRef;
use App\Livewire\Pharmacy\Drugs\WardRisTrans;
use App\Livewire\Pharmacy\ManageNonPnfDrugs;
use App\Livewire\Pharmacy\Purchases\DeliveryList;
use App\Livewire\Pharmacy\Purchases\DeliveryListDonations;
use App\Livewire\Pharmacy\Purchases\DeliveryView;
use App\Livewire\Pharmacy\Purchases\EmergencyPurchases as PurchaseEmergencyPurchases;
use App\Livewire\Pharmacy\Purchases\PimsRisList;
use App\Livewire\Pharmacy\Purchases\ShowRis;
use App\Livewire\Pharmacy\Prescriptions\PrescriptionEr;
use App\Livewire\Pharmacy\Prescriptions\PrescriptionOpd;
use App\Livewire\Pharmacy\Prescriptions\PrescriptionWard;
use App\Livewire\Pharmacy\Prescriptions\Queueing\CashierQueueController;
use App\Livewire\Pharmacy\Prescriptions\Queueing\PrescriptionQueueController;
use App\Livewire\Pharmacy\Prescriptions\Queueing\PrescriptionQueueDisplay;
use App\Livewire\Pharmacy\Prescriptions\Queueing\PrescriptionQueueManagement;
use App\Livewire\Pharmacy\Prescriptions\Queueing\PrescriptionQueueManagementTablet;
use App\Livewire\Pharmacy\Prescriptions\Queueing\QueueDisplaySettings;
use App\Livewire\Pharmacy\Settings\ManageZeroBillingCharges;
use App\Livewire\Portal\ManagePortalUsers;
use App\Livewire\Records\DischargedPatients;
use App\Livewire\Records\ForDischargePatients;
use App\Livewire\Records\PatientsList;
use App\Livewire\Roles\ManageRoles;
use App\Livewire\Users\ManageUsers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;









Route::middleware('guest')->get('/', function () {
    return redirect('login');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', DashboardExecutive::class)->name('dashboard');

    // Dashboard KPI Detail Pages
    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/pending-orders', PendingOrders::class)->name('pending-orders');
        Route::get('/issued-orders', IssuedOrders::class)->name('issued-orders');
        Route::get('/returned-orders', ReturnedOrders::class)->name('returned-orders');
        Route::get('/near-expiry', NearExpiryItems::class)->name('near-expiry');
        Route::get('/expired-items', ExpiredItems::class)->name('expired-items');
        Route::get('/critical-stock', CriticalStockItems::class)->name('critical-stock');
        Route::get('/emergency-purchases', EmergencyPurchases::class)->name('emergency-purchases');
        Route::get('/queue-details', QueueDetails::class)->name('queue-details');
    });

    Route::prefix('/inventory')->name('inventory.')->group(function () {
        Route::get('/stocks', StockList::class)->name('stocks.list');
        Route::get('/stock-summary', StockSummary::class)->name('stocks.summary');
        Route::get('/stock-card', StockCard::class)->name('stocks.card');
        Route::get('/reorder-level', ReorderLevel::class)->name('stocks.reorder');
        Route::get('/reorder-level-computed', ReorderLevelComputed::class)->name('stocks.reorder-computed');
        Route::get('/io-transactions', IoTransactions::class)->name('io-trans');
        Route::get('/io-transactions/view/ref/{reference_no}', ViewIoTransRef::class)->name('io-trans.view-ref');
        Route::get('/io-transactions/view/date/{date}', ViewIoTransDate::class)->name('io-trans.view-date');
        Route::get('/ward-ris', WardRisTrans::class)->name('ward-ris');
        Route::get('/ward-ris/view/ref/{reference_no}', ViewWardRisRef::class)->name('ward-ris.view-ref');
        Route::get('/ward-ris/view/date/{date}', ViewWardRisDate::class)->name('ward-ris.view-date');
    });

    Route::prefix('/records')->name('records.')->group(function () {
        Route::get('/patients', PatientsList::class)
            ->name('patients.index');
        Route::get('/for-discharge-patients', ForDischargePatients::class)
            ->name('for-discharge-patients');
        Route::get('/discharged-patients', DischargedPatients::class)
            ->name('discharged-patients');
    });

    Route::prefix('dispensing')->name('dispensing.')->group(function () {
        Route::get('/encounter/{enccode?}', DispensingEncounter::class)
            ->where('enccode', '.*')->name('view.enctr');
        Route::get('/encounter/charge/{pcchrgcod}', RxoChargeSlip::class)
            ->name('rxo.chargeslip');
        Route::get('/return-slip/{hpercode}', ReturnSlip::class)
            ->name('rxo.return.sum');
    });

    Route::prefix('/purchases')->name('purchases.')->group(function () {
        Route::get('/ris', PimsRisList::class)->name('ris');
        Route::get('/ris/print/{id}', [App\Http\Controllers\RisPrintController::class, 'print'])->name('ris-print');
        Route::get('/ris/{id}', ShowRis::class)->name('ris-show');
        Route::get('/deliveries', DeliveryList::class)->name('deliveries');
        Route::get('/donations', DeliveryListDonations::class)->name('donations');
        Route::get('/emergency-purchase', PurchaseEmergencyPurchases::class)->name('emergency-purchase');
        Route::get('/delivery/{delivery_id}', DeliveryView::class)->name('delivery-view');
    });

    Route::get('/pharmacy/non-pnf-drugs', ManageNonPnfDrugs::class)
        ->name('pharmacy.non-pnf-drugs');

    Route::prefix('rx')->name('rx.')->group(function () {
        Route::get('/opd', PrescriptionOpd::class)->name('opd');
        Route::get('/ward', PrescriptionWard::class)->name('ward');
        Route::get('/er', PrescriptionEr::class)->name('er');
    });

    // Prescription Queue Management Routes
    Route::prefix('prescriptions')->name('prescriptions.')->group(function () {
        Route::get('/queue', PrescriptionQueueManagement::class)
            ->name('queue.index');
        Route::get('/queue/controller', PrescriptionQueueManagementTablet::class)->name('queue.controller');
        Route::get('/queue/controller/v2', PrescriptionQueueController::class)->name('queue.controller2');
        Route::get('/queue-display-settings', QueueDisplaySettings::class)->name('queue.display-setting');
        Route::get('/cashier-queue', CashierQueueController::class)
            ->name('cashier.queue');
    });

    Route::get('/prescriptions/queue/print/{queueId}', function ($queueId) {
        $printItems = session('print_items', []);

        if (empty($printItems)) {
            return redirect()->back()->with('error', 'No items selected for printing');
        }

        $queue = \App\Models\Pharmacy\Prescriptions\PrescriptionQueue::with(['patient'])
            ->find($queueId);

        if (!$queue) {
            return redirect()->back()->with('error', 'Queue not found');
        }

        $items = collect(DB::connection('webapp')->select("
        SELECT
            pd.id, pd.dmdcomb, pd.dmdctr, pd.qty, pd.order_type,
            pd.remark, pd.addtl_remarks, pd.tkehome,
            pd.frequency, pd.duration, dm.drug_concat
        FROM prescription_data pd
        INNER JOIN hospital.dbo.hdmhdr dm ON pd.dmdcomb = dm.dmdcomb AND pd.dmdctr = dm.dmdctr
        WHERE pd.presc_id = ? AND pd.stat = 'A' AND pd.id IN (" . implode(',', array_fill(0, count($printItems), '?')) . ")
        ORDER BY pd.created_at ASC
    ", array_merge([$queue->prescription_id], $printItems)));

        return view('pharmacy.prescriptions.print', [
            'queue' => $queue,
            'items' => $items
        ]);
    })->name('prescriptions.print');

    Route::get('/dispensing/prescription/print/{enccode}', function ($enccode) {
        $printItems = session('print_encounter_items', []);

        if (empty($printItems)) {
            return redirect()->back()->with('error', 'No items selected for printing');
        }

        $encounter = collect(DB::select("
            SELECT TOP 1 enctr.hpercode, enctr.toecode, enctr.enccode, enctr.encdate,
                   pat.patlast, pat.patfirst, pat.patmiddle
            FROM hospital.dbo.henctr enctr WITH (NOLOCK)
            INNER JOIN hospital.dbo.hperson pat WITH (NOLOCK) ON enctr.hpercode = pat.hpercode
            WHERE enctr.enccode = ?
        ", [$enccode]))->first();

        if (!$encounter) {
            return redirect()->back()->with('error', 'Encounter not found');
        }

        $items = collect(DB::connection('webapp')->select("
            SELECT
                pd.id, pd.dmdcomb, pd.dmdctr, pd.qty, pd.order_type,
                pd.remark, pd.addtl_remarks,
                pd.frequency, pd.duration, dm.drug_concat
            FROM prescription_data pd
            INNER JOIN prescription rx ON pd.presc_id = rx.id
            INNER JOIN hospital.dbo.hdmhdr dm ON pd.dmdcomb = dm.dmdcomb AND pd.dmdctr = dm.dmdctr
            WHERE rx.enccode = ? AND pd.stat = 'A' AND pd.id IN (" . implode(',', array_fill(0, count($printItems), '?')) . ")
            ORDER BY pd.created_at ASC
        ", array_merge([$enccode], $printItems)));

        return view('pharmacy.dispensing.print-prescription', [
            'encounter' => $encounter,
            'items' => $items,
        ]);
    })->name('dispensing.print.prescription');

    // User Management Routes
    Route::get('/users', ManageUsers::class)
        ->name('users.index');

    // Roles Management Routes
    Route::get('/roles', ManageRoles::class)
        ->name('roles.index');

    // Permissions Management Routes
    Route::get('/permissions', ManagePermissions::class)
        ->name('permissions.index');

    Route::get('/settings/zero-billing-charges', ManageZeroBillingCharges::class)->name('settings.zero-billing');

    Route::get('/settings/portal/users', ManagePortalUsers::class)->name('settings.portal.users');
});

// Public Queue Display (can be accessed without auth for TV displays)
Route::get('/queue-display/{locationCode?}', PrescriptionQueueDisplay::class)
    ->name('queue.display')
    ->middleware('throttle:60,1'); // Rate limit for security