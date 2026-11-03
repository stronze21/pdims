<?php

namespace App\Livewire\Pharmacy\Reports;

use App\Services\Pharmacy\Reports\PharmacyReportService;
use App\Services\Pharmacy\Reports\EquivalentReportGrouping;
use App\Services\Pharmacy\Reports\ReportDefinition;
use App\Services\Pharmacy\Reports\ReportFilters;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Reports extends Component
{
    use WithPagination;

    #[Locked]
    public string $report = '';

    public array $filters = [];

    #[Locked]
    public array $applied = [];

    public function mount(string $report = ''): void
    {
        abort_unless(auth()->user()?->can('view-reports'), 403);
        abort_unless($report === '' || isset(ReportDefinition::TITLES[$report]), 404);
        $this->report = $report;
        $this->filters = array_intersect_key(request()->query(), ReportFilters::defaults(null))
            + ReportFilters::defaults(auth()->user()->pharm_location_id === null ? null : (string) auth()->user()->pharm_location_id);
        $this->applied = $this->filters;
    }

    public function apply(): void
    {
        $this->resetValidation();
        try {
            $this->applied = app(PharmacyReportService::class)->filters($this->filters);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Nested keys connect server validation to the draft filter controls.
            throw \Illuminate\Validation\ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn ($messages, $key) => ['filters.'.$key => $messages])->all()
            );
        } catch (\Illuminate\Database\QueryException $e) {
            $this->addError('filters', 'Filters could not be loaded. Retry, or contact your application administrator.');

            return;
        }
        $this->resetPage();
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('view-reports'), 403);
        $titles = ReportDefinition::TITLES;
        $loadError = null;
        $data = compact('titles', 'loadError');
        if ($this->report !== '') {
            try {
                $service = app(PharmacyReportService::class);
                $options = $service->options();
                if (($this->applied['location'] ?? '') === '') {
                    if (! auth()->user()->can('filter-stocks-location') || $options['locations']->isEmpty()) {
                        $data['loadError'] = 'No pharmacy location is available for your account. Contact your application administrator to assign a location.';

                        return view('livewire.pharmacy.reports.reports', $data)->layout('layouts.app', ['title' => $titles[$this->report]]);
                    }
                    $this->applied['location'] = $this->filters['location'] = (string) $options['locations']->first()->id;
                }
                $filters = $service->filters($this->applied, $options);
                $query = $service->query($this->report, $filters);
                $totals = $service->totals($this->report, $query);
                $records = (clone $query)->paginate(50);
                $records->through(fn ($row) => $service->row($this->report, $row));
                $metadata = $service->metadata($this->report, $filters, $options);
                $grouped = EquivalentReportGrouping::enabled($filters);
                $columns = ReportDefinition::columns($this->report, $grouped);
                $notes = ReportDefinition::notes($this->report, $grouped);
                $data += compact('options', 'filters', 'totals', 'records', 'metadata', 'columns', 'notes');
            } catch (\Illuminate\Database\QueryException $e) {
                $data['loadError'] = 'This report could not be loaded. Retry, or contact your application administrator if the problem continues. No report totals are available.';
            }
        }

        return view('livewire.pharmacy.reports.reports', $data)
            ->layout('layouts.app', ['title' => $titles[$this->report] ?? 'Pharmacy reports']);
    }
}
