<?php

use App\Http\Controllers\Api\Pharmacy\PrescriptionQueueApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('prescription-queue')->middleware(['api', 'throttle:60,1'])->group(function () {

    // Create new queue (from EMR)
    Route::post('/create', [PrescriptionQueueApiController::class, 'create']);

    // Get queue details
    Route::get('/{queueId}', [PrescriptionQueueApiController::class, 'show']);

    // Update queue status
    Route::put('/{queueId}/status', [PrescriptionQueueApiController::class, 'updateStatus']);

    // Get location statistics
    Route::get('/stats/{locationCode}', [PrescriptionQueueApiController::class, 'stats']);

    // Get active queues for location
    Route::get('/location/{locationCode}/active', [PrescriptionQueueApiController::class, 'activeQueues']);

    // Check queue by prescription ID
    Route::get('/check/{prescriptionId}', [PrescriptionQueueApiController::class, 'checkByPrescription']);
});

/*
|--------------------------------------------------------------------------
| Example API Usage from EMR
|--------------------------------------------------------------------------
|
| 1. Create Queue:
| POST /api/prescription-queue/create
| {
|     "prescription_id": 12345,
|     "enccode": "0000040H123456789012345",
|     "hpercode": "H2024000123",
|     "location_code": "1",
|     "priority": "normal",
|     "queue_prefix": "RX",
|     "created_by": "EMP001",
|     "created_from": "EMR"
| }
|
| Response:
| {
|     "success": true,
|     "message": "Queue created successfully",
|     "data": {
|         "queue_id": 1,
|         "queue_number": "RX-20250103-0001",
|         "estimated_wait_minutes": 30,
|         "position": 3
|     }
| }
|
| 2. Check Queue Status:
| GET /api/prescription-queue/1
|
| 3. Update Status (when pharmacist starts preparing):
| PUT /api/prescription-queue/1/status
| {
|     "status": "preparing",
|     "user_id": "EMP002"
| }
|
| 4. Get Location Stats:
| GET /api/prescription-queue/stats/1?date=2025-01-03
|
| 5. Check if prescription has queue:
| GET /api/prescription-queue/check/12345
|
*/