<?php

namespace App\Livewire\Pharmacy;

use App\Models\Pharmacy\DrugImportBatch;
use App\Models\Pharmacy\DrugImportMapping;
use App\Services\Pharmacy\DrugImport\DrugGroupSuggester;
use App\Services\Pharmacy\DrugImport\DrugImportCommitter;
use App\Services\Pharmacy\DrugImport\DrugImportMapper;
use App\Services\Pharmacy\DrugImport\DrugImportWorkbookReader;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class ManageDrugImport extends Component
{
    use WithFileUploads, WithPagination;

    public $upload;

    public ?string $batchId = null;

    public string $statusFilter = '';

    public string $sheetFilter = '';

    public string $issueFilter = '';

    public string $genericFilter = '';

    public string $formFilter = '';

    public string $routeFilter = '';

    public string $strengthFilter = '';

    public int $perPage = 25;

    public bool $mappingModal = false;

    public ?int $mappingRowId = null;

    public array $mappingForm = [];

    public array $mappingContext = [];

    public array $groupSuggestions = [];

    public array $autoDetectedFields = [];

    public array $newGroupRecommendation = [];

    public bool $groupDrawer = false;

    public array $newGroupForm = [];

    public bool $historyDrawer = false;

    public string $historySearch = '';

    public string $historyStatus = '';

    protected $queryString = ['batchId' => ['as' => 'batch', 'except' => null]];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('view-settings'), 403);
        $this->resetMappingForm();
        if ($this->batchId && ! DrugImportBatch::query()->whereKey($this->batchId)->exists()) {
            $this->batchId = null;
        }
    }

    public function updated($property): void
    {
        if (str_ends_with($property, 'Filter') || $property === 'perPage') {
            $this->resetPage();
        }
        if (in_array($property, ['historySearch', 'historyStatus'], true)) {
            $this->resetPage('historyPage');
        }
        if ($property === 'newGroupForm.dmcode') {
            $this->newGroupForm['dms1key'] = $this->newGroupForm['dms2key'] = $this->newGroupForm['dms3key'] = $this->newGroupForm['dms4key'] = '';
        } elseif ($property === 'newGroupForm.dms1key') {
            $this->newGroupForm['dms2key'] = $this->newGroupForm['dms3key'] = $this->newGroupForm['dms4key'] = '';
        } elseif ($property === 'newGroupForm.dms2key') {
            $this->newGroupForm['dms3key'] = $this->newGroupForm['dms4key'] = '';
        } elseif ($property === 'newGroupForm.dms3key') {
            $this->newGroupForm['dms4key'] = '';
        }
    }

    public function uploadWorkbook(DrugImportWorkbookReader $reader, DrugImportMapper $mapper): void
    {
        $this->validate(['upload' => ['required', 'file', 'mimes:xlsx', 'max:10240']]);
        $batch = null;
        $storedPath = null;
        try {
            $hash = hash_file('sha256', $this->upload->getRealPath());
            $storedPath = $this->upload->storeAs('drug-imports', Str::uuid().'.xlsx', 'local');
            $parsed = $reader->read(Storage::disk('local')->path($storedPath));
            $batch = DrugImportBatch::query()->create([
                'source_filename' => $this->upload->getClientOriginalName(), 'source_hash' => $hash,
                'source_adapter' => $parsed['adapter'], 'stored_path' => $storedPath, 'status' => 'validating',
                'defaults_json' => ['pndf_source' => 'sheet', 'rxot' => 'RXX', 'status' => 'A', 'warnings' => $parsed['warnings']],
                'uploaded_by' => $this->auditUser(),
            ]);
            $mapper->stage($batch, $parsed['rows']);
            $batch->update(['status' => 'review']);
            $this->batchId = $batch->id;
            $this->upload = null;
            session()->flash('success', "Workbook staged with {$batch->fresh()->total_count} candidate records.");
        } catch (Throwable $e) {
            report($e);
            if ($batch) {
                $batch->rows()->delete();
                $batch->delete();
            }
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            $this->addError('upload', 'Unable to stage the workbook: '.$e->getMessage());
        }
    }

    public function openImportHistory(): void
    {
        $this->resetPage('historyPage');
        $this->historyDrawer = true;
    }

    public function openImportBatch(string $batchId): void
    {
        $batch = DrugImportBatch::query()->findOrFail($batchId);
        $this->batchId = $batch->id;
        $this->historyDrawer = false;
        $this->mappingModal = false;
        $this->groupDrawer = false;
        $this->clearFilters();
    }

    public function editMapping(int $rowId, DrugImportMapper $mapper, DrugGroupSuggester $suggester): void
    {
        $row = $this->currentBatch()->rows()->findOrFail($rowId);
        $before = $row->only(['dmdnost', 'strecode', 'formcode', 'rtecode']);
        $row = $mapper->revalidate($row);
        $labels = ['dmdnost' => 'dosage number', 'strecode' => 'strength', 'formcode' => 'dosage form', 'rtecode' => 'route'];
        $this->autoDetectedFields = collect($labels)
            ->filter(fn ($label, $field) => blank($before[$field] ?? null) && filled($row->{$field}))
            ->values()->all();
        $this->mappingRowId = $row->id;
        $this->mappingForm = [
            'grpcode' => trim((string) $row->grpcode), 'dmdnost' => trim((string) $row->dmdnost),
            'strecode' => trim((string) $row->strecode), 'formcode' => trim((string) $row->formcode),
            'rtecode' => trim((string) $row->rtecode), 'pndf' => $row->pndf ?: 'Y',
            'rxot' => $row->rxot ?: 'RXX', 'record_status' => $row->record_status ?: 'A',
        ];
        $this->mappingContext = [
            'source' => $row->source_sheet.' #'.$row->source_row,
            'generic' => $row->generic_name,
            'strength' => $row->strength_text,
            'form' => $row->form_text,
            'route' => $row->route_text,
        ];
        $this->groupSuggestions = $suggester->suggest($row->generic_name);
        $this->newGroupRecommendation = $suggester->recommendNew($row->generic_name, $row->raw_json ?? []);
        $this->mappingModal = true;
    }

    public function selectSuggestedGroup(int $index): void
    {
        $suggestion = $this->groupSuggestions[$index] ?? null;
        if ($suggestion) {
            $this->mappingForm['grpcode'] = $suggestion['id'];
        }
    }

    public function openRecommendedGroup(): void
    {
        abort_unless($this->mappingModal && ($this->newGroupRecommendation['can_create_group'] ?? false), 404);
        $this->resetValidation();
        $this->newGroupForm = [
            'grpcode' => $this->nextGroupCode(),
            'gencode' => data_get($this->newGroupRecommendation, 'generic.id', ''),
            'generic_name' => data_get($this->newGroupRecommendation, 'generic.name', ''),
            'dmcode' => data_get($this->newGroupRecommendation, 'levels.1.id', ''),
            'dms1key' => data_get($this->newGroupRecommendation, 'levels.2.id', ''),
            'dms2key' => data_get($this->newGroupRecommendation, 'levels.3.id', ''),
            'dms3key' => data_get($this->newGroupRecommendation, 'levels.4.id', ''),
            'dms4key' => '',
        ];
        $this->groupDrawer = true;
    }

    public function saveRecommendedGroup(DrugImportMapper $mapper): void
    {
        $this->validate([
            'newGroupForm.grpcode' => ['required', 'string', 'max:10', Rule::unique('hospital.hdruggrp', 'grpcode')],
            'newGroupForm.gencode' => ['required', Rule::exists('hospital.hgen', 'gencode')],
            'newGroupForm.dmcode' => ['required', Rule::exists('hospital.dmmajor', 'dmcode')],
            'newGroupForm.dms1key' => ['nullable', Rule::exists('hospital.dmsub1', 'dms1key')],
            'newGroupForm.dms2key' => ['nullable', Rule::exists('hospital.dmsub2', 'dms2key')],
            'newGroupForm.dms3key' => ['nullable', Rule::exists('hospital.dmsub3', 'dms3key')],
            'newGroupForm.dms4key' => ['nullable', Rule::exists('hospital.dmsub4', 'dms4key')],
        ]);

        $db = DB::connection('hospital');
        $form = collect($this->newGroupForm)->map(fn ($value) => trim((string) $value))->all();
        $this->validateClassificationChain($form);
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        try {
            $db->transaction(function () use ($db, $form) {
                if ($db->table('hdruggrp')->where('grpcode', $form['grpcode'])->lockForUpdate()->exists()) {
                    throw new \RuntimeException('The suggested PNDF group code is already in use. Close and reopen the drawer for a new code.');
                }
                $db->table('hdruggrp')->insert([
                    'grpcode' => $form['grpcode'], 'gencode' => $form['gencode'], 'dmcode' => $form['dmcode'],
                    'dms1key' => $form['dms1key'] ?: null, 'dms2key' => $form['dms2key'] ?: null,
                    'dms3key' => $form['dms3key'] ?: null, 'dms4key' => $form['dms4key'] ?: null,
                    'grpstat' => 'A', 'grplock' => 'N', 'grpupsw' => 'T', 'grpdtmd' => Carbon::now(),
                ]);
            }, 3);
        } catch (Throwable $e) {
            report($e);
            $this->addError('newGroupForm.grpcode', $e->getMessage());

            return;
        }

        $row = $this->currentBatch()->rows()->findOrFail($this->mappingRowId);
        $row->update(['grpcode' => $form['grpcode']]);
        DrugImportMapping::query()->updateOrCreate(
            ['mapping_type' => 'group', 'source_normalized' => $this->normalize($row->generic_name)],
            ['target_code' => $form['grpcode'], 'approved_by' => $this->auditUser()]
        );
        $row = $mapper->revalidate($row);
        $this->mappingForm['grpcode'] = $form['grpcode'];
        $this->groupSuggestions = app(DrugGroupSuggester::class)->suggest($row->generic_name);
        $this->groupDrawer = false;
        session()->flash('success', 'The PNDF group was created, selected, and the row was revalidated.');
    }

    public function saveMapping(DrugImportMapper $mapper): void
    {
        $row = $this->currentBatch()->rows()->findOrFail($this->mappingRowId);
        $this->validate([
            'mappingForm.grpcode' => ['required', Rule::exists('hospital.hdruggrp', 'grpcode')],
            'mappingForm.dmdnost' => ['required', 'numeric', 'min:0'],
            'mappingForm.strecode' => ['required', Rule::exists('hospital.hstre', 'strecode')],
            'mappingForm.formcode' => ['required', Rule::exists('hospital.hform', 'formcode')],
            'mappingForm.rtecode' => ['required', Rule::exists('hospital.hroute', 'rtecode')],
            'mappingForm.pndf' => ['required', Rule::in(['Y', 'N'])],
            'mappingForm.rxot' => ['required', Rule::in(['RXX', 'OTC'])],
            'mappingForm.record_status' => ['required', Rule::in(['A', 'I'])],
        ]);

        $row->fill($this->mappingForm)->save();
        $gencode = DB::connection('hospital')->table('hdruggrp')->where('grpcode', $this->mappingForm['grpcode'])->value('gencode');
        foreach ([
            ['generic', $row->generic_name, $gencode], ['group', $row->generic_name, $this->mappingForm['grpcode']],
            ['strength', $this->strengthUnit($row->strength_text), $this->mappingForm['strecode']],
            ['form', $row->form_text, $this->mappingForm['formcode']], ['route', $row->route_text, $this->mappingForm['rtecode']],
        ] as [$type, $source, $target]) {
            if (filled($source) && filled($target)) {
                DrugImportMapping::query()->updateOrCreate(
                    ['mapping_type' => $type, 'source_normalized' => $this->normalize($source)],
                    ['target_code' => $target, 'approved_by' => $this->auditUser()]
                );
            }
        }
        $mapper->revalidate($row);
        $this->mappingModal = false;
        session()->flash('success', 'Mapping saved and the row was revalidated.');
    }

    public function toggleExcluded(int $rowId, DrugImportMapper $mapper): void
    {
        $row = $this->currentBatch()->rows()->findOrFail($rowId);
        if ($row->row_status === 'imported') {
            return;
        }
        if ($row->row_status === 'excluded') {
            $row->update(['action' => 'import']);
            $mapper->revalidate($row);
        } else {
            $row->update(['action' => 'exclude', 'row_status' => 'excluded']);
            $mapper->refreshBatch($row->batch);
        }
    }

    public function excludeUnresolved(DrugImportMapper $mapper): void
    {
        $this->currentBatch()->rows()->where('row_status', 'needs_mapping')->update(['action' => 'exclude', 'row_status' => 'excluded']);
        $mapper->refreshBatch($this->currentBatch());
    }

    public function revalidateBatch(DrugImportMapper $mapper): void
    {
        $mapper->revalidateBatch($this->currentBatch());
        session()->flash('success', 'All pending rows were revalidated.');
    }

    public function commitReady(DrugImportCommitter $committer): void
    {
        try {
            $result = $committer->commit($this->currentBatch(), $this->auditUser());
            session()->flash('success', "Imported {$result['imported']} records; {$result['duplicates']} concurrent duplicates were skipped.");
        } catch (Throwable $e) {
            report($e);
            session()->flash('error', 'Import was rolled back: '.$e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        return response()->streamDownload(function () {
            $book = new Spreadsheet;
            $sheet = $book->getActiveSheet()->setTitle('Drug Import');
            $headers = ['Generic Name', 'Group Code', 'Dosage Number', 'Strength', 'Dosage Form', 'Route', 'PNDF', 'RX/OTC', 'Status', 'Size Number', 'Size Unit', 'ATC Code', 'Technical Specification'];
            $sheet->fromArray($headers, null, 'A1');
            $sheet->fromArray(['Paracetamol', '', 500, 'mg', 'Tablet', 'Oral', 'Y', 'RXX', 'A', '', '', 'N02BE01', '500 mg tablet'], null, 'A2');
            $sheet->getStyle('A1:M1')->getFont()->setBold(true);
            $sheet->freezePane('A2');
            foreach (range('A', 'M') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, 'PDIMS_Drug_Import_Template.xlsx');
    }

    public function exportResults()
    {
        $batch = $this->currentBatch();

        return response()->streamDownload(function () use ($batch) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Sheet', 'Row', 'Generic', 'Strength', 'Form', 'Route', 'Status', 'Issues', 'Existing Code', 'Imported Code']);
            $batch->rows()->orderBy('id')->chunk(250, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [$row->source_sheet, $row->source_row, $row->generic_name, $row->strength_text,
                        $row->form_text, $row->route_text, $row->row_status, collect($row->issues_json)->pluck('message')->implode('; '),
                        $row->existing_dmdcomb, $row->result_dmdcomb]);
                }
            });
            fclose($out);
        }, 'drug-import-'.$batch->id.'.csv');
    }

    public function clearFilters(): void
    {
        $this->reset(['statusFilter', 'sheetFilter', 'issueFilter', 'genericFilter', 'formFilter', 'routeFilter', 'strengthFilter']);
        $this->resetPage();
    }

    public function render()
    {
        $batch = $this->batchId ? DrugImportBatch::query()->find($this->batchId) : null;
        $rows = null;
        if ($batch) {
            $query = $batch->rows()->orderBy('id');
            if ($this->statusFilter) {
                $query->where('row_status', $this->statusFilter);
            }
            if ($this->sheetFilter) {
                $query->where('source_sheet', $this->sheetFilter);
            }
            if ($this->issueFilter) {
                $query->where('issue_code', $this->issueFilter);
            }
            foreach (['generic_name' => 'genericFilter', 'form_text' => 'formFilter', 'route_text' => 'routeFilter', 'strength_text' => 'strengthFilter'] as $column => $property) {
                if ($this->{$property}) {
                    $query->where($column, 'like', '%'.$this->{$property}.'%');
                }
            }
            $rows = $query->paginate($this->perPage);
        }

        $db = DB::connection('hospital');
        $optionList = fn (string $table, string $code, string $description) => $db->table($table)
            ->orderBy($description)->get([$code.' as id', $description.' as description'])
            ->map(fn ($row) => ['id' => trim((string) $row->id), 'name' => trim((string) $row->description)]);
        $classOptions = fn (string $table, string $code, string $description, ?string $parent = null, ?string $parentValue = null) => ! $this->groupDrawer || ($parent && blank($parentValue))
            ? collect()
            : $db->table($table)->when($parent, fn ($query) => $query->where($parent, $parentValue))
                ->orderBy($description)->get([$code.' as id', $description.' as description'])
                ->map(fn ($row) => ['id' => trim((string) $row->id), 'name' => trim((string) $row->description)]);
        $history = $this->historyDrawer
            ? DrugImportBatch::query()
                ->when($this->historySearch, fn ($query) => $query->where('source_filename', 'like', '%'.$this->historySearch.'%'))
                ->when($this->historyStatus, fn ($query) => $query->where('status', $this->historyStatus))
                ->latest('created_at')->paginate(15, ['*'], 'historyPage')
            : null;

        return view('livewire.pharmacy.manage-drug-import', [
            'batch' => $batch, 'rows' => $rows,
            'groups' => $this->mappingModal ? app(DrugGroupSuggester::class)->options() : [],
            'strengths' => $this->mappingModal ? $optionList('hstre', 'strecode', 'stredesc') : collect(),
            'forms' => $this->mappingModal ? $optionList('hform', 'formcode', 'formdesc') : collect(),
            'routes' => $this->mappingModal ? $optionList('hroute', 'rtecode', 'rtedesc') : collect(),
            'majorClasses' => $classOptions('dmmajor', 'dmcode', 'dmdesc'),
            'subClass1' => $classOptions('dmsub1', 'dms1key', 'dms1desc', 'dmcode', $this->newGroupForm['dmcode'] ?? null),
            'subClass2' => $classOptions('dmsub2', 'dms2key', 'dms2desc', 'dms1key', $this->newGroupForm['dms1key'] ?? null),
            'subClass3' => $classOptions('dmsub3', 'dms3key', 'dms3desc', 'dms2key', $this->newGroupForm['dms2key'] ?? null),
            'subClass4' => $classOptions('dmsub4', 'dms4key', 'dms4desc', 'dms3key', $this->newGroupForm['dms3key'] ?? null),
            'history' => $history,
            'historyStatuses' => $this->historyDrawer ? DrugImportBatch::query()->distinct()->orderBy('status')->pluck('status') : collect(),
            'sheets' => $batch ? $batch->rows()->distinct()->orderBy('source_sheet')->pluck('source_sheet') : collect(),
            'issues' => $batch ? $batch->rows()->whereNotNull('issue_code')->distinct()->orderBy('issue_code')->pluck('issue_code') : collect(),
        ]);
    }

    private function currentBatch(): DrugImportBatch
    {
        return DrugImportBatch::query()->findOrFail($this->batchId);
    }

    private function auditUser(): ?string
    {
        $value = auth()->user()?->employeeid ?: auth()->user()?->name;

        return $value ? mb_substr(trim((string) $value), 0, 50) : null;
    }

    private function normalize($value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $value)));
    }

    private function strengthUnit(?string $value): ?string
    {
        if (! preg_match('/^[0-9]+(?:[.,][0-9]+)?\s*(.+)$/u', trim((string) $value), $match)) {
            return null;
        }

        return preg_replace('/\s*\(as\s+[^)]+\)\s*$/iu', '', trim($match[1]));
    }

    private function resetMappingForm(): void
    {
        $this->mappingForm = ['grpcode' => '', 'dmdnost' => '', 'strecode' => '', 'formcode' => '', 'rtecode' => '', 'pndf' => 'Y', 'rxot' => 'RXX', 'record_status' => 'A'];
        $this->autoDetectedFields = [];
        $this->newGroupRecommendation = [];
        $this->newGroupForm = [];
    }

    private function nextGroupCode(): string
    {
        $row = DB::connection('hospital')->selectOne(
            "SELECT MAX(CAST(grpcode AS bigint)) AS max_code FROM hdruggrp WHERE ISNUMERIC(grpcode) = 1 AND grpcode LIKE '0%'"
        );

        return str_pad((string) (((int) ($row->max_code ?? 0)) + 1), 10, '0', STR_PAD_LEFT);
    }

    private function validateClassificationChain(array $form): void
    {
        foreach ([
            ['field' => 'dms1key', 'table' => 'dmsub1', 'key' => 'dms1key', 'parent' => 'dmcode'],
            ['field' => 'dms2key', 'table' => 'dmsub2', 'key' => 'dms2key', 'parent' => 'dms1key'],
            ['field' => 'dms3key', 'table' => 'dmsub3', 'key' => 'dms3key', 'parent' => 'dms2key'],
            ['field' => 'dms4key', 'table' => 'dmsub4', 'key' => 'dms4key', 'parent' => 'dms3key'],
        ] as $level) {
            if ($form[$level['field']] !== '' && ! DB::connection('hospital')->table($level['table'])
                ->where($level['key'], $form[$level['field']])->where($level['parent'], $form[$level['parent']])->exists()) {
                $this->addError('newGroupForm.'.$level['field'], 'This classification does not belong to the selected parent.');
            }
        }
    }
}
