@if (request()->attributes->get('can_preview_roles'))
    @php($previewRole = request()->attributes->get('preview_role'))
    @if ($previewRole)
        <section aria-label="Active role preview" class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 bg-base-100 px-4 py-2 lg:px-6">
            <div>
                <p class="text-sm font-semibold">Previewing: {{ $previewRole->name }}</p>
                <p class="text-xs text-base-content/70">Actions affect live data using your account.</p>
            </div>
            <form method="POST" action="{{ route('role-preview.destroy') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline btn-sm">Exit preview</button>
            </form>
        </section>
    @endif

    <dialog id="role-preview-modal" class="modal" aria-labelledby="role-preview-title" aria-describedby="role-preview-description">
        <div class="modal-box max-w-md">
            <h2 id="role-preview-title" class="text-xl font-semibold">Role preview</h2>
            <p id="role-preview-description" class="mt-2 text-sm text-base-content/70">
                See the app with another role’s menus and permissions. Actions affect live data using your account.
            </p>
            @if ($previewRole)
                <p class="mt-4 text-sm">Currently previewing: <strong>{{ $previewRole->name }}</strong></p>
            @endif
            <form method="POST" action="{{ route('role-preview.store') }}" class="mt-6">
                @csrf
                <label for="role-preview-select" class="mb-2 block text-sm font-medium">Role to preview</label>
                <select id="role-preview-select" name="role_id" class="select select-bordered w-full" required autofocus>
                    <option value="" disabled @selected(! $previewRole)>Choose a role</option>
                    @foreach (\App\Models\Role::where('guard_name', 'web')->where('name', '!=', 'Super Admin')->orderBy('name')->get() as $previewOption)
                        <option value="{{ $previewOption->id }}" @selected($previewRole?->id === $previewOption->id)>{{ $previewOption->name }}</option>
                    @endforeach
                </select>
                <div class="modal-action">
                    <button type="button" class="btn btn-ghost" x-data x-on:click="document.getElementById('role-preview-modal').close()">Cancel</button>
                    <button type="submit" class="btn btn-primary">{{ $previewRole ? 'Switch role' : 'Preview role' }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button type="submit">Close role preview</button>
        </form>
    </dialog>
@endif
