@extends('layouts.app')

@section('main_max_class', 'max-w-md')

@section('title', 'Create account — '.config('app.name'))

@section('content')
    <header class="page-header-card">
        <p class="text-accent-eyebrow text-[0.65rem] font-semibold uppercase tracking-[0.2em]">Get started</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-50">Create account</h1>
        <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            Your courses, progress, and roadmaps stay private to your account.
        </p>
    </header>

    <div class="auth-panel mt-8 rounded-3xl border border-slate-200/95 bg-white/90 p-6 shadow-lg shadow-slate-300/20 ring-1 ring-slate-200/85 dark:border-slate-800/90 dark:bg-slate-900/55 dark:shadow-black/20 dark:ring-slate-800/70 sm:p-8">
        @include('auth.partials.google-sign-in')

        <form action="{{ route('register') }}" method="post">
            @csrf

            <div class="space-y-5">
                <div>
                    <label for="name" class="mb-2 block text-sm font-semibold text-slate-800 dark:text-slate-200">Name</label>
                    <input
                        type="text"
                        name="name"
                        id="name"
                        value="{{ old('name') }}"
                        required
                        autofocus
                        autocomplete="name"
                        maxlength="255"
                        class="input-field"
                    />
                </div>

                <div>
                    <label for="email" class="mb-2 block text-sm font-semibold text-slate-800 dark:text-slate-200">Email</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        value="{{ old('email') }}"
                        required
                        autocomplete="username"
                        class="input-field"
                    />
                </div>

                <div>
                    <label for="password" class="mb-2 block text-sm font-semibold text-slate-800 dark:text-slate-200">Password</label>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        required
                        autocomplete="new-password"
                        class="input-field"
                    />
                </div>

                <div>
                    <label for="password_confirmation" class="mb-2 block text-sm font-semibold text-slate-800 dark:text-slate-200">Confirm password</label>
                    <input
                        type="password"
                        name="password_confirmation"
                        id="password_confirmation"
                        required
                        autocomplete="new-password"
                        class="input-field"
                    />
                </div>
            </div>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <button type="submit" class="btn-primary w-full sm:w-auto">Create account</button>
                <p class="text-center text-sm text-slate-600 dark:text-slate-400 sm:text-right">
                    Already have an account?
                    <a href="{{ route('login') }}" class="link-accent">Sign in</a>
                </p>
            </div>
        </form>
    </div>
@endsection
