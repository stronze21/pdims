<x-guest-layout>
    <main class="min-h-screen flex items-center justify-center px-5 py-12">
        <section class="w-full max-w-md bg-base-100 rounded-2xl p-7 sm:p-10" aria-labelledby="tv-heading">
            <x-mary-icon name="o-tv" class="w-8 h-8 text-primary mb-5" />
            <h1 id="tv-heading" class="text-2xl font-semibold">Open TV display</h1>
            <p class="mt-3 text-base-content/75">Choose the pharmacy location to show on this screen. No sign-in is required.</p>

            <form method="GET" action="{{ route('queue.display-location') }}" class="mt-7 space-y-5">
                <div>
                    <label for="tv-location" class="block text-sm font-semibold mb-2">Pharmacy location</label>
                    <select id="tv-location" name="location" class="select select-bordered w-full" required @if($errors->has('location')) aria-invalid="true" aria-describedby="location-error" @endif>
                        <option value="" disabled selected>Select a pharmacy location</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('location') == $location->id)>{{ $location->description }}</option>
                        @endforeach
                    </select>
                    @error('location')
                        <p id="location-error" class="text-error text-sm mt-2" role="alert">{{ $message }}</p>
                    @enderror
                </div>
                @if ($locations->isEmpty())
                    <p role="status">No pharmacy locations are available. Contact Pharmacy Administration.</p>
                @endif
                <button type="submit" class="btn btn-primary w-full" @disabled($locations->isEmpty())>Show TV display</button>
            </form>
            <a href="{{ route('login') }}" class="link link-primary inline-block mt-6 text-sm">Back to sign in</a>
        </section>
    </main>
</x-guest-layout>
