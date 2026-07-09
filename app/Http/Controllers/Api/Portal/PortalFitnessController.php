<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\PortalFitnessGoal;
use App\Models\Portal\PortalFitnessLog;
use App\Models\Portal\PortalFitnessReminder;
use App\Models\Portal\PortalPatient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PortalFitnessController extends Controller
{
    public function summary(Request $request)
    {
        $patient = $this->getPatient($request);
        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        $goals = $patient->fitnessGoals()->where('is_active', true)->get();
        $logs = $patient->fitnessLogs()->get();

        return response()->json($this->buildSummary($patient, $goals, $logs));
    }

    public function goals(Request $request)
    {
        $patient = $this->getPatient($request);
        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        return response()->json(
            $patient->fitnessGoals()
                ->orderByDesc('is_active')
                ->orderBy('title')
                ->get()
                ->map(fn (PortalFitnessGoal $goal) => $this->formatGoal($goal))
        );
    }

    public function storeGoal(Request $request)
    {
        $patient = $this->getPatient($request);
        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'habit_type' => 'required|string|max:50',
            'unit' => 'required|string|max:50',
            'target_value' => 'required|numeric|min:0.01',
            'frequency' => 'nullable|string|max:50',
            'goal_category' => 'nullable|string|max:50',
            'source_type' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $goal = $patient->fitnessGoals()->create([
            'title' => $validated['title'],
            'habit_type' => $validated['habit_type'],
            'unit' => $validated['unit'],
            'target_value' => $validated['target_value'],
            'frequency' => $validated['frequency'] ?? 'daily',
            'goal_category' => $validated['goal_category'] ?? 'daily_habit',
            'source_type' => $validated['source_type'] ?? 'self_managed',
            'notes' => $validated['notes'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json($this->formatGoal($goal->fresh()), 201);
    }

    public function updateGoal(Request $request, int $id)
    {
        $goal = $this->resolveGoal($request, $id);
        if (!$goal) {
            return response()->json(['message' => 'Fitness goal not found.'], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'habit_type' => 'sometimes|required|string|max:50',
            'unit' => 'sometimes|required|string|max:50',
            'target_value' => 'sometimes|required|numeric|min:0.01',
            'frequency' => 'sometimes|required|string|max:50',
            'goal_category' => 'sometimes|required|string|max:50',
            'source_type' => 'sometimes|required|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $goal->update($validated);

        return response()->json($this->formatGoal($goal->fresh()));
    }

    public function destroyGoal(Request $request, int $id)
    {
        $goal = $this->resolveGoal($request, $id);
        if (!$goal) {
            return response()->json(['message' => 'Fitness goal not found.'], 404);
        }

        $goal->delete();

        return response()->json(['message' => 'Fitness goal deleted successfully.']);
    }

    public function logs(Request $request)
    {
        $patient = $this->getPatient($request);
        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        $query = $patient->fitnessLogs()->with('goal');

        if ($request->filled('from')) {
            $query->where('logged_at', '>=', Carbon::parse($request->query('from')));
        }
        if ($request->filled('to')) {
            $query->where('logged_at', '<=', Carbon::parse($request->query('to')));
        }

        return response()->json(
            $query->orderByDesc('logged_at')
                ->get()
                ->map(fn (PortalFitnessLog $log) => $this->formatLog($log))
        );
    }

    public function storeLog(Request $request)
    {
        $patient = $this->getPatient($request);
        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        $validated = $request->validate([
            'goal_id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'habit_type' => 'required|string|max:50',
            'value' => 'required|numeric|min:0.01',
            'unit' => 'required|string|max:50',
            'logged_at' => 'nullable|date',
            'source_type' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);

        $goal = null;
        if (!empty($validated['goal_id'])) {
            $goal = $this->resolveGoal($request, (int) $validated['goal_id']);
            if (!$goal) {
                return response()->json(['message' => 'Linked fitness goal not found.'], 404);
            }
        }

        $log = $patient->fitnessLogs()->create([
            'goal_id' => $goal?->id,
            'title' => $validated['title'],
            'habit_type' => $validated['habit_type'],
            'value' => $validated['value'],
            'unit' => $validated['unit'],
            'logged_at' => isset($validated['logged_at']) ? Carbon::parse($validated['logged_at']) : now(),
            'source_type' => $validated['source_type'] ?? 'manual',
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($this->formatLog($log->fresh('goal')), 201);
    }

    public function reminders(Request $request)
    {
        $patient = $this->getPatient($request);
        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        return response()->json(
            $patient->fitnessReminders()
                ->orderBy('time_of_day')
                ->get()
                ->map(fn (PortalFitnessReminder $reminder) => $this->formatReminder($reminder))
        );
    }

    public function storeReminder(Request $request)
    {
        $patient = $this->getPatient($request);
        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        $validated = $request->validate([
            'goal_id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'habit_type' => 'required|string|max:50',
            'time_of_day' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'days_of_week' => 'required|array|min:1',
            'days_of_week.*' => 'integer|min:0|max:6',
            'message' => 'nullable|string|max:1000',
            'is_enabled' => 'boolean',
            'source_type' => 'nullable|string|max:50',
        ]);

        $goal = null;
        if (!empty($validated['goal_id'])) {
            $goal = $this->resolveGoal($request, (int) $validated['goal_id']);
            if (!$goal) {
                return response()->json(['message' => 'Linked fitness goal not found.'], 404);
            }
        }

        $reminder = $patient->fitnessReminders()->create([
            'goal_id' => $goal?->id,
            'title' => $validated['title'],
            'habit_type' => $validated['habit_type'],
            'time_of_day' => $validated['time_of_day'],
            'days_of_week' => array_values($validated['days_of_week']),
            'message' => $validated['message'] ?? null,
            'is_enabled' => $validated['is_enabled'] ?? true,
            'source_type' => $validated['source_type'] ?? 'self_managed',
        ]);

        return response()->json($this->formatReminder($reminder->fresh()), 201);
    }

    public function updateReminder(Request $request, int $id)
    {
        $reminder = $this->resolveReminder($request, $id);
        if (!$reminder) {
            return response()->json(['message' => 'Fitness reminder not found.'], 404);
        }

        $validated = $request->validate([
            'goal_id' => 'nullable|integer',
            'title' => 'sometimes|required|string|max:255',
            'habit_type' => 'sometimes|required|string|max:50',
            'time_of_day' => ['sometimes', 'required', 'regex:/^\d{2}:\d{2}$/'],
            'days_of_week' => 'sometimes|required|array|min:1',
            'days_of_week.*' => 'integer|min:0|max:6',
            'message' => 'nullable|string|max:1000',
            'is_enabled' => 'boolean',
            'source_type' => 'sometimes|required|string|max:50',
        ]);

        if (array_key_exists('goal_id', $validated) && !empty($validated['goal_id'])) {
            $goal = $this->resolveGoal($request, (int) $validated['goal_id']);
            if (!$goal) {
                return response()->json(['message' => 'Linked fitness goal not found.'], 404);
            }
        }

        if (isset($validated['days_of_week'])) {
            $validated['days_of_week'] = array_values($validated['days_of_week']);
        }

        $reminder->update($validated);

        return response()->json($this->formatReminder($reminder->fresh()));
    }

    public function destroyReminder(Request $request, int $id)
    {
        $reminder = $this->resolveReminder($request, $id);
        if (!$reminder) {
            return response()->json(['message' => 'Fitness reminder not found.'], 404);
        }

        $reminder->delete();

        return response()->json(['message' => 'Fitness reminder deleted successfully.']);
    }

    private function getPatient(Request $request): ?PortalPatient
    {
        $account = $request->user();
        if (!$account) {
            return null;
        }

        $account->load('patient');
        return $account->patient;
    }

    private function resolveGoal(Request $request, int $goalId): ?PortalFitnessGoal
    {
        $patient = $this->getPatient($request);
        if (!$patient) {
            return null;
        }

        return $patient->fitnessGoals()->where('id', $goalId)->first();
    }

    private function resolveReminder(Request $request, int $reminderId): ?PortalFitnessReminder
    {
        $patient = $this->getPatient($request);
        if (!$patient) {
            return null;
        }

        return $patient->fitnessReminders()->where('id', $reminderId)->first();
    }

    private function buildSummary(PortalPatient $patient, Collection $goals, Collection $logs): array
    {
        $today = now()->startOfDay();
        $todayLogs = $logs->filter(fn (PortalFitnessLog $log) => $log->logged_at && $log->logged_at->copy()->startOfDay()->equalTo($today));

        $todayProgress = $goals->map(function (PortalFitnessGoal $goal) use ($todayLogs) {
            $loggedValue = $todayLogs
                ->filter(function (PortalFitnessLog $log) use ($goal) {
                    return $log->goal_id === $goal->id || ($log->goal_id === null && $log->habit_type === $goal->habit_type);
                })
                ->sum(fn (PortalFitnessLog $log) => (float) $log->value);

            $target = (float) $goal->target_value;
            $percent = $target > 0 ? min(100, round(($loggedValue / $target) * 100, 1)) : 0;

            return [
                'goal_id' => $goal->id,
                'title' => $goal->title,
                'habit_type' => $goal->habit_type,
                'unit' => $goal->unit,
                'goal_target' => $this->normalizeNumber($goal->target_value),
                'logged_value' => $this->normalizeNumber($loggedValue),
                'percent_complete' => $percent,
                'is_complete' => $target > 0 && $loggedValue >= $target,
            ];
        })->values();

        $weeklyTrend = collect(range(0, 6))->map(function (int $offset) use ($today, $goals, $logs) {
            $day = $today->copy()->subDays(6 - $offset);
            $dayLogs = $logs->filter(fn (PortalFitnessLog $log) => $log->logged_at && $log->logged_at->copy()->startOfDay()->equalTo($day));

            $completed = $goals->filter(function (PortalFitnessGoal $goal) use ($dayLogs) {
                $loggedValue = $dayLogs
                    ->filter(function (PortalFitnessLog $log) use ($goal) {
                        return $log->goal_id === $goal->id || ($log->goal_id === null && $log->habit_type === $goal->habit_type);
                    })
                    ->sum(fn (PortalFitnessLog $log) => (float) $log->value);

                return (float) $goal->target_value > 0 && $loggedValue >= (float) $goal->target_value;
            })->count();

            $total = $goals->count();

            return [
                'date' => $day->toDateString(),
                'completed_goals' => $completed,
                'total_goals' => $total,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            ];
        })->values();

        return [
            'today_date' => $today->toDateString(),
            'active_goal_count' => $goals->count(),
            'completed_goal_count' => $todayProgress->where('is_complete', true)->count(),
            'current_streak' => $this->calculateStreak($weeklyTrend),
            'total_logs_this_week' => $logs->filter(fn (PortalFitnessLog $log) => $log->logged_at && $log->logged_at->gte($today->copy()->subDays(6)))->count(),
            'motivation' => $this->motivationMessage($todayProgress),
            'today_progress' => $todayProgress,
            'weekly_trend' => $weeklyTrend,
            'latest_vitals' => $this->latestVitals($patient),
        ];
    }

    private function calculateStreak(Collection $weeklyTrend): int
    {
        $streak = 0;
        foreach ($weeklyTrend->sortByDesc('date') as $point) {
            if (($point['total_goals'] ?? 0) > 0 && ($point['completed_goals'] ?? 0) > 0) {
                $streak++;
                continue;
            }

            break;
        }

        return $streak;
    }

    private function motivationMessage(Collection $todayProgress): string
    {
        if ($todayProgress->isEmpty()) {
            return 'Create your first goal to start building a healthy routine.';
        }

        $completed = $todayProgress->where('is_complete', true)->count();
        if ($completed === $todayProgress->count()) {
            return 'Excellent work today. Every active goal is on track.';
        }

        if ($completed > 0) {
            return 'You already completed part of today’s plan. Keep the momentum going.';
        }

        return 'A small log entry is enough to start today’s streak.';
    }

    private function latestVitals(PortalPatient $patient): ?array
    {
        $vital = $patient->vitals()->with('vitalSign')->orderByDesc('vitals_date')->first();
        if (!$vital) {
            return null;
        }

        return [
            'label' => $vital->vitalSign?->vital_sign ?? 'Vital',
            'value' => (string) $vital->value,
            'unit' => $vital->vitalSign?->unit ?? '',
            'recorded_at' => optional($vital->vitals_date)->toISOString(),
        ];
    }

    private function formatGoal(PortalFitnessGoal $goal): array
    {
        return [
            'id' => $goal->id,
            'title' => $goal->title,
            'habit_type' => $goal->habit_type,
            'unit' => $goal->unit,
            'target_value' => $this->normalizeNumber($goal->target_value),
            'frequency' => $goal->frequency,
            'goal_category' => $goal->goal_category,
            'source_type' => $goal->source_type,
            'notes' => $goal->notes,
            'is_active' => (bool) $goal->is_active,
            'created_at' => optional($goal->created_at)->toISOString(),
            'updated_at' => optional($goal->updated_at)->toISOString(),
        ];
    }

    private function formatLog(PortalFitnessLog $log): array
    {
        return [
            'id' => $log->id,
            'goal_id' => $log->goal_id,
            'title' => $log->title,
            'habit_type' => $log->habit_type,
            'value' => $this->normalizeNumber($log->value),
            'unit' => $log->unit,
            'logged_at' => optional($log->logged_at)->toISOString(),
            'source_type' => $log->source_type,
            'notes' => $log->notes,
        ];
    }

    private function formatReminder(PortalFitnessReminder $reminder): array
    {
        return [
            'id' => $reminder->id,
            'goal_id' => $reminder->goal_id,
            'title' => $reminder->title,
            'habit_type' => $reminder->habit_type,
            'time_of_day' => $reminder->time_of_day,
            'days_of_week' => array_values($reminder->days_of_week ?? []),
            'message' => $reminder->message,
            'is_enabled' => (bool) $reminder->is_enabled,
            'source_type' => $reminder->source_type,
        ];
    }

    private function normalizeNumber($value): float|int
    {
        $number = (float) $value;
        return fmod($number, 1.0) === 0.0 ? (int) $number : round($number, 2);
    }
}
