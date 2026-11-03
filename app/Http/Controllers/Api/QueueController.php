<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueue;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueueLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QueueController extends Controller
{
    public function index(Request $request)
    {
        $query = PrescriptionQueue::with(['patient', 'prescription', 'preparer', 'dispenser'])
            ->whereDate('queued_at', today());

        if ($request->has('location')) {
            $query->where('location_code', $request->location);
        }

        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->whereIn('queue_status', ['waiting', 'preparing', 'ready']);
            } else {
                $query->where('queue_status', $request->status);
            }
        }

        $queues = $query->orderByRaw("
            CASE priority
                WHEN 'stat' THEN 1
                WHEN 'urgent' THEN 2
                ELSE 3
            END
        ")->orderBy('queued_at', 'asc')->get();

        return response()->json($queues);
    }

    public function stats(Request $request)
    {
        $locationCode = $request->input('location');
        $date = today();

        $query = PrescriptionQueue::whereDate('queued_at', $date);

        if ($locationCode) {
            $query->where('location_code', $locationCode);
        }

        $stats = [
            'total_today' => (clone $query)->count(),
            'waiting' => (clone $query)->where('queue_status', 'waiting')->count(),
            'preparing' => (clone $query)->where('queue_status', 'preparing')->count(),
            'ready' => (clone $query)->where('queue_status', 'ready')->count(),
            'dispensed' => (clone $query)->where('queue_status', 'dispensed')->count(),
            'cancelled' => (clone $query)->where('queue_status', 'cancelled')->count(),
            'avg_wait_time' => $this->getAverageWaitTime($locationCode),
        ];

        return response()->json($stats);
    }

    private function getAverageWaitTime($locationCode = null)
    {
        $query = PrescriptionQueue::where('queue_status', 'dispensed')
            ->whereDate('queued_at', today())
            ->whereNotNull('dispensed_at');

        if ($locationCode) {
            $query->where('location_code', $locationCode);
        }

        $dispensed = $query->get();

        if ($dispensed->isEmpty()) return 0;

        $totalMinutes = $dispensed->sum(function ($queue) {
            return $queue->getTotalTimeMinutes();
        });

        return round($totalMinutes / $dispensed->count());
    }

    public function updateStatus(Request $request, $id)
    {
        $statuses = ['start_preparing' => 'preparing', 'mark_ready' => 'ready', 'mark_dispensed' => 'dispensed', 'cancel' => 'cancelled'];
        $status = $statuses[$request->input('action')] ?? null;
        if (!$status) return response()->json(['message' => 'Invalid action'], 400);
        if (!PrescriptionQueue::whereKey($id)->exists()) return response()->json(['message' => 'Queue not found'], 404);
        $result = app(\App\Services\Pharmacy\PrescriptionQueueService::class)->updateQueueStatus(
            $id, $status, optional($request->user())->employeeid, $request->input('remarks')
        );
        if (!$result['success']) return response()->json(['message' => $result['message']], 409);
        return response()->json($result['queue']->load(['patient', 'prescription']));
    }
}
