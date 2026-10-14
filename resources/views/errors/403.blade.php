@extends('errors::minimal')

@section('title', __('Forbidden'))
@section('code', '403')
@section('message')
    @if (request()->attributes->get('preview_role'))
        <div>This page is unavailable for the role you are previewing.</div>
        <a href="{{ route('dashboard') }}" style="display: inline-block; margin-top: 12px; text-decoration: underline;">Return to Dashboard to switch roles or exit preview</a>
    @else
        {{ __($exception->getMessage() ?: 'Forbidden') }}
    @endif
@endsection
