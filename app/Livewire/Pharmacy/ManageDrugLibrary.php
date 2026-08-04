<?php

namespace App\Livewire\Pharmacy;

use App\Services\Pharmacy\DrugImport\DrugMasterWriter;
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

    public string $statusFilter = '';

    public string $pndfFilter = '';

    public string $rxotFilter = '';

    public string $brandFilter = '';

    public string $groupFilter = '';

    public string $formFilter = '';

    public string $routeFilter = '';

    public string $strengthFilter = '';

    public string $majorClassFilter = '';

    public string $fundSourceFilter = '';

    public bool $drugModal = false;

    public bool $lookupModal = false;

    public bool $groupModal = false;

    public bool $subModal = false;

    public ?string $editingDmdcomb = null;

    public ?string $editingDmdctr = null;

    public ?string $brandSourceDmdcomb = null;

    public ?string $brandSourceDmdctr = null;

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
        abort_unless(auth()->user()?->can('view-settings'), 403);

        $this->resetDrugForm();
        $this->resetLookupForm();
        $this->resetGroupForm();
        $this->resetSubForm();

        if (request()->query('create') === 'generic') {
            $this->tab = 'generics';
            $this->createLookup('generics');
            $this->lookupForm['code'] = $this->nextNumericCode('hgen', 'gencode', 5);
            $this->lookupForm['description'] = trim((string) request()->query('generic'));
        } elseif (request()->query('create') === 'group') {
            $this->tab = 'groups';
            $this->createGroup();
            $this->groupForm['gencode'] = trim((string) request()->query('gencode'));
            foreach (['dmcode', 'dms1key', 'dms2key', 'dms3key', 'dms4key'] as $field) {
                $value = trim((string) request()->query($field));
                $this->groupForm[$field] = $this->encodeClassValue($value);
            }
        }
    }

    public function updatedTab(): void
    {
        $this->resetPage();
        $this->clearFilters();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updated(string $property): void
    {
        if (in_array($property, [
            'statusFilter',
            'pndfFilter',
            'rxotFilter',
            'brandFilter',
            'groupFilter',
            'formFilter',
            'routeFilter',
            'strengthFilter',
            'majorClassFilter',
            'fundSourceFilter',
            'perPage',
        ], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset([
            'search',
            'statusFilter',
            'pndfFilter',
            'rxotFilter',
            'brandFilter',
            'groupFilter',
            'formFilter',
            'routeFilter',
            'strengthFilter',
            'majorClassFilter',
            'fundSourceFilter',
        ]);
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
        $this->brandSourceDmdcomb = null;
        $this->brandSourceDmdctr = null;
        $this->resetDrugForm();
        $this->drugModal = true;
    }

    public function editDrug(string $dmdcomb, string $dmdctr): void
    {
        $drug = $this->hospital()->table('hdmhdr')->where(compact('dmdcomb', 'dmdctr'))->first();

        abort_if(! $drug, 404);

        $this->editingDmdcomb = $dmdcomb;
        $this->editingDmdctr = $dmdctr;
        $this->brandSourceDmdcomb = null;
        $this->brandSourceDmdctr = null;

        $this->fillDrugForm($drug);

        $this->drugModal = true;
    }

    public function createBrandVariant(string $dmdcomb, string $dmdctr): void
    {
        $drug = $this->hospital()->table('hdmhdr')->where(compact('dmdcomb', 'dmdctr'))->first();

        abort_if(! $drug, 404);

        $this->editingDmdcomb = null;
        $this->editingDmdctr = null;
        $this->brandSourceDmdcomb = $dmdcomb;
        $this->brandSourceDmdctr = $dmdctr;
        $this->fillDrugForm($drug);
        $this->drugForm['brandname'] = '';
        $this->drugForm['begbal'] = '0';
        $this->drugForm['stockbal'] = '0';
        $this->drugForm['barcode'] = '';
        $this->drugModal = true;
    }

    public function saveDrug(): void
    {
        $this->validate([
            'drugForm.grpcode' => ['required', 'string', 'max:10', Rule::exists('hospital.hdruggrp', 'grpcode')],
            'drugForm.dmdnost' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'drugForm.strecode' => ['required', 'string', 'max:5', Rule::exists('hospital.hstre', 'strecode')],
            'drugForm.formcode' => ['required', 'string', 'max:5', Rule::exists('hospital.hform', 'formcode')],
            'drugForm.rtecode' => ['required', 'string', 'max:5', Rule::exists('hospital.hroute', 'rtecode')],
            'drugForm.brandname' => [
                Rule::requiredIf(fn () => filled($this->brandSourceDmdcomb)),
                'nullable',
                'string',
                'max:30',
            ],
            'drugForm.dmdpndf' => ['required', Rule::in(['Y', 'N'])],
            'drugForm.dmdrxot' => ['required', Rule::in(['RXX', 'OTC'])],
            'drugForm.dmdstat' => ['required', Rule::in(['A', 'I'])],
            'drugForm.dmdrem' => ['nullable', 'string', 'max:255'],
            'drugForm.barcode' => ['nullable', 'string', 'max:30'],
            'drugForm.dmdnnostp' => ['nullable', Rule::in(['Y', 'N'])],
            'drugForm.atcode' => ['nullable', 'string', 'max:5'],
            'drugForm.dmdclaimno' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'drugForm.techspec' => ['nullable', 'string', 'max:255'],
            'drugForm.dmdclmuom' => ['nullable', 'string', 'max:5'],
            'drugForm.dmdstco' => ['nullable', 'string', 'max:255'],
            'drugForm.dmdedl' => ['nullable', Rule::in(['Y', 'N'])],
            'drugForm.lbscode' => ['nullable', 'string', 'max:5'],
            'drugForm.hprodid' => ['nullable', 'string', 'max:40'],
            'drugForm.begbal' => ['nullable', 'integer', 'min:0'],
            'drugForm.stockbal' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'drugForm.packcode' => ['nullable', 'string', 'max:5', Rule::exists('hospital.hpackage', 'packcode')],
            'drugForm.saltcode' => ['nullable', 'string', 'max:5', Rule::exists('hospital.hsalt', 'saltcode')],
            'drugForm.packvolno' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'drugForm.packvolunitcode' => ['nullable', 'string', 'max:5', Rule::exists('hospital.huom', 'uomcode')],
        ]);

        try {
            $this->hospital()->transaction(function () {
                $isEditing = filled($this->editingDmdcomb);
                $isBrandVariant = ! $isEditing && filled($this->brandSourceDmdcomb);
                $definitionSource = null;

                if ($isBrandVariant) {
                    $definitionSource = $this->hospital()->table('hdmhdr')
                        ->where('dmdcomb', $this->brandSourceDmdcomb)
                        ->where('dmdctr', $this->brandSourceDmdctr)
                        ->lockForUpdate()
                        ->first();

                    if (! $definitionSource) {
                        throw new \RuntimeException('The source drug for this brand variant no longer exists.');
                    }
                } elseif ($isEditing && (int) $this->editingDmdctr > 1) {
                    $definitionSource = $this->hospital()->table('hdmhdr')
                        ->where('dmdcomb', $this->editingDmdcomb)
                        ->where('dmdctr', $this->editingDmdctr)
                        ->lockForUpdate()
                        ->first();

                    if (! $definitionSource) {
                        throw new \RuntimeException('The drug record no longer exists.');
                    }
                }

                if ($definitionSource) {
                    $this->copySharedDrugDefinition($definitionSource);
                }

                $dmdcomb = $isEditing
                    ? $this->editingDmdcomb
                    : ($isBrandVariant ? $this->brandSourceDmdcomb : $this->nextDrugCodeLocked());
                $dmdctr = $isEditing
                    ? $this->editingDmdctr
                    : ($isBrandVariant ? $this->nextDrugCounterLocked($dmdcomb) : '1');
                $now = Carbon::now();

                $drugData = [
                    'grpcode' => $this->drugForm['grpcode'],
                    'dmdnost' => (float) $this->drugForm['dmdnost'],
                    'strecode' => $this->drugForm['strecode'],
                    'formcode' => $this->drugForm['formcode'],
                    'rtecode' => $this->drugForm['rtecode'],
                    'brandname' => $this->blankToNull($this->drugForm['brandname']),
                    'dmdpndf' => $this->drugForm['dmdpndf'],
                    'dmdrxot' => $this->drugForm['dmdrxot'],
                    'dmdstat' => $this->drugForm['dmdstat'],
                    'dmdrem' => $this->blankToNull($this->drugForm['dmdrem']),
                    'barcode' => $this->blankToNull($this->drugForm['barcode']),
                    'dmdnnostp' => $this->blankToNull($this->drugForm['dmdnnostp']),
                    'atcode' => $this->blankToNull($this->drugForm['atcode']),
                    'dmdclaimno' => $this->blankToNull($this->drugForm['dmdclaimno']),
                    'techspec' => $this->blankToNull($this->drugForm['techspec']),
                    'dmdclmuom' => $this->blankToNull($this->drugForm['dmdclmuom']),
                    'dmdstco' => $this->blankToNull($this->drugForm['dmdstco']),
                    'dmdedl' => $this->blankToNull($this->drugForm['dmdedl']),
                    'lbscode' => $this->blankToNull($this->drugForm['lbscode']),
                    'hprodid' => $this->blankToNull($this->drugForm['hprodid']),
                    'begbal' => (int) ($this->drugForm['begbal'] ?: 0),
                    'stockbal' => (float) ($this->drugForm['stockbal'] ?: 0),
                    'baldteasof' => $now,
                    'packcode' => $this->blankToNull($this->drugForm['packcode']),
                    'saltcode' => $this->blankToNull($this->drugForm['saltcode']),
                    'packvolno' => $this->blankToNull($this->drugForm['packvolno']),
                    'packvolunitcode' => $this->blankToNull($this->drugForm['packvolunitcode']),
                    'drug_concat' => $this->buildDrugConcat(),
                    'dmdupsw' => 'T',
                    'dmddtmd' => $now,
                ];

                if ($isEditing) {
                    $updated = $this->hospital()->table('hdmhdr')
                        ->where('dmdcomb', $dmdcomb)
                        ->where('dmdctr', $dmdctr)
                        ->update($drugData);

                    if ($updated === 0 && ! $this->hospital()->table('hdmhdr')->where(compact('dmdcomb', 'dmdctr'))->exists()) {
                        throw new \RuntimeException('The drug record no longer exists.');
                    }
                } else {
                    $this->hospital()->table('hdmhdr')->insert([
                        'dmdcomb' => $dmdcomb,
                        'dmdctr' => $dmdctr,
                        'dmdlock' => 'N',
                        'lot_no' => '',
                        ...$drugData,
                    ]);
                }
            });

            session()->flash('success', 'Drug and medicine item saved successfully.');
            $this->drugModal = false;
            $this->brandSourceDmdcomb = null;
            $this->brandSourceDmdctr = null;
        } catch (Throwable $e) {
            report($e);
            session()->flash('error', 'Unable to save the drug item. Please review the values and try again.');
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
            'lookupForm.description' => ['required', 'string', 'max:'.$config['descriptionMax']],
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
            $config['description'] => trim($this->lookupForm['description']),
            $config['status'] => $this->lookupForm['status'],
            'updsw' => 'T',
            'datemod' => Carbon::now(),
        ];

        if ($config['lock']) {
            $data[$config['lock']] = 'N';
        }

        if ($config['entryByLength']) {
            $data['entryby'] = $this->auditUser($config['entryByLength']);
        }

        if ($this->lookupOriginalCode) {
            $this->hospital()->table($config['table'])
                ->where($config['code'], $this->lookupOriginalCode)
                ->update($data);
        } else {
            $this->hospital()->table($config['table'])->insert([
                $config['code'] => $code,
                ...$data,
            ]);
        }

        session()->flash('success', $config['label'].' saved successfully.');
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

        session()->flash('success', $config['label'].' status updated successfully.');
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
            'groupForm.gencode' => ['required', 'string', 'max:5', Rule::exists('hospital.hgen', 'gencode')],
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

        foreach ([
            ['field' => 'dmcode', 'table' => 'dmmajor', 'key' => 'dmcode', 'value' => $dmcode, 'parent' => null, 'parentValue' => null],
            ['field' => 'dms1key', 'table' => 'dmsub1', 'key' => 'dms1key', 'value' => $dms1key, 'parent' => 'dmcode', 'parentValue' => $dmcode],
            ['field' => 'dms2key', 'table' => 'dmsub2', 'key' => 'dms2key', 'value' => $dms2key, 'parent' => 'dms1key', 'parentValue' => $dms1key],
            ['field' => 'dms3key', 'table' => 'dmsub3', 'key' => 'dms3key', 'value' => $dms3key, 'parent' => 'dms2key', 'parentValue' => $dms2key],
            ['field' => 'dms4key', 'table' => 'dmsub4', 'key' => 'dms4key', 'value' => $dms4key, 'parent' => 'dms3key', 'parentValue' => $dms3key],
        ] as $classification) {
            if (blank($classification['value'])) {
                continue;
            }

            $query = $this->hospital()->table($classification['table'])
                ->where($classification['key'], $classification['value']);

            if ($classification['parent']) {
                $query->where($classification['parent'], $classification['parentValue']);
            }

            if (! $query->exists()) {
                $this->addError(
                    'groupForm.'.$classification['field'],
                    'The selected classification is invalid for its parent.'
                );

                return;
            }
        }

        $exists = $this->hospital()->table('hdruggrp')
            ->where('grpcode', $grpcode)
            ->when($this->editingGrpcode, fn ($query) => $query->where('grpcode', '<>', $this->editingGrpcode))
            ->exists();

        if ($exists) {
            $this->addError('groupForm.grpcode', 'This PNDF group code already exists.');

            return;
        }

        $groupData = [
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
        ];

        if ($this->editingGrpcode) {
            $this->hospital()->table('hdruggrp')
                ->where('grpcode', $this->editingGrpcode)
                ->update($groupData);
        } else {
            $this->hospital()->table('hdruggrp')->insert([
                'grpcode' => $grpcode,
                ...$groupData,
            ]);
        }

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
            'begbal' => (string) ((float) $sub->begbal),
            'stockbal' => (string) ((float) $sub->stockbal),
            'rpoint' => (string) ((float) $sub->rpoint),
            'reorder_level' => (string) ((int) $sub->reorder_level),
            'statusMed' => trim((string) $sub->statusMed) ?: 'A',
        ];
        $this->subModal = true;
    }

    public function createSub(string $dmdcomb, string $dmdctr): void
    {
        abort_if(! $this->hospital()->table('hdmhdr')->where(compact('dmdcomb', 'dmdctr'))->exists(), 404);

        $this->editingSubDmdcomb = $dmdcomb;
        $this->editingSubDmdctr = $dmdctr;
        $this->editingSubKey = null;
        $this->resetSubForm();
        $this->subModal = true;
    }

    public function saveSub(): void
    {
        $this->validate([
            'subForm.dmhdrsub' => ['required', 'string', 'max:6', Rule::exists('hospital.hcharge', 'chrgcode')],
            'subForm.barcode' => ['nullable', 'string', 'max:30'],
            'subForm.begbal' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'subForm.stockbal' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'subForm.rpoint' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'subForm.reorder_level' => ['nullable', 'integer', 'min:0'],
            'subForm.statusMed' => ['required', Rule::in(['A', 'I'])],
        ]);

        $key = [
            'dmdcomb' => $this->editingSubDmdcomb,
            'dmdctr' => $this->editingSubDmdctr,
            'dmhdrsub' => $this->editingSubKey ?: $this->subForm['dmhdrsub'],
        ];
        $data = [
            'barcode' => $this->blankToNull($this->subForm['barcode']),
            'begbal' => (float) ($this->subForm['begbal'] ?: 0),
            'stockbal' => (float) ($this->subForm['stockbal'] ?: 0),
            'rpoint' => (float) ($this->subForm['rpoint'] ?: 0),
            'reorder_level' => (int) ($this->subForm['reorder_level'] ?: 0),
            'statusMed' => $this->subForm['statusMed'],
            'datemod' => Carbon::now(),
            'entryby' => $this->auditUser(10),
        ];

        if ($this->editingSubKey) {
            $this->hospital()->table('hdmhdrsub')->where($key)->update($data);
        } else {
            if ($this->hospital()->table('hdmhdrsub')->where($key)->exists()) {
                $this->addError('subForm.dmhdrsub', 'This fund source already exists for the selected drug.');

                return;
            }

            $this->hospital()->table('hdmhdrsub')->insert([...$key, 'baldteasof' => Carbon::now(), ...$data]);
        }

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
        $lookupTab = in_array($this->tab, ['generics', 'forms', 'routes', 'strengths', 'packages', 'salts', 'units'], true)
            ? $this->tab
            : null;
        $lookupType = $this->lookupType ?: $lookupTab;
        $emptyOptions = collect();
        $showDrugFilters = $this->tab === 'drugs';

        return view('livewire.pharmacy.manage-drug-library', [
            'rows' => $this->rows(),
            'generics' => $this->groupModal ? $this->options('hgen', 'gencode', 'gendesc') : $emptyOptions,
            'groups' => $this->drugModal ? $this->groupOptions() : $emptyOptions,
            'forms' => $this->drugModal ? $this->options('hform', 'formcode', 'formdesc') : $emptyOptions,
            'routes' => $this->drugModal ? $this->options('hroute', 'rtecode', 'rtedesc') : $emptyOptions,
            'strengths' => $this->drugModal ? $this->options('hstre', 'strecode', 'stredesc') : $emptyOptions,
            'packages' => $this->drugModal ? $this->options('hpackage', 'packcode', 'packdesc') : $emptyOptions,
            'salts' => $this->drugModal ? $this->options('hsalt', 'saltcode', 'saltdesc') : $emptyOptions,
            'units' => $this->drugModal ? $this->options('huom', 'uomcode', 'uomdesc') : $emptyOptions,
            'chargeCodes' => $this->subModal ? $this->chargeOptions() : $emptyOptions,
            'majorClasses' => $this->groupModal ? $this->majorClassOptions() : $emptyOptions,
            'subClass1Options' => $this->groupModal ? $this->subClassOptions(1) : $emptyOptions,
            'subClass2Options' => $this->groupModal ? $this->subClassOptions(2) : $emptyOptions,
            'subClass3Options' => $this->groupModal ? $this->subClassOptions(3) : $emptyOptions,
            'subClass4Options' => $this->groupModal ? $this->subClassOptions(4) : $emptyOptions,
            'filterGroups' => $showDrugFilters ? $this->groupOptions(false) : $emptyOptions,
            'filterForms' => $showDrugFilters ? $this->options('hform', 'formcode', 'formdesc') : $emptyOptions,
            'filterRoutes' => $showDrugFilters ? $this->options('hroute', 'rtecode', 'rtedesc') : $emptyOptions,
            'filterStrengths' => $showDrugFilters ? $this->options('hstre', 'strecode', 'stredesc') : $emptyOptions,
            'filterMajorClasses' => $this->tab === 'groups' ? $this->options('dmmajor', 'dmcode', 'dmdesc') : $emptyOptions,
            'filterChargeCodes' => $this->tab === 'subs' ? $this->chargeOptions(false) : $emptyOptions,
            'activeFilterCount' => $this->activeFilterCount(),
            'lookupLabel' => $lookupType ? $this->lookupConfig($lookupType)['label'] : 'Library',
        ]);
    }

    private function rows()
    {
        return match ($this->tab) {
            'generics', 'forms', 'routes', 'strengths', 'packages', 'salts', 'units' => $this->lookupRows($this->tab),
            'groups' => $this->groupRows(),
            'subs' => $this->subRows(),
            default => $this->drugRows(),
        };
    }

    private function drugRows()
    {
        $search = '%'.$this->search.'%';

        return $this->hospital()->table('hdmhdr as d')
            ->join('hdruggrp as grp', 'grp.grpcode', '=', 'd.grpcode')
            ->join('hgen as gen', 'gen.gencode', '=', 'grp.gencode')
            ->join('hform as form', 'form.formcode', '=', 'd.formcode')
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
            ->when($this->statusFilter, fn ($query) => $query->where('d.dmdstat', $this->statusFilter))
            ->when($this->pndfFilter, fn ($query) => $query->where('d.dmdpndf', $this->pndfFilter))
            ->when($this->rxotFilter, fn ($query) => $query->where('d.dmdrxot', $this->rxotFilter))
            ->when($this->groupFilter, fn ($query) => $query->where('d.grpcode', $this->groupFilter))
            ->when($this->formFilter, fn ($query) => $query->where('d.formcode', $this->formFilter))
            ->when($this->routeFilter, fn ($query) => $query->where('d.rtecode', $this->routeFilter))
            ->when($this->strengthFilter, fn ($query) => $query->where('d.strecode', $this->strengthFilter))
            ->when($this->brandFilter === 'generic', fn ($query) => $query->whereRaw("NULLIF(LTRIM(RTRIM(d.brandname)), '') IS NULL"))
            ->when($this->brandFilter === 'branded', fn ($query) => $query->whereRaw("NULLIF(LTRIM(RTRIM(d.brandname)), '') IS NOT NULL"))
            ->groupBy('d.dmdcomb', 'd.dmdctr', 'd.dmdnost', 'd.brandname', 'd.dmdpndf', 'd.dmdstat', 'd.drug_concat', 'gen.gendesc', 'form.formdesc', 'route.rtedesc', 'strength.stredesc')
            ->orderBy('gen.gendesc')
            ->orderBy('d.dmdcomb')
            ->orderBy('d.dmdctr')
            ->paginate($this->perPage);
    }

    private function lookupRows(string $type)
    {
        $config = $this->lookupConfig($type);
        $search = '%'.$this->search.'%';

        return $this->hospital()->table($config['table'])
            ->select($config['code'].' as code', $config['description'].' as description', $config['status'].' as status')
            ->when($this->search, function ($query) use ($config, $search) {
                $query->where(function ($q) use ($config, $search) {
                    $q->where($config['code'], 'like', $search)
                        ->orWhere($config['description'], 'like', $search);
                });
            })
            ->when($this->statusFilter, fn ($query) => $query->where($config['status'], $this->statusFilter))
            ->orderBy($config['description'])
            ->paginate($this->perPage);
    }

    private function groupRows()
    {
        $search = '%'.$this->search.'%';

        return $this->hospital()->table('hdruggrp as grp')
            ->leftJoin('hgen as gen', 'gen.gencode', '=', 'grp.gencode')
            ->leftJoin('dmmajor as major', 'major.dmcode', '=', 'grp.dmcode')
            ->leftJoin('dmsub1 as sub1', 'sub1.dms1key', '=', 'grp.dms1key')
            ->leftJoin('dmsub2 as sub2', 'sub2.dms2key', '=', 'grp.dms2key')
            ->leftJoin('dmsub3 as sub3', 'sub3.dms3key', '=', 'grp.dms3key')
            ->leftJoin('dmsub4 as sub4', 'sub4.dms4key', '=', 'grp.dms4key')
            ->select('grp.*', 'gen.gendesc', 'major.dmdesc', 'sub1.dms1desc', 'sub2.dms2desc', 'sub3.dms3desc', 'sub4.dms4desc')
            ->when($this->search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('grp.grpcode', 'like', $search)
                        ->orWhere('gen.gendesc', 'like', $search)
                        ->orWhere('grp.dmcode', 'like', $search)
                        ->orWhere('major.dmdesc', 'like', $search)
                        ->orWhere('sub1.dms1desc', 'like', $search)
                        ->orWhere('sub2.dms2desc', 'like', $search)
                        ->orWhere('sub3.dms3desc', 'like', $search)
                        ->orWhere('sub4.dms4desc', 'like', $search);
                });
            })
            ->when($this->statusFilter, fn ($query) => $query->where('grp.grpstat', $this->statusFilter))
            ->when($this->majorClassFilter, fn ($query) => $query->where('grp.dmcode', $this->majorClassFilter))
            ->orderBy('gen.gendesc')
            ->paginate($this->perPage);
    }

    private function subRows()
    {
        $search = '%'.$this->search.'%';

        return $this->hospital()->table('hdmhdrsub as sub')
            ->join('hdmhdr as d', function ($join) {
                $join->on('d.dmdcomb', '=', 'sub.dmdcomb')->on('d.dmdctr', '=', 'sub.dmdctr');
            })
            ->leftJoin('hcharge as charge', 'charge.chrgcode', '=', 'sub.dmhdrsub')
            ->select('sub.*', 'd.drug_concat', 'charge.chrgdesc')
            ->when($this->search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('d.drug_concat', 'like', $search)
                        ->orWhere('sub.dmhdrsub', 'like', $search)
                        ->orWhere('sub.barcode', 'like', $search)
                        ->orWhere('charge.chrgdesc', 'like', $search);
                });
            })
            ->when($this->statusFilter, fn ($query) => $query->where('sub.statusMed', $this->statusFilter))
            ->when($this->fundSourceFilter, fn ($query) => $query->where('sub.dmhdrsub', $this->fundSourceFilter))
            ->orderBy('d.drug_concat')
            ->orderBy('sub.dmhdrsub')
            ->paginate($this->perPage);
    }

    private function options(string $table, string $codeColumn, string $descriptionColumn)
    {
        return $this->hospital()->table($table)
            ->select($codeColumn.' as id', DB::raw("CONCAT($codeColumn, ' - ', $descriptionColumn) as name"))
            ->orderBy($descriptionColumn)
            ->get();
    }

    private function chargeOptions(bool $activeOnly = true)
    {
        return $this->hospital()->table('hcharge')
            ->select('chrgcode as id', DB::raw("CONCAT(chrgcode, ' - ', chrgdesc) as name"))
            ->when($activeOnly, fn ($query) => $query->where('chrgstat', 'A'))
            ->orderBy('chrgdesc')
            ->get();
    }

    private function groupOptions(bool $activeOnly = true)
    {
        return $this->hospital()->table('hdruggrp as grp')
            ->leftJoin('hgen as gen', 'gen.gencode', '=', 'grp.gencode')
            ->select('grp.grpcode as id', DB::raw("CONCAT(grp.grpcode, ' - ', COALESCE(gen.gendesc, 'No generic')) as name"))
            ->when($activeOnly, fn ($query) => $query->where('grp.grpstat', 'A'))
            ->orderBy('gen.gendesc')
            ->get();
    }

    private function activeFilterCount(): int
    {
        $filters = [$this->search, $this->statusFilter];

        if ($this->tab === 'drugs') {
            $filters = [
                ...$filters,
                $this->pndfFilter,
                $this->rxotFilter,
                $this->brandFilter,
                $this->groupFilter,
                $this->formFilter,
                $this->routeFilter,
                $this->strengthFilter,
            ];
        } elseif ($this->tab === 'groups') {
            $filters[] = $this->majorClassFilter;
        } elseif ($this->tab === 'subs') {
            $filters[] = $this->fundSourceFilter;
        }

        return collect($filters)->filter(fn ($value) => filled($value))->count();
    }

    private function majorClassOptions()
    {
        return $this->hospital()->table('dmmajor')
            ->select('dmcode', 'dmdesc')
            ->where('dmstat', 'A')
            ->orderBy('dmdesc')
            ->get()
            ->map(fn ($row) => [
                'id' => $this->encodeClassValue($row->dmcode),
                'name' => trim((string) $row->dmcode).' - '.trim((string) $row->dmdesc),
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
            ->select($config['key'].' as id', $config['desc'].' as description')
            ->where($config['parent'], $parentValue)
            ->where($config['status'], 'A')
            ->orderBy($config['desc'])
            ->get()
            ->map(fn ($row) => [
                'id' => $this->encodeClassValue($row->id),
                'name' => trim((string) $row->id).' - '.trim((string) $row->description),
            ]);
    }

    private function lookupConfig(?string $type): array
    {
        return match ($type) {
            'generics' => ['label' => 'Generic', 'table' => 'hgen', 'code' => 'gencode', 'description' => 'gendesc', 'descriptionMax' => 255, 'status' => 'genstat', 'lock' => 'genlock', 'entryByLength' => 15],
            'forms' => ['label' => 'Form', 'table' => 'hform', 'code' => 'formcode', 'description' => 'formdesc', 'descriptionMax' => 255, 'status' => 'formstat', 'lock' => 'formlock', 'entryByLength' => 15],
            'routes' => ['label' => 'Route', 'table' => 'hroute', 'code' => 'rtecode', 'description' => 'rtedesc', 'descriptionMax' => 100, 'status' => 'rtestat', 'lock' => 'rtelock', 'entryByLength' => null],
            'strengths' => ['label' => 'Strength', 'table' => 'hstre', 'code' => 'strecode', 'description' => 'stredesc', 'descriptionMax' => 255, 'status' => 'strestat', 'lock' => 'strelock', 'entryByLength' => 30],
            'packages' => ['label' => 'Packaging', 'table' => 'hpackage', 'code' => 'packcode', 'description' => 'packdesc', 'descriptionMax' => 255, 'status' => 'packstat', 'lock' => 'packlock', 'entryByLength' => 30],
            'salts' => ['label' => 'Salt', 'table' => 'hsalt', 'code' => 'saltcode', 'description' => 'saltdesc', 'descriptionMax' => 255, 'status' => 'saltstat', 'lock' => null, 'entryByLength' => 30],
            'units' => ['label' => 'Unit of Measure', 'table' => 'huom', 'code' => 'uomcode', 'description' => 'uomdesc', 'descriptionMax' => 255, 'status' => 'uomstat', 'lock' => 'uomlock', 'entryByLength' => 30],
            default => abort(404),
        };
    }

    private function copySharedDrugDefinition(object $source): void
    {
        foreach ([
            'grpcode',
            'dmdnost',
            'strecode',
            'formcode',
            'rtecode',
            'dmdpndf',
            'dmdrxot',
            'dmdnnostp',
            'atcode',
            'techspec',
            'dmdrem',
            'dmdclmuom',
            'dmdstco',
            'dmdedl',
            'lbscode',
            'packcode',
            'saltcode',
            'packvolno',
            'packvolunitcode',
        ] as $field) {
            $this->drugForm[$field] = trim((string) $source->{$field});
        }
    }

    private function auditUser(int $maxLength): ?string
    {
        $value = optional(auth()->user())->employeeid ?: optional(auth()->user())->name;

        return filled($value) ? mb_substr(trim((string) $value), 0, $maxLength) : null;
    }

    private function buildDrugConcat(): string
    {
        return app(DrugMasterWriter::class)->description($this->hospital(), $this->drugForm);
    }

    private function nextDrugCodeLocked(): string
    {
        return app(DrugMasterWriter::class)->nextDrugCodeLocked($this->hospital());
    }

    private function nextDrugCounterLocked(string $dmdcomb): string
    {
        $row = $this->hospital()->selectOne(
            'SELECT MAX(dmdctr) AS max_counter FROM hdmhdr WITH (UPDLOCK, HOLDLOCK) WHERE dmdcomb = ?',
            [$dmdcomb]
        );
        $next = ((int) ($row->max_counter ?? 0)) + 1;

        if ($next > 99) {
            throw new \RuntimeException('No more brand counters are available for this drug code.');
        }

        return (string) $next;
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

        return $value === '' ? '' : 'class:'.base64_encode($value);
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
            'dmdrxot' => 'RXX',
            'dmdstat' => 'A',
            'dmdrem' => '',
            'barcode' => '',
            'dmdnnostp' => 'N',
            'atcode' => '',
            'dmdclaimno' => '',
            'techspec' => '',
            'dmdclmuom' => '',
            'dmdstco' => '',
            'dmdedl' => '',
            'lbscode' => '',
            'hprodid' => '',
            'begbal' => '0',
            'stockbal' => '0',
            'packcode' => '',
            'saltcode' => '',
            'packvolno' => '',
            'packvolunitcode' => '',
        ];
        $this->resetValidation();
    }

    private function fillDrugForm(object $drug): void
    {
        $this->drugForm = [
            'grpcode' => trim((string) $drug->grpcode),
            'dmdnost' => trim((string) $drug->dmdnost),
            'strecode' => trim((string) $drug->strecode),
            'formcode' => trim((string) $drug->formcode),
            'rtecode' => trim((string) $drug->rtecode),
            'brandname' => trim((string) $drug->brandname),
            'dmdpndf' => trim((string) $drug->dmdpndf) ?: 'Y',
            'dmdrxot' => trim((string) $drug->dmdrxot) ?: 'RXX',
            'dmdstat' => trim((string) $drug->dmdstat) ?: 'A',
            'dmdrem' => trim((string) $drug->dmdrem),
            'barcode' => trim((string) $drug->barcode),
            'dmdnnostp' => trim((string) $drug->dmdnnostp) ?: 'N',
            'atcode' => trim((string) $drug->atcode),
            'dmdclaimno' => trim((string) $drug->dmdclaimno),
            'techspec' => trim((string) $drug->techspec),
            'dmdclmuom' => trim((string) $drug->dmdclmuom),
            'dmdstco' => trim((string) $drug->dmdstco),
            'dmdedl' => trim((string) $drug->dmdedl),
            'lbscode' => trim((string) $drug->lbscode),
            'hprodid' => trim((string) $drug->hprodid),
            'begbal' => trim((string) ($drug->begbal ?? 0)),
            'stockbal' => trim((string) ($drug->stockbal ?? 0)),
            'packcode' => trim((string) $drug->packcode),
            'saltcode' => trim((string) $drug->saltcode),
            'packvolno' => trim((string) $drug->packvolno),
            'packvolunitcode' => trim((string) $drug->packvolunitcode),
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
