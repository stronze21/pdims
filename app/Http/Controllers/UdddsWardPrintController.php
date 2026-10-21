<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Pharmacy\UdddsService;
use App\Services\Pharmacy\UdddsWardPrint;

class UdddsWardPrintController extends Controller
{
    public function __invoke(Request $request, UdddsService $service)
    {
        $filters = $request->validate(['date' => 'required|date_format:Y-m-d', 'ward' => 'nullable|string|max:30']);
        $location = auth()->user()->pharm_location_id;
        abort_unless($location, 403);
        $items = $service->processedWardItemsForDate($filters['ward'] ?? null, $location, $filters['date']);
        return response()->view('prints.uddds-ward-orders', [
            'wards' => UdddsWardPrint::group($items), 'date' => $filters['date'],
        ])->header('Cache-Control', 'no-store, private');
    }
}
