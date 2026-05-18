<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700" rel="stylesheet" />
    <script>
        (function () {
            try {
                var k = 'home-teacher-theme';
                var stored = localStorage.getItem(k);
                var dark = stored === 'dark' ? true : stored === 'light' ? false : window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="app-backdrop min-h-full text-slate-800 antialiased selection:bg-sky-300/50 selection:text-slate-900 dark:text-slate-100/95 dark:selection:bg-sky-500/30 dark:selection:text-white">
    <header class="app-header-bar sticky top-0 z-50 backdrop-blur-xl supports-[backdrop-filter]:bg-white/70 dark:supports-[backdrop-filter]:bg-slate-950/70">
        {{-- Header width is fixed so the bar lines up on every page; main still uses @section('main_max_class') below. --}}
        <div class="app-header-inner mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3.5 sm:gap-4 sm:px-6 sm:py-4">
            <a href="{{ route('home') }}" class="group flex min-w-0 items-center gap-3" aria-label="{{ config('app.name') }}, home">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-sky-400 to-blue-600 text-lg shadow-lg shadow-sky-300/55 ring-1 ring-white/30 transition group-hover:shadow-sky-500/25 dark:shadow-blue-950/45 dark:ring-white/20 dark:group-hover:shadow-sky-500/20" aria-hidden="true">
                    <span class="text-sm font-bold text-slate-950">▶</span>
                </span>
                <span class="truncate text-lg font-semibold tracking-tight text-slate-900 transition group-hover:text-slate-950 dark:text-slate-50 dark:group-hover:text-white">
                    {{ config('app.name') }}
                </span>
            </a>
            <nav class="flex max-w-[62%] flex-wrap items-center justify-end gap-0.5 sm:max-w-none sm:gap-1">
                @auth
                    <a href="{{ route('home') }}" class="nav-pill text-[13px] sm:text-sm">Home</a>
                    <a href="{{ route('courses.index') }}" class="nav-pill text-[13px] sm:text-sm">Courses</a>
                    <a href="{{ route('roadmaps.index') }}" class="nav-pill text-[13px] sm:text-sm">Roadmaps</a>
                    <a href="{{ route('playground.show') }}" class="nav-pill text-[13px] sm:text-sm">Playground</a>
                @endauth
                <button
                    type="button"
                    id="theme-toggle"
                    class="theme-toggle-btn"
                    aria-label="Toggle color theme"
                    aria-pressed="false"
                    title="Toggle light / dark mode"
                >
                    <svg data-theme-when="light" class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                    </svg>
                    <svg data-theme-when="dark" class="hidden size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                    </svg>
                </button>
                @auth
                    <a
                        href="{{ route('profile.index') }}"
                        class="nav-pill hidden max-w-[8rem] truncate text-[13px] sm:inline lg:max-w-[12rem] sm:text-sm"
                        title="{{ auth()->user()->email }}"
                    >
                        {{ auth()->user()->name }}
                    </a>
                    <form action="{{ route('logout') }}" method="post" class="inline">
                        @csrf
                        <button type="submit" class="nav-pill text-[13px] sm:text-sm">Sign out</button>
                    </form>
                    <a
                        href="{{ route('courses.create') }}"
                        class="ml-1 inline-flex items-center justify-center rounded-full bg-gradient-to-r from-sky-500 to-blue-600 px-3.5 py-2 text-xs font-semibold text-white shadow-md shadow-sky-400/35 ring-1 ring-white/20 transition hover:from-sky-400 hover:to-blue-500 dark:shadow-blue-950/40 dark:ring-white/15 dark:hover:shadow-sky-900/50 sm:ml-2 sm:px-4 sm:text-sm"
                    >
                        <span class="sm:hidden">Add</span>
                        <span class="hidden sm:inline">Add course</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="nav-pill text-[13px] sm:text-sm">Sign in</a>
                    <a
                        href="{{ route('register') }}"
                        class="ml-1 inline-flex items-center justify-center rounded-full bg-gradient-to-r from-sky-500 to-blue-600 px-3.5 py-2 text-xs font-semibold text-white shadow-md shadow-sky-400/35 ring-1 ring-white/20 transition hover:from-sky-400 hover:to-blue-500 dark:shadow-blue-950/40 dark:ring-white/15 dark:hover:shadow-sky-900/50 sm:ml-2 sm:px-4 sm:text-sm"
                    >
                        Sign up
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="mx-auto @yield('main_max_class', 'max-w-5xl') px-4 py-8 sm:px-6 sm:py-11 lg:py-14">
        @if (session('status'))
            <div class="mb-8 rounded-2xl border border-sky-200/95 bg-gradient-to-r from-sky-50 via-white to-blue-50/95 px-5 py-4 text-sm text-sky-950 shadow-lg shadow-sky-200/40 ring-1 ring-sky-100/80 dark:border-sky-800/50 dark:from-sky-950/45 dark:via-slate-950/20 dark:to-blue-950/35 dark:text-sky-100 dark:shadow-sky-950/25 dark:ring-transparent">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-8 rounded-2xl border border-rose-200/95 bg-rose-50/90 px-5 py-4 text-sm text-rose-900 shadow-lg shadow-rose-200/35 ring-1 ring-rose-100 dark:border-rose-900/60 dark:bg-rose-950/35 dark:text-rose-100 dark:shadow-rose-950/20 dark:ring-transparent">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
