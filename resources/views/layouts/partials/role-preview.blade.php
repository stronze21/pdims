@if (request()->attributes->get('can_preview_roles'))
    @php($previewRole = request()->attributes->get('preview_role'))
    <section aria-label="Role preview" class="border-b border-base-300 bg-base-100 px-4 py-3 lg:px-6">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-48">
                <p class="text-sm font-semibold">{{ $previewRole ? 'Previewing: '.$previewRole->name : 'Role preview' }}</p>
                <p class="text-xs text-base-content/70">{{ $previewRole ? 'Menus and permissions reflect this role. Actions affect live data using your account.' : 'See the app with another role’s menus and permissions.' }}</p>
            </div>
            <form method="POST" action="{{ route('role-preview.store') }}" class="flex flex-wrap items-center gap-2">
                @csrf
                <label for="role-preview-select" class="sr-only">Role to preview</label>
                <select id="role-preview-select" name="role_id" class="select select-bordered select-sm w-48" required>
                    <option value="" disabled @selected(! $previewRole)>Choose a role</option>
                    @foreach (\App\Models\Role::where('guard_name', 'web')->where('name', '!=', 'Super Admin')->orderBy('name')->get() as $previewOption)
                        <option value="{{ $previewOption->id }}" @selected($previewRole?->id === $previewOption->id)>{{ $previewOption->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary btn-sm">{{ $previewRole ? 'Switch role' : 'Preview role' }}</button>
            </form>
            @if ($previewRole)
                <form method="POST" action="{{ route('role-preview.destroy') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline btn-sm">Exit preview</button>
                </form>
            @endif
        </div>
    </section>
@endif
