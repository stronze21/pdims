<?php

namespace App\Livewire\Pharmacy\Settings;

use App\Models\PrescriptionReorderLog;
use App\Services\Pharmacy\PrescriptionReactivationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

#[Layout('layouts.app')]
#[Title('Prescription Reorder Settings')]
class PrescriptionReorderSettings extends Component
{
    use Toast;

    public bool $showRerunWarningModal = false;
    public ?array $lastManualRun = null;

    protected PrescriptionReactivationService $reactivationService;

    public function boot(PrescriptionReactivationService $reactivationService): void
    {
        $this->reactivationService = $reactivationService;
    }

    public function triggerManualReorder(): void
    {
        if ($this->reactivationService->hasCompletedRunToday(now('Asia/Manila'))) {
            $this->showRerunWarningModal = true;
            return;
        }

        $this->runManualReorder(false);
    }

    public function confirmManualRerun(): void
    {
        $this->showRerunWarningModal = false;
        $this->runManualReorder(true);
    }

    private function runManualReorder(bool $isRerun): void
    {
        $notes = $isRerun
            ? 'Manual reorder triggered from settings after a run already occurred today.'
            : 'Manual reorder triggered from settings.';

        $run = $this->reactivationService->runReorder(
            now('Asia/Manila'),
            false,
            'manual',
            auth()->user()?->name ?? (string) auth()->id(),
            $notes
        );

        $this->lastManualRun = $run;

        $message = $run['count'] > 0
            ? "Manual reorder completed. {$run['count']} item(s) reordered."
            : 'Manual reorder completed. No eligible items were found.';

        $this->success($message);
    }

    public function render()
    {
        $now = now('Asia/Manila');
        $todayRun = $this->reactivationService->todayRunLog($now);
        $latestRun = $this->reactivationService->latestRun();
        $runLogs = $this->reactivationService->runLogs();
        $recentItems = PrescriptionReorderLog::query()
            ->select([
                'prescription_reorder_logs.*',
                'pd.presc_id',
                'pd.dmdcomb',
                'pd.dmdctr',
                'pd.remark',
                'pd.duration',
                'pd.frequency',
                'dm.drug_concat',
                'enctr.hpercode',
                'enctr.toecode as encounter_type',
                'pat.patlast',
                'pat.patfirst',
                'pat.patmiddle',
            ])
            ->leftJoin('webapp.dbo.prescription_data as pd', 'pd.id', '=', 'prescription_reorder_logs.prescription_data_id')
            ->leftJoin('webapp.dbo.prescription as rx', 'rx.id', '=', 'pd.presc_id')
            ->leftJoin('hospital.dbo.henctr as enctr', 'enctr.enccode', '=', 'rx.enccode')
            ->leftJoin('hospital.dbo.hperson as pat', 'pat.hpercode', '=', 'enctr.hpercode')
            ->leftJoin('hospital.dbo.hdmhdr as dm', function ($join) {
                $join->on('dm.dmdcomb', '=', 'pd.dmdcomb')
                    ->on('dm.dmdctr', '=', 'pd.dmdctr');
            })
            ->orderByDesc('prescription_reorder_logs.reordered_at')
            ->limit(20)
            ->get()
            ->map(function ($row) {
                $patientName = collect([
                    trim((string) ($row->patlast ?? '')),
                    trim((string) ($row->patfirst ?? '')),
                    trim((string) ($row->patmiddle ?? '')),
                ])->filter()->values();

                $label = match ($row->encounter_type ?? null) {
                    'OPD' => 'Out-Patient',
                    'ER' => 'Emergency Room',
                    'ADM' => 'Admission',
                    'ERADM' => 'ER to Admission',
                    'OPDAD' => 'OPD to Admission',
                    'WALKN' => 'Walk-In',
                    default => $row->encounter_type,
                };

                return (object) [
                    'prescription_data_id' => $row->prescription_data_id,
                    'prescription_id' => $row->prescription_id,
                    'source' => $row->source,
                    'source_label' => match ($row->source) {
                        'auto' => 'Automatic',
                        'manual' => 'Manual',
                        default => ucfirst((string) $row->source),
                    },
                    'reordered_at' => $row->reordered_at,
                    'performed_by' => $row->performed_by,
                    'drug_concat' => $row->drug_concat,
                    'patient_name' => $patientName->isEmpty()
                        ? null
                        : trim($patientName->shift() . ($patientName->isEmpty() ? '' : ', ' . $patientName->implode(' '))),
                    'hpercode' => $row->hpercode,
                    'encounter_type_label' => $label,
                    'notes' => $row->notes,
                ];
            });

        $nextScheduledRun = $now->copy()->setTime(7, 0);
        if ($now->greaterThanOrEqualTo($nextScheduledRun)) {
            $nextScheduledRun->addDay();
        }

        return view('livewire.pharmacy.settings.prescription-reorder-settings', [
            'todayRun' => $todayRun,
            'latestRun' => $latestRun,
            'runLogs' => $runLogs,
            'recentItems' => $recentItems,
            'nextScheduledRun' => $nextScheduledRun,
        ]);
    }
}
