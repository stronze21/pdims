<?php

namespace App\Livewire\Pharmacy\Purchases;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class PimsRisList extends Component
{
    use WithPagination;

    public $search = '';
    public $sortField = 'tbl_ris.risdate';
    public $sortDirection = 'desc';
    public $perPage = 10;
    public $officeId = 22;
    public $statusFilter = 'all';

    protected $queryString = ['search', 'sortField', 'sortDirection', 'perPage', 'statusFilter'];

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function getDeliveryStatus($risItem)
    {
        if ($risItem->transferred_to_pdims) {
            return 'Transferred to Delivery';
        } elseif ($risItem->issuedstat === 'I') {
            return 'Issued';
        } elseif ($risItem->apprvstat === 'A') {
            return 'Approved';
        } elseif ($risItem->apprvstat === 'P') {
            return 'Pending Approval';
        } else {
            return 'Draft';
        }
    }

    public function getDeliveryStatusClass($risItem)
    {
        if ($risItem->transferred_to_pdims) {
            return 'badge-success';
        } elseif ($risItem->issuedstat === 'I') {
            return 'badge-primary';
        } elseif ($risItem->apprvstat === 'A') {
            return 'badge-info';
        } elseif ($risItem->apprvstat === 'P') {
            return 'badge-warning';
        } else {
            return 'badge-ghost';
        }
    }

    public function render()
    {
        $risItems = DB::connection('pims')
            ->table('tbl_ris')
            ->select([
                'tbl_ris.risid',
                'tbl_ris.risno',
                'tbl_ris.purpose',
                DB::raw("DATE_FORMAT(tbl_ris.risdate, '%b-%d-%Y') AS formatted_risdate"),
                'tbl_ris.risdate',
                DB::raw("DATE_FORMAT(tbl_ris.requestdate, '%b-%d-%Y') AS formatted_requestdate"),
                DB::raw("DATE_FORMAT(tbl_ris.apprvddate, '%b-%d-%Y') AS formatted_approveddate"),
                DB::raw("DATE_FORMAT(tbl_ris.issueddate, '%b-%d-%Y') AS formatted_issueddate"),
                'tbl_ris.apprvstat',
                'tbl_ris.issuedstat',
                'tbl_ris.status',
                'tbl_ris.ris_in_iar',
                'tbl_ris.transferred_to_pdims',
                'tbl_ris.transferred_at',
                'req.fullName AS requested_by',
                'issue.fullName AS issued_by',
                DB::raw('(SELECT COUNT(*)
                          FROM tbl_ris_details
                          WHERE tbl_ris_details.risid = tbl_ris.risid
                          AND tbl_ris_details.status = "A") as item_count'),
                DB::raw('(SELECT SUM(tbl_ris_release.releaseqty * tbl_ris_release.unitprice)
                          FROM tbl_ris_details
                          JOIN tbl_ris_release ON tbl_ris_release.risdetid = tbl_ris_details.risdetid
                          WHERE tbl_ris_details.risid = tbl_ris.risid
                          AND tbl_ris_details.status = "A"
                          AND tbl_ris_release.status = "A") as total_amount'),
            ])
            ->leftJoin('tbl_user AS req', 'req.userID', '=', 'tbl_ris.requestby')
            ->leftJoin('tbl_user AS issue', 'issue.userID', '=', 'tbl_ris.issuedby')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('tbl_ris_details')
                    ->join('tbl_items', 'tbl_items.itemid', '=', 'tbl_ris_details.itemid')
                    ->whereRaw('tbl_ris_details.risid = tbl_ris.risid')
                    ->where('tbl_items.catid', 9);
            })
            ->where('tbl_ris.officeID', $this->officeId)
            ->when($this->statusFilter !== 'all', function ($query) {
                if ($this->statusFilter === 'approved') {
                    return $query->where('tbl_ris.apprvstat', 'A');
                } elseif ($this->statusFilter === 'pending') {
                    return $query->where('tbl_ris.apprvstat', 'P');
                } elseif ($this->statusFilter === 'issued') {
                    return $query->where('tbl_ris.issuedstat', 'I');
                } elseif ($this->statusFilter === 'transferred') {
                    return $query->whereNotNull('tbl_ris.transferred_to_pdims');
                } elseif ($this->statusFilter === 'not-transferred') {
                    return $query->whereNull('tbl_ris.transferred_to_pdims');
                }
            })
            ->when($this->search, function ($query) {
                return $query->where(function ($query) {
                    $query->where('tbl_ris.risno', 'like', '%' . $this->search . '%')
                        ->orWhere('tbl_ris.purpose', 'like', '%' . $this->search . '%')
                        ->orWhere('req.fullName', 'like', '%' . $this->search . '%')
                        ->orWhere('issue.fullName', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.pharmacy.purchases.pims-ris-list', [
            'risItems' => $risItems,
        ])->layout('layouts.app', ['title' => 'PIMS RIS']);
    }
}
