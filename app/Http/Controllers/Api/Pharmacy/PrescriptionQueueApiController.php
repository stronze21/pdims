<?php

namespace App\Http\Controllers\Api\Pharmacy;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Pharmacy\PrescriptionQueueService;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueue;

class PrescriptionQueueApiController extends Controller
{
    protected $queueService;

    public function __construct(PrescriptionQueueService $queueService)
    {
        $this->queueService = $queueService;
    }

    /**
     * Create a new queue entry from EMR
     * POST /api/prescription-queue/create
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'prescription_id' => 'required|integer',
            'enccode' => 'required|string|max:50',
            'hpercode' => 'required|string|max:50',
            'location_code' => 'required|string|max:20',
            'priority' => 'sometimes|in:normal,urgent,stat',
            'queue_prefix' => 'sometimes|string|max:10',
            'remarks' => 'sometimes|string',
            'created_by' => 'sometimes|string|max:20',
            'created_from' => 'sometimes|string|max:50',
        ]);

        $result = $this->queueService->createQueue($validated);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'queue_id' => $result['queue']->id,
                    'queue_number' => $result['queue']->queue_number,
                    'estimated_wait_minutes' => $result['queue']->estimated_wait_minutes,
                    'position' => $this->getQueuePosition($result['queue']),
                ],
            ], 201);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
            'error' => $result['error'] ?? null,
        ], 400);
    }

    /**
     * Get queue status
     * GET /api/prescription-queue/{queueId}
     */
    public function show($queueId)
    {
        $queue = PrescriptionQueue::with(['patient', 'prescription'])
            ->find($queueId);

        if (!$queue) {
            return response()->json([
                'success' => false,
                'message' => 'Queue not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $queue->id,
                'queue_number' => $queue->queue_number,
                'status' => $queue->queue_status,
                'priority' => $queue->priority,
                'queued_at' => $queue->queued_at,
                'estimated_wait_minutes' => $queue->estimated_wait_minutes,
                'wait_time_minutes' => $queue->getWaitTimeMinutes(),
                'position' => $this->getQueuePosition($queue),
                'patient' => [
                    'hpercode' => $queue->hpercode,
                    'name' => $queue->patient ? $queue->patient->fullname() : 'N/A',
                ],
            ],
        ]);
    }

    /**
     * Update queue status
     * PUT /api/prescription-queue/{queueId}/status
     */
    public function updateStatus(Request $request, $queueId)
    {
        $validated = $request->validate([
            'status' => 'required|in:waiting,preparing,ready,dispensed,cancelled',
            'user_id' => 'sometimes|string|max:20',
            'remarks' => 'sometimes|string',
        ]);

        $result = $this->queueService->updateQueueStatus(
            $queueId,
            $validated['status'],
            $validated['user_id'] ?? null,
            $validated['remarks'] ?? null
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'queue_id' => $result['queue']->id,
                    'queue_number' => $result['queue']->queue_number,
                    'status' => $result['queue']->queue_status,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
            'error' => $result['error'] ?? null,
        ], 400);
    }

    /**
     * Get location statistics
     * GET /api/prescription-queue/stats/{locationCode}
     */
    public function stats($locationCode, Request $request)
    {
        $date = $request->input('date') ? \Carbon\Carbon::parse($request->input('date')) : today();

        $stats = $this->queueService->getLocationStats($locationCode, $date);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get active queues for location
     * GET /api/prescription-queue/location/{locationCode}/active
     */
    public function activeQueues($locationCode)
    {
        $queues = PrescriptionQueue::forLocation($locationCode)
            ->active()
            ->whereDate('queued_at', today())
            ->orderByPriority()
            ->get()
            ->map(function ($queue) {
                return [
                    'id' => $queue->id,
                    'queue_number' => $queue->queue_number,
                    'status' => $queue->queue_status,
                    'priority' => $queue->priority,
                    'queued_at' => $queue->queued_at,
                    'wait_time_minutes' => $queue->getWaitTimeMinutes(),
                    'hpercode' => $queue->hpercode,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $queues,
            'count' => $queues->count(),
        ]);
    }

    /**
     * Check queue by prescription ID
     * GET /api/prescription-queue/check/{prescriptionId}
     */
    public function checkByPrescription($prescriptionId)
    {
        $queue = PrescriptionQueue::where('prescription_id', $prescriptionId)
            ->whereIn('queue_status', ['waiting', 'preparing', 'ready'])
            ->first();

        if (!$queue) {
            return response()->json([
                'success' => false,
                'message' => 'No active queue found for this prescription',
                'has_queue' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'has_queue' => true,
            'data' => [
                'id' => $queue->id,
                'queue_number' => $queue->queue_number,
                'status' => $queue->queue_status,
                'position' => $this->getQueuePosition($queue),
                'estimated_wait_minutes' => $queue->estimated_wait_minutes,
            ],
        ]);
    }

    /**
     * Helper: Get queue position
     */
    private function getQueuePosition($queue)
    {
        if (!$queue->isWaiting()) {
            return null;
        }

        return PrescriptionQueue::forLocation($queue->location_code)
            ->waiting()
            ->whereDate('queued_at', $queue->queued_at)
            ->where('queued_at', '<=', $queue->queued_at)
            ->count();
    }
}
