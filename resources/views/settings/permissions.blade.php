{{-- resources/views/settings/permissions.blade.php --}}
@extends('layouts.settings')

@section('title', 'Permissions | TestOps')

@section('settings-content')
    <x-settings.placeholder
        title="Permissions"
        description="Permission management will allow Super Admins to define global permissions assigned to roles."
    />
@endsection
