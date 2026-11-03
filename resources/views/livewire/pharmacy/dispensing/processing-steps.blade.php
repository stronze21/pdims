<ol class="space-y-4" aria-label="Processing steps">
    @foreach ($steps as $index => $label)
        <li class="flex items-start gap-3 {{ $index > $active ? 'text-base-content/45' : '' }}" @if ($index === $active) aria-current="step" @endif>
            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center" aria-hidden="true">
                @if ($index < $active)
                    <svg class="h-5 w-5 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6" /></svg>
                @elseif ($index === $active)
                    <span x-show="processingBusy" class="loading loading-spinner loading-xs text-primary motion-reduce:animate-none"></span>
                @else
                    <span class="h-2 w-2 rounded-full bg-base-content/25"></span>
                @endif
            </span>
            <div>
                <span class="text-sm {{ $index === $active ? 'font-semibold' : '' }}">{{ $label }}</span>
                <span class="sr-only">{{ $index < $active ? 'Completed' : ($index === $active ? 'In progress' : 'Upcoming') }}</span>
                @if ($index === $active && $detail)
                    <p class="mt-1 text-xs text-base-content/65">{{ $detail }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
