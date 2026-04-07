<?php

use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Pharmacy\PrescriptionQueueApiController;
use App\Http\Controllers\Api\Portal\PortalAppointmentController;
use App\Http\Controllers\Api\Portal\PortalAuthController;
use App\Http\Controllers\Api\Portal\PortalBillingController;
use App\Http\Controllers\Api\Portal\PortalEncounterController;
use App\Http\Controllers\Api\Portal\PortalLabResultController;
use App\Http\Controllers\Api\Portal\PortalPrescriptionController;
use App\Http\Controllers\Api\Portal\PortalProfileController;
use App\Http\Controllers\Api\Portal\PortalChatController;
use App\Http\Controllers\Api\Portal\PortalFriendsController;
use App\Http\Controllers\Api\Portal\PortalPatientChatController;
use App\Http\Controllers\Api\Portal\PortalServicesController;
use App\Http\Controllers\Api\Portal\PortalEmergencyContactController;
use App\Http\Controllers\Api\Portal\PortalTeleconsultController;
use App\Http\Controllers\Api\Portal\PushSubscriptionController;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Controllers\Api\QueueController;
use App\Http\Controllers\Api\StockApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::middleware('web')->group(function () {
    Route::get('/stocks', [StockApiController::class, 'index']);
});

// Queue Management routes
Route::get('/queues', [QueueController::class, 'index']);
Route::get('/queues/stats', [QueueController::class, 'stats']);
Route::post('/queues/{id}/update-status', [QueueController::class, 'updateStatus']);

// Prescription routes
Route::get('/prescriptions/{id}/items', [PrescriptionController::class, 'getPrescribedItems']);


Route::prefix('prescription-queue')->middleware(['api', 'throttle:60,1'])->group(function () {

    Route::get('health', function () {
        return response()->json(['status' => 'ok', 'time' => now()]);
    });
    Route::post('create', [PrescriptionQueueApiController::class, 'create']);
    // Create new queue (from EMR)
    // Route::post('/create', [PrescriptionQueueApiController::class, 'create']);

    // Get queue details
    Route::get('/{queueId}', [PrescriptionQueueApiController::class, 'show']);

    // Update queue status
    // Route::put('/{queueId}/status', [PrescriptionQueueApiController::class, 'updateStatus']);

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

// Portal (Salun-at) API routes
Route::prefix('portal')->group(function () {
    Route::post('/login', [PortalAuthController::class, 'login']);
    Route::post('/register', [PortalAuthController::class, 'register']);

    // Public endpoint - no auth required (accessible from login page)
    Route::get('/emergency-contacts', [PortalEmergencyContactController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {
        // Emergency contacts management (CRUD)
        Route::get('/emergency-contacts/all', [PortalEmergencyContactController::class, 'all']);
        Route::post('/emergency-contacts', [PortalEmergencyContactController::class, 'store']);
        Route::put('/emergency-contacts/{id}', [PortalEmergencyContactController::class, 'update']);
        Route::delete('/emergency-contacts/{id}', [PortalEmergencyContactController::class, 'destroy']);
        Route::get('/user', [PortalAuthController::class, 'user']);
        Route::post('/logout', [PortalAuthController::class, 'logout']);

        // Patient profile routes (syncs demographics from hospital DB)
        Route::get('/profile', [PortalProfileController::class, 'profile']);
        Route::put('/profile', [PortalProfileController::class, 'update']);
        Route::get('/profile/addresses', [PortalProfileController::class, 'addresses']);
        Route::get('/profile/family', [PortalProfileController::class, 'family']);
        Route::post('/profile/family', [PortalProfileController::class, 'addFamily']);
        Route::get('/profile/vitals', [PortalProfileController::class, 'vitals']);
        Route::get('/profile/medical-info', [PortalProfileController::class, 'medicalInfo']);

        // Prescription refill routes
        Route::get('/prescriptions', [PortalPrescriptionController::class, 'prescriptions']);
        Route::get('/prescriptions/{id}/items', [PortalPrescriptionController::class, 'prescriptionItems']);
        Route::post('/prescriptions/refill', [PortalPrescriptionController::class, 'requestRefill']);
        Route::get('/prescriptions/refills', [PortalPrescriptionController::class, 'refillHistory']);

        // Patient encounters (records) routes
        Route::get('/encounters', [PortalEncounterController::class, 'encounters']);
        Route::get('/encounters/details', [PortalEncounterController::class, 'encounterDetails']);

        // Laboratory results routes
        Route::get('/lab-results', [PortalLabResultController::class, 'labResults']);
        Route::get('/lab-results/encounter', [PortalLabResultController::class, 'encounterLabResults']);

        // Billing / payment routes
        Route::get('/billing/payments', [PortalBillingController::class, 'payments']);

        // Appointment routes
        Route::get('/appointments', [PortalAppointmentController::class, 'index']);
        Route::get('/appointments/types', [PortalAppointmentController::class, 'types']);
        Route::get('/appointments/clinics', [PortalAppointmentController::class, 'clinics']);
        Route::get('/clinics/directory', [PortalAppointmentController::class, 'directory']);
        Route::get('/appointments/availability', [PortalAppointmentController::class, 'availability']);
        Route::get('/appointments/slots', [PortalAppointmentController::class, 'slots']);
        Route::post('/appointments', [PortalAppointmentController::class, 'store']);
        Route::get('/appointments/{id}', [PortalAppointmentController::class, 'show']);

        // Chat routes (patient-to-staff)
        Route::get('/chat/conversations', [PortalChatController::class, 'conversations']);
        Route::post('/chat/conversations', [PortalChatController::class, 'createConversation']);
        Route::get('/chat/conversations/{id}/messages', [PortalChatController::class, 'messages']);
        Route::post('/chat/conversations/{id}/messages', [PortalChatController::class, 'sendMessage']);
        Route::patch('/chat/conversations/{id}/read', [PortalChatController::class, 'markRead']);

        // Friends routes
        Route::get('/friends', [PortalFriendsController::class, 'index']);
        Route::get('/friends/search', [PortalFriendsController::class, 'search']);
        Route::post('/friends/quick-add', [PortalFriendsController::class, 'quickAdd']);
        Route::post('/friends/request', [PortalFriendsController::class, 'sendRequest']);
        Route::get('/friends/requests', [PortalFriendsController::class, 'requests']);
        Route::get('/friends/requests/sent', [PortalFriendsController::class, 'sentRequests']);
        Route::post('/friends/requests/{requestId}/accept', [PortalFriendsController::class, 'acceptRequest']);
        Route::post('/friends/requests/{requestId}/decline', [PortalFriendsController::class, 'declineRequest']);
        Route::delete('/friends/{friendId}', [PortalFriendsController::class, 'removeFriend']);

        // Push notification subscription routes
        Route::post('/push/subscribe', [PushSubscriptionController::class, 'subscribe']);
        Route::delete('/push/unsubscribe', [PushSubscriptionController::class, 'unsubscribe']);

        // Teleconsult routes
        Route::get('/teleconsult/upcoming', [PortalTeleconsultController::class, 'upcoming']);
        Route::get('/teleconsult/{appointmentId}', [PortalTeleconsultController::class, 'show']);
        Route::get('/teleconsult/{sessionId}/join-info', [PortalTeleconsultController::class, 'joinInfo']);
        Route::post('/teleconsult/{sessionId}/join', [PortalTeleconsultController::class, 'join']);
        Route::post('/teleconsult/{sessionId}/leave', [PortalTeleconsultController::class, 'leave']);

        // Patient-to-patient chat routes
        Route::get('/chat/patient/conversations', [PortalPatientChatController::class, 'conversations']);
        Route::post('/chat/patient/conversations', [PortalPatientChatController::class, 'createConversation']);
        Route::get('/chat/patient/conversations/{id}/messages', [PortalPatientChatController::class, 'messages']);
        Route::post('/chat/patient/conversations/{id}/messages', [PortalPatientChatController::class, 'sendMessage']);
        Route::patch('/chat/patient/conversations/{id}/read', [PortalPatientChatController::class, 'markRead']);
        Route::post('/chat/patient/conversations/{id}/members', [PortalPatientChatController::class, 'addMember']);
        Route::delete('/chat/patient/conversations/{id}/members/{userId}', [PortalPatientChatController::class, 'removeMember']);
        Route::post('/chat/patient/conversations/{id}/leave', [PortalPatientChatController::class, 'leave']);
    });
});
