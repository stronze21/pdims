@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'input w-full border-base-300 bg-base-100 text-base-content focus:border-primary focus:ring-primary']) !!}>
