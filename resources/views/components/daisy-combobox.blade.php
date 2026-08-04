@props([
    'label' => null,
    'options' => [],
    'optionValue' => 'id',
    'optionLabel' => 'name',
    'placeholder' => 'Select an option',
    'noResultsText' => 'No matching options',
    'clearable' => false,
])

@php
    $modelName = $attributes->wire('model')->value();
    $disabled = filter_var($attributes->get('disabled', false), FILTER_VALIDATE_BOOL);
    $required = filter_var($attributes->get('required', false), FILTER_VALIDATE_BOOL);
@endphp

<div
    {{ $attributes->whereStartsWith('class')->class('relative') }}
    x-data="{
        open: false,
        search: '',
        selected: @entangle($attributes->wire('model')),
        options: @js(collect($options)->values()),
        optionValue: @js($optionValue),
        optionLabel: @js($optionLabel),
        disabled: @js($disabled),

        selectedOption() {
            return this.options.find((option) => String(option[this.optionValue]) === String(this.selected))
        },

        selectedLabel() {
            return this.selectedOption()?.[this.optionLabel] ?? ''
        },

        filteredOptions() {
            const term = this.search.trim().toLocaleLowerCase()

            if (!term) {
                return this.options
            }

            return this.options.filter((option) =>
                String(option[this.optionLabel] ?? '').toLocaleLowerCase().includes(term)
            )
        },

        showOptions() {
            if (this.disabled) {
                return
            }

            this.open = true
            this.search = ''
            this.$nextTick(() => {
                this.$refs.search.focus()
                this.$refs.search.select()
            })
        },

        choose(option) {
            this.selected = option[this.optionValue]
            this.search = ''
            this.open = false
            this.$refs.search.blur()
        },

        reset() {
            this.selected = ''
            this.search = ''
            this.open = false
        },
    }"
    x-on:click.outside="open = false; search = ''"
    x-on:keydown.escape.window="open = false; search = ''"
>
    <fieldset class="fieldset py-0">
        @if ($label)
            <legend class="fieldset-legend mb-0.5">
                {{ $label }}
                @if ($required)
                    <span class="text-error">*</span>
                @endif
            </legend>
        @endif

        <div class="relative">
            <input
                x-ref="search"
                type="text"
                class="input input-bordered w-full pr-20"
                x-bind:value="open ? search : selectedLabel()"
                x-on:focus="showOptions()"
                x-on:click="showOptions()"
                x-on:input="search = $event.target.value; open = true"
                x-on:keydown.arrow-down.prevent="open = true"
                x-bind:placeholder="@js($placeholder)"
                x-bind:disabled="disabled"
                @if ($required) required @endif
                autocomplete="off"
                role="combobox"
                x-bind:aria-expanded="open"
            />

            <div class="absolute inset-y-0 right-2 flex items-center gap-1">
                @if ($clearable)
                    <button
                        type="button"
                        class="btn btn-ghost btn-circle btn-xs"
                        x-show="selected !== null && selected !== '' && !disabled"
                        x-on:click.stop="reset()"
                        aria-label="Clear selection"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.8" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif

                <button
                    type="button"
                    class="btn btn-ghost btn-circle btn-xs"
                    x-on:click.stop="open ? (open = false) : showOptions()"
                    x-bind:disabled="disabled"
                    aria-label="Toggle options"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="1.8" stroke="currentColor" class="h-4 w-4 transition-transform"
                        x-bind:class="{ 'rotate-180': open }" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>
            </div>
        </div>

        <div
            x-cloak
            x-show="open"
            x-transition.opacity.duration.100ms
            class="absolute z-50 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-base-300 bg-base-100 p-1 shadow-xl"
            role="listbox"
        >
            <template x-for="option in filteredOptions()" x-bind:key="option[optionValue]">
                <button
                    type="button"
                    class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm hover:bg-base-200 focus:bg-base-200 focus:outline-none"
                    x-bind:class="{ 'bg-primary/10 font-semibold text-primary': String(option[optionValue]) === String(selected) }"
                    x-on:click="choose(option)"
                    role="option"
                    x-bind:aria-selected="String(option[optionValue]) === String(selected)"
                >
                    <span class="truncate" x-text="option[optionLabel]"></span>
                    <svg x-show="String(option[optionValue]) === String(selected)"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor" class="ml-2 h-4 w-4 shrink-0" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </button>
            </template>

            <div x-show="filteredOptions().length === 0" class="px-3 py-6 text-center text-sm text-base-content/60">
                {{ $noResultsText }}
            </div>
        </div>

        @if ($modelName && $errors->has($modelName))
            <div class="mt-1 text-sm text-error">{{ $errors->first($modelName) }}</div>
        @endif
    </fieldset>
</div>
