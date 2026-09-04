<?php

namespace App\Livewire\Portal;

use App\Models\Portal\Announcement;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class ManageAnnouncements extends Component
{
    use Toast, WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public bool $tableAvailable = true;
    public bool $formModal = false;
    public bool $deleteModal = false;
    public ?int $editingId = null;
    public ?int $selectedId = null;

    public string $title = '';
    public string $body = '';
    public string $imageUrl = '';
    public string $linkUrl = '';
    public string $publishedAt = '';
    public string $endsAt = '';
    public bool $isActive = true;
    public int $sortOrder = 0;

    public function mount(): void
    {
        $this->tableAvailable = Schema::connection('portal')->hasTable('announcements');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        if (! $this->tableAvailable) {
            $this->warning('Announcements table is missing. Run the Portal announcements migration first.');
            return;
        }

        $this->resetForm();
        $this->formModal = true;
    }

    public function openEditModal(int $id): void
    {
        $item = Announcement::find($id);
        if (! $item) {
            $this->error('Announcement not found.');
            return;
        }

        $this->editingId = $item->id;
        $this->title = $item->title;
        $this->body = $item->body ?? '';
        $this->imageUrl = $item->image_url ?? '';
        $this->linkUrl = $item->link_url ?? '';
        $this->publishedAt = $item->published_at?->format('Y-m-d\TH:i') ?? '';
        $this->endsAt = $item->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->isActive = (bool) $item->is_active;
        $this->sortOrder = (int) $item->sort_order;
        $this->formModal = true;
    }

    public function save(): void
    {
        if (! $this->tableAvailable) {
            $this->warning('Announcements table is missing. Run the Portal announcements migration first.');
            return;
        }

        $this->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'imageUrl' => 'nullable|string|max:500',
            'linkUrl' => 'nullable|string|max:500',
            'publishedAt' => 'nullable|date',
            'endsAt' => 'nullable|date|after_or_equal:publishedAt',
            'isActive' => 'boolean',
            'sortOrder' => 'integer|min:0',
        ]);

        $payload = [
            'title' => trim($this->title),
            'body' => trim($this->body) !== '' ? trim($this->body) : null,
            'image_url' => trim($this->imageUrl) !== '' ? trim($this->imageUrl) : null,
            'link_url' => trim($this->linkUrl) !== '' ? trim($this->linkUrl) : null,
            'published_at' => $this->publishedAt !== '' ? $this->publishedAt : null,
            'ends_at' => $this->endsAt !== '' ? $this->endsAt : null,
            'is_active' => $this->isActive,
            'sort_order' => $this->sortOrder,
        ];

        if ($this->editingId) {
            Announcement::findOrFail($this->editingId)->update($payload);
            $this->success('Announcement updated.');
        } else {
            $payload['created_by'] = auth()->user()->name ?? auth()->id();
            Announcement::create($payload);
            $this->success('Announcement published to the patient Home banner.');
        }

        $this->formModal = false;
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $item = Announcement::find($id);
        if (! $item) {
            return;
        }

        $item->update(['is_active' => ! $item->is_active]);
        $this->success($item->is_active ? 'Announcement is now visible.' : 'Announcement hidden from Home.');
    }

    public function openDeleteModal(int $id): void
    {
        $this->selectedId = $id;
        $this->deleteModal = true;
    }

    public function deleteAnnouncement(): void
    {
        $item = Announcement::find($this->selectedId);
        if ($item) {
            $item->delete();
            $this->success('Announcement deleted.');
        }

        $this->deleteModal = false;
        $this->selectedId = null;
    }

    public function render()
    {
        $items = collect();

        if ($this->tableAvailable) {
            $items = Announcement::query()
                ->when($this->search !== '', function ($query) {
                    $query->where(function ($inner) {
                        $inner->where('title', 'LIKE', '%'.$this->search.'%')
                            ->orWhere('body', 'LIKE', '%'.$this->search.'%');
                    });
                })
                ->when($this->statusFilter === 'active', fn ($query) => $query->where('is_active', true))
                ->when($this->statusFilter === 'hidden', fn ($query) => $query->where('is_active', false))
                ->orderBy('sort_order')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(15);
        }

        return view('livewire.portal.manage-announcements', [
            'items' => $items,
        ])->layout('layouts.portal');
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->body = '';
        $this->imageUrl = '';
        $this->linkUrl = '';
        $this->publishedAt = now()->format('Y-m-d\TH:i');
        $this->endsAt = '';
        $this->isActive = true;
        $this->sortOrder = 0;
    }
}
