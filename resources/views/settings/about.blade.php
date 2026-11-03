{{-- resources/views/settings/about.blade.php --}}
@extends('layouts.settings')

@section('title', 'About | TestOps')

@section('settings-content')
    <x-settings.placeholder
        title="About"
        description="Platform, version, and environment information for this TestOps installation will appear here."
    />
@endsection
