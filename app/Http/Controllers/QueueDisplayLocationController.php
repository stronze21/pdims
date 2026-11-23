<?php

namespace App\Http\Controllers;

use App\Models\PharmLocation;
use Illuminate\Http\Request;

class QueueDisplayLocationController extends Controller
{
    public function __invoke(Request $request)
    {
        $locations = PharmLocation::orderBy('description')->get(['id', 'description']);

        if ($request->has('location')) {
            $validated = $request->validate(['location' => ['required', 'integer']]);
            $location = $locations->firstWhere('id', $validated['location']);

            if (! $location) {
                return back()->withErrors(['location' => 'Select an available pharmacy location.']);
            }

            return redirect()->route('queue.display', ['locationCode' => $location->id]);
        }

        return view('auth.queue-display-location', compact('locations'));
    }
}
