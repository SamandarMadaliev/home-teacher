@extends('layouts.app')

@section('main_max_class', 'max-w-md')

@section('title', 'Sign in — '.config('app.name'))

@section('content')
    <header class="rounded-3xl border border-slate-200/95 bg-gradient-to-br from-white via-white to-sky-50/55 p-6 shadow-lg shadow-slate-300/25 ring-1 ring-sky-100/45 dark:border-slate-800/90 dark:from-slate-900/88 dark:via-slate-900/75 dark:to-sky-950/30 dark:shadow-black/25 dark:ring-sky-950/30 sm:p-7">
        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-sky-700 dark:text-sky-400/90">Welcome back</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-50">Sign in</h1>
        <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            Access your courses, roadmaps, and watch progress.
        </p>
    </header>

    <div class="auth-panel mt-8 rounded-3xl border border-slate-200/95 bg-white/90 p-6 shadow-lg shadow-slate-300/20 ring-1 ring-slate-200/85 dark:border-slate-800/90 dark:bg-slate-900/55 dark:shadow-black/20 dark:ring-slate-800/70 sm:p-8">
        @include('auth.partials.google-sign-in')

        <form action="{{ route('login') }}" method="post">
            @csrf

            <div class="space-y-5">
                <div>
                    <label for="email" class="mb-2 block text-sm font-semibold text-slate-800 dark:text-slate-200">Email</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
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
                        autocomplete="current-password"
                        class="input-field"
                    />
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input
                        type="checkbox"
                        name="remember"
                        value="1"
                        @checked(old('remember'))
                        class="size-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500/40 dark:border-slate-600 dark:bg-slate-900"
                    />
                    Remember me
                </label>
            </div>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <button type="submit" class="btn-primary w-full sm:w-auto">Sign in</button>
                <p class="text-center text-sm text-slate-600 dark:text-slate-400 sm:text-right">
                    No account?
                    <a href="{{ route('register') }}" class="font-semibold text-sky-600 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300">Create one</a>
                </p>
            </div>
        </form>
    </div>
@endsection
