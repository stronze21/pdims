<?php

use App\Livewire\Permissions\ManagePermissions;
use App\Livewire\Pharmacy\Dispensing\DispensingEncounter;
use App\Livewire\Pharmacy\Prescriptions\CashierQueueController;
use App\Livewire\Pharmacy\Prescriptions\PrescriptionQueueController;
use App\Livewire\Pharmacy\Prescriptions\PrescriptionQueueDisplay;
use App\Livewire\Pharmacy\Prescriptions\PrescriptionQueueManagement;
use App\Livewire\Pharmacy\Prescriptions\PrescriptionQueueManagementTablet;
use App\Livewire\Pharmacy\Prescriptions\QueueDisplaySettings;
use App\Livewire\Records\PatientsList;
use App\Livewire\Roles\ManageRoles;
use App\Livewire\Users\ManageUsers;
use Illuminate\Support\Facades\Route;




Route::middleware('guest')->get('/', function () {
    return redirect('login');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::prefix('/records')->name('records.')->group(function () {
        Route::get('/patients', PatientsList::class)
            ->name('patients.index');
    });

    Route::prefix('dispensing')->name('dispensing.')->group(function () {
        Route::get('/dispensing/encounter/{enccode}', DispensingEncounter::class)
            ->where('enccode', '.*')->name('view.enctr');
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








    // User Management Routes
    Route::get('/users', ManageUsers::class)
        ->name('users.index');

    // Roles Management Routes
    Route::get('/roles', ManageRoles::class)
        ->name('roles.index');

    // Permissions Management Routes
    Route::get('/permissions', ManagePermissions::class)
        ->name('permissions.index');
});

// Public Queue Display (can be accessed without auth for TV displays)
Route::get('/queue-display/{locationCode?}', PrescriptionQueueDisplay::class)
    ->name('queue.display')
    ->middleware('throttle:60,1'); // Rate limit for security
