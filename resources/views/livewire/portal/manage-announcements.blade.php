<div class="flex flex-col px-5 py-5 mx-auto max-w-screen">
    <x-mary-header title="News & Announcements" subtitle="These cards appear in the Salun-at Home banner carousel" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-mary-input placeholder="Search title or body..." wire:model.live.debounce.300ms="search" icon="o-magnifying-glass" clearable class="w-72" />
            <x-mary-button label="New announcement" icon="o-plus" class="btn-primary" wire:click="openCreateModal" />
        </x-slot:middle>
    </x-mary-header>

    <div class="flex gap-2 mb-4">
        @foreach (['all' => 'All', 'active' => 'Visible', 'hidden' => 'Hidden'] as $value => $label)
            <button type="button" wire:click="$set('statusFilter', '{{ $value }}')" class="btn btn-sm {{ $statusFilter === $value ? 'btn-primary' : 'btn-ghost' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @unless ($tableAvailable)
        <div class="alert mb-4 border border-warning/30 bg-warning/10">
            <span>The `announcements` table is not on the Portal database yet. Run the Portal announcements migration to activate this module.</span>
        </div>
    @endunless

    <div class="bg-base-100 rounded-2xl shadow-xl border border-base-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead class="bg-base-200">
                    <tr>
                        <th>Order</th>
                        <th>Announcement</th>
                        <th>Schedule</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr class="hover:bg-base-200/70 transition-colors border-b border-base-300">
                            <td class="py-3 px-4 text-sm font-mono">{{ $item->sort_order }}</td>
                            <td class="py-3 px-4">
                                <div class="font-semibold text-sm">{{ $item->title }}</div>
                                @if ($item->body)
                                    <div class="text-xs text-base-content/60 truncate max-w-[320px]">{{ $item->body }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-xs">
                                <div>{{ $item->published_at?->format('M d, Y g:i A') ?? 'Immediately' }}</div>
                                <div class="text-base-content/60">{{ $item->ends_at ? 'Until '.$item->ends_at->format('M d, Y g:i A') : 'No end date' }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="badge badge-sm {{ $item->is_active ? 'badge-success' : 'badge-ghost' }}">{{ $item->is_active ? 'Visible' : 'Hidden' }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex justify-center gap-1">
                                    <button type="button" class="btn btn-xs btn-ghost" wire:click="toggleActive({{ $item->id }})">{{ $item->is_active ? 'Hide' : 'Show' }}</button>
                                    <button type="button" class="btn btn-xs btn-info" wire:click="openEditModal({{ $item->id }})">Edit</button>
                                    <button type="button" class="btn btn-xs btn-error" wire:click="openDeleteModal({{ $item->id }})">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-16 text-center text-base-content/60">No announcements yet. Create one to fill the Home banner.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($tableAvailable)
            <div class="p-4">{{ $items->links() }}</div>
        @endif
    </div>

    <x-mary-modal wire:model="formModal" title="{{ $editingId ? 'Edit announcement' : 'New announcement' }}" box-class="max-w-2xl">
        <div class="space-y-4">
            <x-mary-input label="Title" wire:model="title" placeholder="OPD Hours · Mon–Sat" />
            <x-mary-textarea label="Body" wire:model="body" rows="3" placeholder="Walk-in clinics are open 7:00 AM to 5:00 PM." />
            <x-mary-input label="Image URL" wire:model="imageUrl" placeholder="https://..." />
            <x-mary-input label="Link URL" wire:model="linkUrl" placeholder="/departments or https://..." />
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-mary-input label="Publish at" type="datetime-local" wire:model="publishedAt" />
                <x-mary-input label="End at" type="datetime-local" wire:model="endsAt" />
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-mary-input label="Sort order" type="number" wire:model="sortOrder" />
                <x-mary-checkbox label="Visible on Home" wire:model="isActive" />
            </div>
        </div>
        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('formModal', false)" />
            <x-mary-button label="Save" class="btn-primary" wire:click="save" spinner="save" />
        </x-slot:actions>
    </x-mary-modal>

    <x-mary-modal wire:model="deleteModal" title="Delete announcement">
        <p>Remove this announcement from the Home banner?</p>
        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('deleteModal', false)" />
            <x-mary-button label="Delete" class="btn-error" wire:click="deleteAnnouncement" spinner="deleteAnnouncement" />
        </x-slot:actions>
    </x-mary-modal>
</div>
