<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\EmergencyContact;
use Illuminate\Http\Request;

class PortalEmergencyContactController extends Controller
{
    /**
     * List all active emergency contacts (public, no auth required).
     */
    public function index()
    {
        $contacts = EmergencyContact::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json($contacts);
    }

    /**
     * List all emergency contacts including inactive (admin).
     */
    public function all()
    {
        $contacts = EmergencyContact::orderBy('sort_order')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json($contacts);
    }

    /**
     * Store a new emergency contact.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'label' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $contact = EmergencyContact::create($validated);

        return response()->json([
            'message' => 'Emergency contact created successfully.',
            'contact' => $contact,
        ], 201);
    }

    /**
     * Update an existing emergency contact.
     */
    public function update(Request $request, $id)
    {
        $contact = EmergencyContact::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:255',
            'label' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $contact->update($validated);

        return response()->json([
            'message' => 'Emergency contact updated successfully.',
            'contact' => $contact,
        ]);
    }

    /**
     * Delete an emergency contact (soft delete).
     */
    public function destroy($id)
    {
        $contact = EmergencyContact::findOrFail($id);
        $contact->delete();

        return response()->json([
            'message' => 'Emergency contact deleted successfully.',
        ]);
    }
}
