@extends('layouts.app')

@section('main_max_class', 'max-w-4xl')

@section('content')
    <header class="mb-6">
        <p class="section-eyebrow text-accent">Profile</p>
        <h1 class="home-page-title mt-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
            {{ auth()->user()->name }}
        </h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-500">{{ auth()->user()->email }}</p>
    </header>

    @include('profile._tabs', ['activeTab' => $activeTab ?? 'account'])

    <div class="profile-tab-panel mt-8">
        @yield('profile_panel')
    </div>
@endsection
