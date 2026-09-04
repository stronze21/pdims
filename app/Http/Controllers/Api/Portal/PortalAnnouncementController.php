<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\Announcement;
use Illuminate\Http\Request;

class PortalAnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::query()
            ->current()
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        return response()->json($announcements);
    }

    public function all()
    {
        $announcements = Announcement::query()
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        return response()->json($announcements);
    }

    public function store(Request $request)
    {
        $announcement = Announcement::create($this->validated($request));

        return response()->json([
            'message' => 'Announcement created successfully.',
            'announcement' => $announcement,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->update($this->validated($request, true));

        return response()->json([
            'message' => 'Announcement updated successfully.',
            'announcement' => $announcement,
        ]);
    }

    public function destroy($id)
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->delete();

        return response()->json([
            'message' => 'Announcement deleted successfully.',
        ]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';

        return $request->validate([
            'title' => $required.'|string|max:255',
            'body' => 'nullable|string',
            'image_url' => 'nullable|string|max:500',
            'link_url' => 'nullable|string|max:500',
            'published_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:published_at',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);
    }
}
