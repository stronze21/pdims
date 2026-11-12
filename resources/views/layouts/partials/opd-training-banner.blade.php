<aside aria-label="OPD training guidance" data-opd-stage="{{ $stage }}"
    data-opd-patient-called="{{ $patientCalled ? 'yes' : 'no' }}"
    class="flex flex-wrap items-center justify-between gap-2 border-b border-primary/30 bg-primary/10 px-4 py-2 text-sm">
    <div>
        <p class="font-semibold">OPD Walkthrough · Synthetic patient record</p>
        <p role="status">{{ $trainingHint }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route($prescriptionSource ? 'prescriptions.opd-walkthrough.'.$prescriptionSource : 'prescriptions.opd-walkthrough') }}"
            class="btn btn-ghost btn-sm">Restart</a>
        <a href="{{ route('prescriptions.queue.controller2') }}" class="btn btn-outline btn-sm">Exit walkthrough</a>
    </div>
</aside>
