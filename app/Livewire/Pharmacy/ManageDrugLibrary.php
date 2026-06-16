<?php

namespace App\Livewire\Pharmacy;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class ManageDrugLibrary extends Component
{
    use WithPagination;

    public string $tab = 'drugs';
    public string $search = '';
    public int $perPage = 15;

    public bool $drugModal = false;
    public bool $lookupModal = false;
    public bool $groupModal = false;
    public bool $subModal = false;

    public ?string $editingDmdcomb = null;
    public ?string $editingDmdctr = null;
    public ?string $editingSubDmdcomb = null;
    public ?string $editingSubDmdctr = null;
    public ?string $editingSubKey = null;
    public ?string $lookupType = null;
    public ?string $lookupOriginalCode = null;
    public ?string $editingGrpcode = null;

    public array $drugForm = [];
    public array $lookupForm = [];
    public array $groupForm = [];
    public array $subForm = [];

    protected $queryString = [
        'tab' => ['except' => 'drugs'],
        'search' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->resetDrugForm();
        $this->resetLookupForm();
        $this->resetGroupForm();
        $this->resetSubForm();
    }

    public function updatedTab(): void
    {
        $this->resetPage();
        $this->search = '';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedGroupFormDmcode(): void
    {
        $this->groupForm['dms1key'] = '';
        $this->groupForm['dms2key'] = '';
        $this->groupForm['dms3key'] = '';
        $this->groupForm['dms4key'] = '';
    }

    public function updatedGroupFormDms1key(): void
    {
        $this->groupForm['dms2key'] = '';
        $this->groupForm['dms3key'] = '';
        $this->groupForm['dms4key'] = '';
    }

    public function updatedGroupFormDms2key(): void
    {
        $this->groupForm['dms3key'] = '';
        $this->groupForm['dms4key'] = '';
    }

    public function updatedGroupFormDms3key(): void
    {
        $this->groupForm['dms4key'] = '';
    }

    public function createDrug(): void
    {
        $this->editingDmdcomb = null;
        $this->editingDmdctr = null;
        $this->resetDrugForm();
        $this->drugModal = true;
    }

    public function editDrug(string $dmdcomb, string $dmdctr): void
    {
        $drug = $this->hospital()->table('hdmhdr')->where(compact('dmdcomb', 'dmdctr'))->first();

        abort_if(! $drug, 404);

        $sub = $this->hospital()->table('hdmhdrsub')
            ->where(compact('dmdcomb', 'dmdctr'))
            ->orderBy('dmhdrsub')
            ->first();

        $this->editingDmdcomb = $dmdcomb;
        $this->editingDmdctr = $dmdctr;

        $this->drugForm = [
            'grpcode' => trim((string) $drug->grpcode),
            'dmdnost' => trim((string) $drug->dmdnost),
            'strecode' => trim((string) $drug->strecode),
            'formcode' => trim((string) $drug->formcode),
            'rtecode' => trim((string) $drug->rtecode),
            'brandname' => trim((string) $drug->brandname),
            'dmdpndf' => trim((string) $drug->dmdpndf) ?: 'Y',
            'dmdrxot' => trim((string) $drug->dmdrxot),
            'dmdstat' => trim((string) $drug->dmdstat) ?: 'A',
            'dmdrem' => trim((string) $drug->dmdrem),
            'barcode' => trim((string) $drug->barcode),
            'dmhdrsub' => $sub ? trim((string) $sub->dmhdrsub) : '',
            'sub_barcode' => $sub ? trim((string) $sub->barcode) : '',
            'begbal' => $sub ? (string) ((int) $sub->begbal) : '0',
            'stockbal' => $sub ? (string) ((int) $sub->stockbal) : '0',
            'reorder_level' => $sub ? (string) ((int) $sub->reorder_level) : '0',
            'rpoint' => $sub ? (string) ((int) $sub->rpoint) : '0',
        ];

        $this->drugModal = true;
    }

    public function saveDrug(): void
    {
        $this->validate([
            'drugForm.grpcode' => ['required', 'string', 'max:10'],
            'drugForm.dmdnost' => ['required', 'string', 'max:100'],
            'drugForm.strecode' => ['required', 'string', 'max:5'],
            'drugForm.formcode' => ['required', 'string', 'max:5'],
            'drugForm.rtecode' => ['nullable', 'string', 'max:5'],
            'drugForm.brandname' => ['nullable', 'string', 'max:255'],
            'drugForm.dmdpndf' => ['required', Rule::in(['Y', 'N'])],
            'drugForm.dmdrxot' => ['nullable', 'string', 'max:1'],
            'drugForm.dmdstat' => ['required', Rule::in(['A', 'I'])],
            'drugForm.dmdrem' => ['nullable', 'string', 'max:255'],
            'drugForm.barcode' => ['nullable', 'string', 'max:20'],
            'drugForm.dmhdrsub' => ['required', 'string', 'max:6'],
            'drugForm.sub_barcode' => ['nullable', 'string', 'max:20'],
            'drugForm.begbal' => ['nullable', 'integer', 'min:0'],
            'drugForm.stockbal' => ['nullable', 'integer', 'min:0'],
            'drugForm.reorder_level' => ['nullable', 'integer', 'min:0'],
            'drugForm.rpoint' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $this->hospital()->transaction(function () {
                $dmdcomb = $this->editingDmdcomb ?: $this->nextNumericCode('hdmhdr', 'dmdcomb', 12);
                $dmdctr = $this->editingDmdctr ?: '1';
                $now = Carbon::now();

                $drugData = [
                    'dmdcomb' => $dmdcomb,
                    'dmdctr' => $dmdctr,
                    'grpcode' => $this->drugForm['grpcode'],
                    'dmdnost' => $this->drugForm['dmdnost'],
                    'strecode' => $this->drugForm['strecode'],
                    'formcode' => $this->drugForm['formcode'],
                    'rtecode' => $this->blankToNull($this->drugForm['rtecode']),
                    'brandname' => $this->blankToNull($this->drugForm['brandname']),
                    'dmdpndf' => $this->drugForm['dmdpndf'],
                    'dmdrxot' => $this->blankToNull($this->drugForm['dmdrxot']),
                    'dmdstat' => $this->drugForm['dmdstat'],
                    'dmdrem' => $this->blankToNull($this->drugForm['dmdrem']),
                    'barcode' => $this->blankToNull($this->drugForm['barcode']),
                    'drug_concat' => $this->buildDrugConcat(),
                    'dmdlock' => 'N',
                    'dmdupsw' => 'T',
                    'dmddtmd' => $now,
                    'lot_no' => '',
                ];

                $this->hospital()->table('hdmhdr')->updateOrInsert(
                    ['dmdcomb' => $dmdcomb, 'dmdctr' => $dmdctr],
                    $drugData
                );

                $subData = [
                    'dmdcomb' => $dmdcomb,
                    'dmdctr' => $dmdctr,
                    'dmhdrsub' => $this->drugForm['dmhdrsub'],
                    'barcode' => $this->blankToNull($this->drugForm['sub_barcode']),
                    'begbal' => (int) ($this->drugForm['begbal'] ?: 0),
                    'stockbal' => (int) ($this->drugForm['stockbal'] ?: 0),
                    'baldteasof' => $now,
                    'datemod' => $now,
                    'entryby' => optional(auth()->user())->employeeid ?: optional(auth()->user())->name,
                    'rpoint' => (int) ($this->drugForm['rpoint'] ?: 0),
                    'reorder_level' => (int) ($this->drugForm['reorder_level'] ?: 0),
                    'statusMed' => $this->drugForm['dmdstat'],
                ];

                $this->hospital()->table('hdmhdrsub')->updateOrInsert(
                    ['dmdcomb' => $dmdcomb, 'dmdctr' => $dmdctr, 'dmhdrsub' => $this->drugForm['dmhdrsub']],
                    $subData
                );
            });

            session()->flash('success', 'Drug and medicine item saved successfully.');
            $this->drugModal = false;
        } catch (Throwable $e) {
            report($e);
            session()->flash('error', 'Unable to save drug item: ' . $e->getMessage());
        }
    }

    public function toggleDrugStatus(string $dmdcomb, string $dmdctr, string $currentStatus): void
    {
        $status = $this->nextStatus($currentStatus);

        $this->hospital()->transaction(function () use ($dmdcomb, $dmdctr, $status) {
            $this->hospital()->table('hdmhdr')
                ->where('dmdcomb', $dmdcomb)
                ->where('dmdctr', $dmdctr)
                ->update([
                    'dmdstat' => $status,
                    'dmdupsw' => 'T',
                    'dmddtmd' => Carbon::now(),
                ]);

            $this->hospital()->table('hdmhdrsub')
                ->where('dmdcomb', $dmdcomb)
                ->where('dmdctr', $dmdctr)
                ->update([
                    'statusMed' => $status,
                    'datemod' => Carbon::now(),
                ]);
        });

        session()->flash('success', 'Drug status updated successfully.');
    }

    public function createLookup(string $type): void
    {
        $this->lookupType = $type;
        $this->lookupOriginalCode = null;
        $this->resetLookupForm();
        $this->lookupModal = true;
    }

    public function editLookup(string $type, string $code): void
    {
        $config = $this->lookupConfig($type);
        $record = $this->hospital()->table($config['table'])->where($config['code'], $code)->first();

        abort_if(! $record, 404);

        $this->lookupType = $type;
        $this->lookupOriginalCode = $code;
        $this->lookupForm = [
            'code' => trim((string) $record->{$config['code']}),
            'description' => trim((string) $record->{$config['description']}),
            'status' => trim((string) $record->{$config['status']}) ?: 'A',
        ];
        $this->lookupModal = true;
    }

    public function saveLookup(): void
    {
        $config = $this->lookupConfig($this->lookupType);

        $this->validate([
            'lookupForm.code' => ['required', 'string', 'max:5'],
            'lookupForm.description' => ['required', 'string', 'max:255'],
            'lookupForm.status' => ['required', Rule::in(['A', 'I'])],
        ]);

        $code = strtoupper(trim($this->lookupForm['code']));
        $exists = $this->hospital()->table($config['table'])
            ->where($config['code'], $code)
            ->when($this->lookupOriginalCode, fn ($query) => $query->where($config['code'], '<>', $this->lookupOriginalCode))
            ->exists();

        if ($exists) {
            $this->addError('lookupForm.code', 'This code already exists.');
            return;
        }

        $data = [
            $config['code'] => $code,
            $config['description'] => trim($this->lookupForm['description']),
            $config['status'] => $this->lookupForm['status'],
            $config['lock'] => 'N',
            'updsw' => 'T',
            'datemod' => Carbon::now(),
        ];

        if (in_array($this->lookupType, ['generics', 'forms', 'strengths'], true)) {
            $data['entryby'] = optional(auth()->user())->employeeid ?: optional(auth()->user())->name;
        }

        $this->hospital()->table($config['table'])->updateOrInsert(
            [$config['code'] => $this->lookupOriginalCode ?: $code],
            $data
        );

        session()->flash('success', $config['label'] . ' saved successfully.');
        $this->lookupModal = false;
    }

    public function toggleLookupStatus(string $type, string $code, string $currentStatus): void
    {
        $config = $this->lookupConfig($type);

        $this->hospital()->table($config['table'])
            ->where($config['code'], $code)
            ->update([
                $config['status'] => $this->nextStatus($currentStatus),
                'updsw' => 'T',
                'datemod' => Carbon::now(),
            ]);

        session()->flash('success', $config['label'] . ' status updated successfully.');
    }

    public function createGroup(): void
    {
        $this->editingGrpcode = null;
        $this->resetGroupForm();
        $this->groupForm['grpcode'] = $this->nextNumericCode('hdruggrp', 'grpcode', 10, true);
        $this->groupModal = true;
    }

    public function editGroup(string $grpcode): void
    {
        $group = $this->hospital()->table('hdruggrp')->where('grpcode', $grpcode)->first();

        abort_if(! $group, 404);

        $this->editingGrpcode = $grpcode;
        $this->groupForm = [
            'grpcode' => trim((string) $group->grpcode),
            'gencode' => trim((string) $group->gencode),
            'dmcode' => $this->encodeClassValue($group->dmcode),
            'dms1key' => $this->encodeClassValue($group->dms1key),
            'dms2key' => $this->encodeClassValue($group->dms2key),
            'dms3key' => $this->encodeClassValue($group->dms3key),
            'dms4key' => $this->encodeClassValue($group->dms4key),
            'grpstat' => trim((string) $group->grpstat) ?: 'A',
        ];
        $this->groupModal = true;
    }

    public function saveGroup(): void
    {
        $this->validate([
            'groupForm.grpcode' => ['required', 'string', 'max:10'],
            'groupForm.gencode' => ['required', 'string', 'max:5'],
            'groupForm.dmcode' => ['required', 'string', 'max:120'],
            'groupForm.dms1key' => ['nullable', 'string', 'max:120'],
            'groupForm.dms2key' => ['nullable', 'string', 'max:120'],
            'groupForm.dms3key' => ['nullable', 'string', 'max:120'],
            'groupForm.dms4key' => ['nullable', 'string', 'max:120'],
            'groupForm.grpstat' => ['required', Rule::in(['A', 'I'])],
        ]);

        $grpcode = trim($this->groupForm['grpcode']);
        $dmcode = $this->decodeClassValue($this->groupForm['dmcode']);
        $dms1key = $this->decodeClassValue($this->groupForm['dms1key']);
        $dms2key = $this->decodeClassValue($this->groupForm['dms2key']);
        $dms3key = $this->decodeClassValue($this->groupForm['dms3key']);
        $dms4key = $this->decodeClassValue($this->groupForm['dms4key']);
        $exists = $this->hospital()->table('hdruggrp')
            ->where('grpcode', $grpcode)
            ->when($this->editingGrpcode, fn ($query) => $query->where('grpcode', '<>', $this->editingGrpcode))
            ->exists();

        if ($exists) {
            $this->addError('groupForm.grpcode', 'This PNDF group code already exists.');
            return;
        }

        $this->hospital()->table('hdruggrp')->updateOrInsert(
            ['grpcode' => $this->editingGrpcode ?: $grpcode],
            [
                'grpcode' => $grpcode,
                'gencode' => $this->groupForm['gencode'],
                'dmcode' => $dmcode,
                'dms1key' => $this->blankToNull($dms1key),
                'dms2key' => $this->blankToNull($dms2key),
                'dms3key' => $this->blankToNull($dms3key),
                'dms4key' => $this->blankToNull($dms4key),
                'grpstat' => $this->groupForm['grpstat'],
                'grplock' => 'N',
                'grpupsw' => 'T',
                'grpdtmd' => Carbon::now(),
            ]
        );

        session()->flash('success', 'PNDF group saved successfully.');
        $this->groupModal = false;
    }

    public function toggleGroupStatus(string $grpcode, string $currentStatus): void
    {
        $this->hospital()->table('hdruggrp')
            ->where('grpcode', $grpcode)
            ->update([
                'grpstat' => $this->nextStatus($currentStatus),
                'grpupsw' => 'T',
                'grpdtmd' => Carbon::now(),
            ]);

        session()->flash('success', 'PNDF group status updated successfully.');
    }

    public function editSub(string $dmdcomb, string $dmdctr, string $dmhdrsub): void
    {
        $sub = $this->hospital()->table('hdmhdrsub')->where(compact('dmdcomb', 'dmdctr', 'dmhdrsub'))->first();

        abort_if(! $sub, 404);

        $this->editingSubDmdcomb = $dmdcomb;
        $this->editingSubDmdctr = $dmdctr;
        $this->editingSubKey = $dmhdrsub;
        $this->subForm = [
            'dmhdrsub' => trim((string) $sub->dmhdrsub),
            'barcode' => trim((string) $sub->barcode),
            'begbal' => (string) ((int) $sub->begbal),
            'stockbal' => (string) ((int) $sub->stockbal),
            'rpoint' => (string) ((int) $sub->rpoint),
            'reorder_level' => (string) ((int) $sub->reorder_level),
            'statusMed' => trim((string) $sub->statusMed) ?: 'A',
        ];
        $this->subModal = true;
    }

    public function saveSub(): void
    {
        $this->validate([
            'subForm.dmhdrsub' => ['required', 'string', 'max:6'],
            'subForm.barcode' => ['nullable', 'string', 'max:20'],
            'subForm.begbal' => ['nullable', 'integer', 'min:0'],
            'subForm.stockbal' => ['nullable', 'integer', 'min:0'],
            'subForm.rpoint' => ['nullable', 'integer', 'min:0'],
            'subForm.reorder_level' => ['nullable', 'integer', 'min:0'],
            'subForm.statusMed' => ['required', Rule::in(['A', 'I'])],
        ]);

        $this->hospital()->table('hdmhdrsub')->updateOrInsert(
            [
                'dmdcomb' => $this->editingSubDmdcomb,
                'dmdctr' => $this->editingSubDmdctr,
                'dmhdrsub' => $this->editingSubKey,
            ],
            [
                'dmdcomb' => $this->editingSubDmdcomb,
                'dmdctr' => $this->editingSubDmdctr,
                'dmhdrsub' => $this->subForm['dmhdrsub'],
                'barcode' => $this->blankToNull($this->subForm['barcode']),
                'begbal' => (int) ($this->subForm['begbal'] ?: 0),
                'stockbal' => (int) ($this->subForm['stockbal'] ?: 0),
                'rpoint' => (int) ($this->subForm['rpoint'] ?: 0),
                'reorder_level' => (int) ($this->subForm['reorder_level'] ?: 0),
                'statusMed' => $this->subForm['statusMed'],
                'datemod' => Carbon::now(),
                'entryby' => optional(auth()->user())->employeeid ?: optional(auth()->user())->name,
            ]
        );

        session()->flash('success', 'Drug sub-record saved successfully.');
        $this->subModal = false;
    }

    public function toggleSubStatus(string $dmdcomb, string $dmdctr, string $dmhdrsub, string $currentStatus): void
    {
        $this->hospital()->table('hdmhdrsub')
            ->where('dmdcomb', $dmdcomb)
            ->where('dmdctr', $dmdctr)
            ->where('dmhdrsub', $dmhdrsub)
            ->update([
                'statusMed' => $this->nextStatus($currentStatus),
                'datemod' => Carbon::now(),
            ]);

        session()->flash('success', 'Drug sub-record status updated successfully.');
    }

    public function render()
    {
        return view('livewire.pharmacy.manage-drug-library', [
            'rows' => $this->rows(),
            'generics' => $this->options('hgen', 'gencode', 'gendesc'),
            'groups' => $this->groupOptions(),
            'forms' => $this->options('hform', 'formcode', 'formdesc'),
            'routes' => $this->options('hroute', 'rtecode', 'rtedesc'),
            'strengths' => $this->options('hstre', 'strecode', 'stredesc'),
            'chargeCodes' => $this->chargeOptions(),
            'majorClasses' => $this->majorClassOptions(),
            'subClass1Options' => $this->subClassOptions(1),
            'subClass2Options' => $this->subClassOptions(2),
            'subClass3Options' => $this->subClassOptions(3),
            'subClass4Options' => $this->subClassOptions(4),
            'lookupLabel' => $this->lookupType ? $this->lookupConfig($this->lookupType)['label'] : 'Library',
        ]);
    }

    private function rows()
    {
        return match ($this->tab) {
            'generics' => $this->lookupRows('generics'),
            'groups' => $this->groupRows(),
            'forms' => $this->lookupRows('forms'),
            'routes' => $this->lookupRows('routes'),
            'strengths' => $this->lookupRows('strengths'),
            'subs' => $this->subRows(),
            default => $this->drugRows(),
        };
    }

    private function drugRows()
    {
        $search = '%' . $this->search . '%';

        return $this->hospital()->table('hdmhdr as d')
            ->leftJoin('hdruggrp as grp', 'grp.grpcode', '=', 'd.grpcode')
            ->leftJoin('hgen as gen', 'gen.gencode', '=', 'grp.gencode')
            ->leftJoin('hform as form', 'form.formcode', '=', 'd.formcode')
            ->leftJoin('hroute as route', 'route.rtecode', '=', 'd.rtecode')
            ->leftJoin('hstre as strength', 'strength.strecode', '=', 'd.strecode')
            ->leftJoin('hdmhdrsub as sub', function ($join) {
                $join->on('sub.dmdcomb', '=', 'd.dmdcomb')->on('sub.dmdctr', '=', 'd.dmdctr');
            })
            ->select([
                'd.dmdcomb',
                'd.dmdctr',
                'd.dmdnost',
                'd.brandname',
                'd.dmdpndf',
                'd.dmdstat',
                'd.drug_concat',
                'gen.gendesc',
                'form.formdesc',
                'route.rtedesc',
                'strength.stredesc',
                DB::raw('COUNT(sub.dmhdrsub) as sub_count'),
            ])
            ->when($this->search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('d.drug_concat', 'like', $search)
                        ->orWhere('d.dmdnost', 'like', $search)
                        ->orWhere('d.brandname', 'like', $search)
                        ->orWhere('gen.gendesc', 'like', $search);
                });
            })
            ->groupBy('d.dmdcomb', 'd.dmdctr', 'd.dmdnost', 'd.brandname', 'd.dmdpndf', 'd.dmdstat', 'd.drug_concat', 'gen.gendesc', 'form.formdesc', 'route.rtedesc', 'strength.stredesc')
            ->orderBy('gen.gendesc')
            ->orderBy('d.dmdnost')
            ->paginate($this->perPage);
    }

    private function lookupRows(string $type)
    {
        $config = $this->lookupConfig($type);
        $search = '%' . $this->search . '%';

        return $this->hospital()->table($config['table'])
            ->select($config['code'] . ' as code', $config['description'] . ' as description', $config['status'] . ' as status')
            ->when($this->search, function ($query) use ($config, $search) {
                $query->where($config['code'], 'like', $search)->orWhere($config['description'], 'like', $search);
            })
            ->orderBy($config['description'])
            ->paginate($this->perPage);
    }

    private function groupRows()
    {
        $search = '%' . $this->search . '%';

        return $this->hospital()->table('hdruggrp as grp')
            ->leftJoin('hgen as gen', 'gen.gencode', '=', 'grp.gencode')
            ->leftJoin('dmmajor as major', 'major.dmcode', '=', 'grp.dmcode')
            ->leftJoin('dmsub1 as sub1', 'sub1.dms1key', '=', 'grp.dms1key')
            ->leftJoin('dmsub2 as sub2', 'sub2.dms2key', '=', 'grp.dms2key')
            ->leftJoin('dmsub3 as sub3', 'sub3.dms3key', '=', 'grp.dms3key')
            ->leftJoin('dmsub4 as sub4', 'sub4.dms4key', '=', 'grp.dms4key')
            ->select('grp.*', 'gen.gendesc', 'major.dmdesc', 'sub1.dms1desc', 'sub2.dms2desc', 'sub3.dms3desc', 'sub4.dms4desc')
            ->when($this->search, function ($query) use ($search) {
                $query->where('grp.grpcode', 'like', $search)
                    ->orWhere('gen.gendesc', 'like', $search)
                    ->orWhere('grp.dmcode', 'like', $search)
                    ->orWhere('major.dmdesc', 'like', $search)
                    ->orWhere('sub1.dms1desc', 'like', $search)
                    ->orWhere('sub2.dms2desc', 'like', $search)
                    ->orWhere('sub3.dms3desc', 'like', $search)
                    ->orWhere('sub4.dms4desc', 'like', $search);
            })
            ->orderBy('gen.gendesc')
            ->paginate($this->perPage);
    }

    private function subRows()
    {
        $search = '%' . $this->search . '%';

        return $this->hospital()->table('hdmhdrsub as sub')
            ->join('hdmhdr as d', function ($join) {
                $join->on('d.dmdcomb', '=', 'sub.dmdcomb')->on('d.dmdctr', '=', 'sub.dmdctr');
            })
            ->leftJoin('hcharge as charge', 'charge.chrgcode', '=', 'sub.dmhdrsub')
            ->select('sub.*', 'd.drug_concat', 'charge.chrgdesc')
            ->when($this->search, function ($query) use ($search) {
                $query->where('d.drug_concat', 'like', $search)
                    ->orWhere('sub.dmhdrsub', 'like', $search)
                    ->orWhere('sub.barcode', 'like', $search)
                    ->orWhere('charge.chrgdesc', 'like', $search);
            })
            ->orderBy('d.drug_concat')
            ->orderBy('sub.dmhdrsub')
            ->paginate($this->perPage);
    }

    private function options(string $table, string $codeColumn, string $descriptionColumn)
    {
        return $this->hospital()->table($table)
            ->select($codeColumn . ' as id', DB::raw("CONCAT($codeColumn, ' - ', $descriptionColumn) as name"))
            ->orderBy($descriptionColumn)
            ->limit(500)
            ->get();
    }

    private function chargeOptions()
    {
        return $this->hospital()->table('hcharge')
            ->select('chrgcode as id', DB::raw("CONCAT(chrgcode, ' - ', chrgdesc) as name"))
            ->orderBy('chrgdesc')
            ->limit(500)
            ->get();
    }

    private function groupOptions()
    {
        return $this->hospital()->table('hdruggrp as grp')
            ->leftJoin('hgen as gen', 'gen.gencode', '=', 'grp.gencode')
            ->select('grp.grpcode as id', DB::raw("CONCAT(grp.grpcode, ' - ', COALESCE(gen.gendesc, 'No generic')) as name"))
            ->where('grp.grpstat', 'A')
            ->orderBy('gen.gendesc')
            ->limit(500)
            ->get();
    }

    private function majorClassOptions()
    {
        return $this->hospital()->table('dmmajor')
            ->select('dmcode', 'dmdesc')
            ->where('dmstat', 'A')
            ->orderBy('dmdesc')
            ->limit(500)
            ->get()
            ->map(fn ($row) => [
                'id' => $this->encodeClassValue($row->dmcode),
                'name' => trim((string) $row->dmcode) . ' - ' . trim((string) $row->dmdesc),
            ]);
    }

    private function subClassOptions(int $level)
    {
        $config = match ($level) {
            1 => ['table' => 'dmsub1', 'key' => 'dms1key', 'desc' => 'dms1desc', 'status' => 'dms1sta', 'parent' => 'dmcode', 'parentValue' => $this->groupForm['dmcode'] ?? null],
            2 => ['table' => 'dmsub2', 'key' => 'dms2key', 'desc' => 'dms2desc', 'status' => 'dms2sta', 'parent' => 'dms1key', 'parentValue' => $this->groupForm['dms1key'] ?? null],
            3 => ['table' => 'dmsub3', 'key' => 'dms3key', 'desc' => 'dms3desc', 'status' => 'dms3sta', 'parent' => 'dms2key', 'parentValue' => $this->groupForm['dms2key'] ?? null],
            4 => ['table' => 'dmsub4', 'key' => 'dms4key', 'desc' => 'dms4desc', 'status' => 'dms4sta', 'parent' => 'dms3key', 'parentValue' => $this->groupForm['dms3key'] ?? null],
        };

        if (blank($config['parentValue'])) {
            return collect();
        }

        $parentValue = $this->decodeClassValue($config['parentValue']);

        return $this->hospital()->table($config['table'])
            ->select($config['key'] . ' as id', $config['desc'] . ' as description')
            ->where($config['parent'], $parentValue)
            ->where($config['status'], 'A')
            ->orderBy($config['desc'])
            ->limit(500)
            ->get()
            ->map(fn ($row) => [
                'id' => $this->encodeClassValue($row->id),
                'name' => trim((string) $row->id) . ' - ' . trim((string) $row->description),
            ]);
    }

    private function lookupConfig(?string $type): array
    {
        return match ($type) {
            'generics' => ['label' => 'Generic', 'table' => 'hgen', 'code' => 'gencode', 'description' => 'gendesc', 'status' => 'genstat', 'lock' => 'genlock'],
            'forms' => ['label' => 'Form', 'table' => 'hform', 'code' => 'formcode', 'description' => 'formdesc', 'status' => 'formstat', 'lock' => 'formlock'],
            'routes' => ['label' => 'Route', 'table' => 'hroute', 'code' => 'rtecode', 'description' => 'rtedesc', 'status' => 'rtestat', 'lock' => 'rtelock'],
            'strengths' => ['label' => 'Strength', 'table' => 'hstre', 'code' => 'strecode', 'description' => 'stredesc', 'status' => 'strestat', 'lock' => 'strelock'],
            default => abort(404),
        };
    }

    private function buildDrugConcat(): string
    {
        $group = $this->hospital()->table('hdruggrp as grp')
            ->leftJoin('hgen as gen', 'gen.gencode', '=', 'grp.gencode')
            ->where('grp.grpcode', $this->drugForm['grpcode'])
            ->value('gen.gendesc');
        $strength = $this->hospital()->table('hstre')->where('strecode', $this->drugForm['strecode'])->value('stredesc');
        $form = $this->hospital()->table('hform')->where('formcode', $this->drugForm['formcode'])->value('formdesc');

        return collect([$group, $this->drugForm['dmdnost'], $strength, $form, $this->drugForm['brandname']])
            ->filter(fn ($part) => filled($part))
            ->implode('_,');
    }

    private function nextNumericCode(string $table, string $column, int $length, bool $leadingZeroOnly = false): string
    {
        $query = $this->hospital()->table($table)
            ->selectRaw("MAX(CAST({$column} AS bigint)) as max_code")
            ->whereRaw("ISNUMERIC({$column}) = 1");

        if ($leadingZeroOnly) {
            $query->where($column, 'like', '0%');
        }

        $next = ((int) ($query->value('max_code') ?? 0)) + 1;

        return str_pad((string) $next, $length, '0', STR_PAD_LEFT);
    }

    private function nextStatus(?string $currentStatus): string
    {
        return strtoupper((string) $currentStatus) === 'A' ? 'I' : 'A';
    }

    private function encodeClassValue($value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '' : 'class:' . base64_encode($value);
    }

    private function decodeClassValue($value): string
    {
        $value = trim((string) $value);

        if (! str_starts_with($value, 'class:')) {
            return $value;
        }

        $decoded = base64_decode(substr($value, 6), true);

        return $decoded === false ? '' : $decoded;
    }

    private function resetDrugForm(): void
    {
        $this->drugForm = [
            'grpcode' => '',
            'dmdnost' => '',
            'strecode' => '',
            'formcode' => '',
            'rtecode' => '',
            'brandname' => '',
            'dmdpndf' => 'Y',
            'dmdrxot' => '',
            'dmdstat' => 'A',
            'dmdrem' => '',
            'barcode' => '',
            'dmhdrsub' => '',
            'sub_barcode' => '',
            'begbal' => '0',
            'stockbal' => '0',
            'reorder_level' => '0',
            'rpoint' => '0',
        ];
        $this->resetValidation();
    }

    private function resetLookupForm(): void
    {
        $this->lookupForm = ['code' => '', 'description' => '', 'status' => 'A'];
        $this->resetValidation();
    }

    private function resetGroupForm(): void
    {
        $this->groupForm = [
            'grpcode' => '',
            'gencode' => '',
            'dmcode' => '',
            'dms1key' => '',
            'dms2key' => '',
            'dms3key' => '',
            'dms4key' => '',
            'grpstat' => 'A',
        ];
        $this->resetValidation();
    }

    private function resetSubForm(): void
    {
        $this->subForm = [
            'dmhdrsub' => '',
            'barcode' => '',
            'begbal' => '0',
            'stockbal' => '0',
            'rpoint' => '0',
            'reorder_level' => '0',
            'statusMed' => 'A',
        ];
        $this->resetValidation();
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function hospital()
    {
        return DB::connection('hospital');
    }
}
