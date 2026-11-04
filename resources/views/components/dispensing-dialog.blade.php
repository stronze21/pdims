@props(['model', 'title', 'boxClass' => '', 'closeMethod' => null, 'drawer' => false])

<dialog id="dispensing-{{ $model }}" wire:key="dispensing-{{ $model }}" wire:ignore.self
    class="modal backdrop-blur {{ $drawer ? 'place-items-start' : '' }}"
    aria-labelledby="dispensing-{{ $model }}-title"
    x-data="{ model: @js($model), closeMethod: @js($closeMethod) }"
    x-effect="syncPreparedDialog($el, model)"
    x-on:cancel.prevent="dismissPreparedDialog(model, closeMethod)">
    <div class="modal-box {{ $drawer ? 'h-dvh max-h-dvh w-full rounded-none' : '' }} {{ $boxClass }}">
        <h2 id="dispensing-{{ $model }}-title" class="mb-5 pr-8 text-xl font-bold" tabindex="-1" autofocus>{{ $title }}</h2>
        <button type="button" class="btn btn-circle btn-sm btn-ghost absolute end-2 top-2"
            aria-label="Close" x-bind:disabled="pendingDialog?.model === model"
            x-on:click="dismissPreparedDialog(model, closeMethod)">×</button>

        <div x-show="pendingDialog?.model === model" x-cloak class="space-y-4" role="status">
            <span class="sr-only">Loading {{ $title }}</span>
            <div class="skeleton h-4 w-32"></div>
            <div class="skeleton h-12 w-full"></div>
            <div class="skeleton h-28 w-full"></div>
        </div>
        <p x-show="failedDialog === model" x-cloak class="text-sm text-error" role="alert">
            Could not load this dialog. Close it and try again.
        </p>
        <div x-show="$wire[model] && pendingDialog?.model !== model && failedDialog !== model" x-cloak>
            {{ $slot }}
            @isset($actions)
                <div class="modal-action">{{ $actions }}</div>
            @endisset
        </div>
    </div>
    @unless($closeMethod === 'closeReturnModal')
        <form method="dialog" class="modal-backdrop" x-on:submit.prevent="dismissPreparedDialog(model, closeMethod)">
            <button type="submit" tabindex="-1" aria-label="Close dialog"></button>
        </form>
    @endunless
</dialog>
