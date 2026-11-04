<button type="button" x-data="pharmacyAppearance" @click="toggleTheme()"
    :aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'"
    aria-label="Switch appearance" {{ $attributes->class(['btn btn-circle btn-ghost']) }}>
    <svg x-show="!dark" aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 2.25 12c0 5.385 4.365 9.75 9.75 9.75a9.753 9.753 0 0 0 9.752-6.748Z" /></svg>
    <svg x-show="dark" x-cloak aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m6.364 1.136-1.06 1.06M21 12h-1.5m-1.136 6.364-1.06-1.06M12 21v-1.5m-6.364-1.136 1.06-1.06M3 12h1.5m1.136-6.364 1.06 1.06M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" /></svg>
</button>
