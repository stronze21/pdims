<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-base-100 border border-base-300 rounded-md font-semibold text-xs text-base-content/85  shadow-xs hover:bg-base-200 focus:outline-hidden focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
