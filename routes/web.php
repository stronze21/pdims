<?php

use App\Livewire\Roles\ManageRoles;
use App\Livewire\Users\ManageUsers;
use Illuminate\Support\Facades\Route;
use App\Livewire\Records\PatientsList;
use App\Livewire\Permissions\ManagePermissions;
use App\Livewire\Pharmacy\Dispensing\DispensingEncounter;
use App\Livewire\Pharmacy\Prescriptions\PrescriptionQueueDisplay;
use App\Livewire\Pharmacy\Prescriptions\PrescriptionQueueManagement;
use App\Livewire\Pharmacy\Prescriptions\PrescriptionQueueManagementTablet;

Route::get('/', function () {
    return view('welcome');
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
    });

    // Public Queue Display (can be accessed without auth for TV displays)
    Route::get('/queue-display/{locationCode?}', PrescriptionQueueDisplay::class)
        ->name('queue.display')
        ->middleware('throttle:60,1'); // Rate limit for security

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
