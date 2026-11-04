<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-primary-content  hover:bg-primary/90 focus:bg-primary/90 active:bg-primary/80 focus:outline-hidden focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
