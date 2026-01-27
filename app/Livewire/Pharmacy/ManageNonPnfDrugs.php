<?php

namespace App\Livewire\Pharmacy;

use App\Models\NonPnfDrug;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ManageNonPnfDrugs extends Component
{
    use WithPagination, WithFileUploads;

    public $search = '';
    public $showModal = false;
    public $showImportModal = false;
    public $showDeletedOnly = false;
    public $importFile;

    // Form fields
    public $drugId;
    public $medicine_name = '';
    public $dose = '';
    public $unit = '';
    public $is_active = true;
    public $remarks = '';

    // Import results
    public $importResults = null;
    public $previewData = null;
    public $showPreview = false;

    protected $queryString = ['search', 'showDeletedOnly'];

    protected function rules()
    {
        return [
            'medicine_name' => 'required|string|max:255',
            'dose' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'remarks' => 'nullable|string|max:500',
        ];
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingShowDeletedOnly()
    {
        $this->resetPage();
    }

    public function updatedImportFile()
    {
        if ($this->importFile) {
            $this->importExcel();
        }
    }

    public function create()
    {
        $this->resetForm();
        $this->drugId = null;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $drug = NonPnfDrug::withTrashed()->findOrFail($id);

        $this->drugId = $drug->id;
        $this->medicine_name = $drug->medicine_name;
        $this->dose = $drug->dose;
        $this->unit = $drug->unit;
        $this->is_active = $drug->is_active;
        $this->remarks = $drug->remarks;

        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'medicine_name' => $this->medicine_name,
            'dose' => $this->dose,
            'unit' => $this->unit,
            'is_active' => $this->is_active,
            'remarks' => $this->remarks,
        ];

        if ($this->drugId) {
            NonPnfDrug::withTrashed()->findOrFail($this->drugId)->update($data);
            session()->flash('success', 'Non-PNF Drug updated successfully');
        } else {
            NonPnfDrug::create($data);
            session()->flash('success', 'Non-PNF Drug created successfully');
        }

        $this->closeModal();
    }

    public function toggleActive($id)
    {
        $drug = NonPnfDrug::findOrFail($id);
        $drug->update(['is_active' => !$drug->is_active]);

        session()->flash('success', 'Status updated successfully');
    }

    public function delete($id)
    {
        NonPnfDrug::findOrFail($id)->delete();
        session()->flash('success', 'Non-PNF Drug deleted successfully');
    }

    public function restore($id)
    {
        NonPnfDrug::withTrashed()->findOrFail($id)->restore();
        session()->flash('success', 'Non-PNF Drug restored successfully');
    }

    public function forceDelete($id)
    {
        NonPnfDrug::withTrashed()->findOrFail($id)->forceDelete();
        session()->flash('success', 'Non-PNF Drug permanently deleted');
    }

    public function openImportModal()
    {
        $this->importFile = null;
        $this->importResults = null;
        $this->showImportModal = true;
    }

    public function downloadTemplate()
    {
        $fileName = 'Non-PNF_Drugs_Template.xlsx';
        $filePath = storage_path('app/templates/' . $fileName);

        if (!file_exists($filePath)) {
            $this->createTemplate();
        }

        return response()->download($filePath, $fileName);
    }

    private function createTemplate()
    {
        $templatePath = storage_path('app/templates');
        if (!file_exists($templatePath)) {
            mkdir($templatePath, 0755, true);
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'LIST OF MEDICINES');
        $sheet->setCellValue('B1', 'Dose');
        $sheet->setCellValue('C1', 'Unit');

        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2EFDA']
            ]
        ];
        $sheet->getStyle('A1:C1')->applyFromArray($headerStyle);

        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(20);

        $sheet->setCellValue('A2', 'Paracetamol');
        $sheet->setCellValue('B2', '500mg');
        $sheet->setCellValue('C2', 'tablet');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save(storage_path('app/templates/Non-PNF_Drugs_Template.xlsx'));
    }

    public function importExcel()
    {
        $this->validate([
            'importFile' => 'required|mimes:xlsx,xls|max:10240'
        ]);

        try {
            $path = $this->importFile->getRealPath();
            $spreadsheet = IOFactory::load($path);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            $previewData = [];
            $validCount = 0;
            $invalidCount = 0;

            foreach (array_slice($rows, 1) as $index => $row) {
                $rowNumber = $index + 2;

                if (empty(array_filter($row))) {
                    continue;
                }

                $medicineName = trim($row[0] ?? '');
                $dose = trim($row[1] ?? '');
                $unit = trim($row[2] ?? '');

                $errors = [];
                $status = 'valid';

                if (empty($medicineName)) {
                    $errors[] = 'Medicine name is required';
                    $status = 'invalid';
                    $invalidCount++;
                } else {
                    // Check for duplicates in database
                    $existing = DB::table('pharm_non_pnf_drugs')
                        ->whereNull('deleted_at')
                        ->where('medicine_name', $medicineName)
                        ->where('dose', $dose ?: null)
                        ->where('unit', $unit ?: null)
                        ->exists();

                    if ($existing) {
                        $errors[] = 'Duplicate entry exists';
                        $status = 'duplicate';
                    }

                    $validCount++;
                }

                $previewData[] = [
                    'row_number' => $rowNumber,
                    'medicine_name' => $medicineName,
                    'dose' => $dose,
                    'unit' => $unit,
                    'status' => $status,
                    'errors' => $errors
                ];
            }

            $this->previewData = [
                'data' => $previewData,
                'valid_count' => $validCount,
                'invalid_count' => $invalidCount,
                'total_count' => count($previewData)
            ];

            $this->showPreview = true;
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to read file: ' . $e->getMessage());
        }
    }

    public function confirmImport()
    {
        if (!$this->previewData) {
            session()->flash('error', 'No preview data available');
            return;
        }

        try {
            $imported = 0;
            $skipped = 0;
            $errors = [];

            DB::beginTransaction();

            foreach ($this->previewData['data'] as $row) {
                if ($row['status'] === 'invalid') {
                    $skipped++;
                    $errors[] = "Row {$row['row_number']}: " . implode(', ', $row['errors']);
                    continue;
                }

                if ($row['status'] === 'duplicate') {
                    $skipped++;
                    $errors[] = "Row {$row['row_number']}: Duplicate entry";
                    continue;
                }

                try {
                    NonPnfDrug::create([
                        'medicine_name' => $row['medicine_name'],
                        'dose' => $row['dose'] ?: null,
                        'unit' => $row['unit'] ?: null,
                        'is_active' => true,
                        'remarks' => 'Imported from Excel'
                    ]);
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Row {$row['row_number']}: " . $e->getMessage();
                    $skipped++;
                }
            }

            DB::commit();

            $this->importResults = [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors
            ];

            if ($imported > 0) {
                session()->flash('success', "Successfully imported {$imported} drugs");
            }

            $this->showPreview = false;
            $this->previewData = null;
            $this->importFile = null;
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Import failed: ' . $e->getMessage());
        }
    }

    public function cancelPreview()
    {
        $this->showPreview = false;
        $this->previewData = null;
        $this->importFile = null;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function closeImportModal()
    {
        $this->showImportModal = false;
        $this->importFile = null;
        $this->importResults = null;
        $this->previewData = null;
        $this->showPreview = false;
    }

    private function resetForm()
    {
        $this->reset(['medicine_name', 'dose', 'unit', 'is_active', 'remarks']);
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render()
    {
        $query = DB::table('pharm_non_pnf_drugs');

        if ($this->showDeletedOnly) {
            $query->whereNotNull('deleted_at');
        } else {
            $query->whereNull('deleted_at');
        }

        if ($this->search) {
            $search = '%' . $this->search . '%';

            // Use DIFFERENCE() for phonetic matching (MS SQL Server)
            // DIFFERENCE returns 0-4, where 4 = identical sound
            $query->where(function ($q) use ($search) {
                $q->where('medicine_name', 'like', $search)
                    ->orWhere('dose', 'like', $search)
                    ->orWhere('unit', 'like', $search)
                    ->orWhereRaw('DIFFERENCE(medicine_name, ?) >= 3', [$this->search]);
            });

            // Order by relevance: exact matches first, then phonetic matches
            $query->orderByRaw("
                CASE
                    WHEN medicine_name LIKE ? THEN 1
                    WHEN medicine_name LIKE ? THEN 2
                    WHEN DIFFERENCE(medicine_name, ?) = 4 THEN 3
                    WHEN DIFFERENCE(medicine_name, ?) = 3 THEN 4
                    ELSE 5
                END,
                medicine_name ASC
            ", [$this->search, $search, $this->search, $this->search]);
        } else {
            $query->orderBy('medicine_name', 'asc');
        }

        $drugs = $query->paginate(20);

        return view('livewire.pharmacy.manage-non-pnf-drugs', [
            'drugs' => $drugs,
        ]);
    }
}
