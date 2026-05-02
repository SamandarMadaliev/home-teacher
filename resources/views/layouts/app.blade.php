<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css'])
    @stack('head')
</head>
<body class="app-backdrop min-h-full text-slate-100/95 antialiased selection:bg-sky-500/30 selection:text-white">
    <header class="app-header-bar sticky top-0 z-50 backdrop-blur-xl supports-[backdrop-filter]:bg-slate-950/70">
        <div class="mx-auto @yield('main_max_class', 'max-w-5xl') flex items-center justify-between gap-3 px-4 py-3.5 sm:gap-4 sm:px-6 sm:py-4">
            <a href="{{ route('home') }}" class="group flex min-w-0 items-center gap-3" aria-label="{{ config('app.name') }}, home">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-sky-400 to-blue-600 text-lg shadow-lg shadow-blue-950/45 ring-1 ring-white/20 transition group-hover:shadow-sky-500/20" aria-hidden="true">
                    <span class="text-sm font-bold text-slate-950">▶</span>
                </span>
                <span class="truncate text-lg font-semibold tracking-tight text-slate-50 transition group-hover:text-white">
                    {{ config('app.name') }}
                </span>
            </a>
            <nav class="flex max-w-[62%] flex-wrap items-center justify-end gap-0.5 sm:max-w-none sm:gap-1">
                <a href="{{ route('home') }}" class="nav-pill text-[13px] sm:text-sm">Home</a>
                <a href="{{ route('courses.index') }}" class="nav-pill text-[13px] sm:text-sm">Courses</a>
                <a href="{{ route('roadmaps.index') }}" class="nav-pill text-[13px] sm:text-sm">Roadmaps</a>
                <a
                    href="{{ route('courses.create') }}"
                    class="ml-1 inline-flex items-center justify-center rounded-full bg-gradient-to-r from-sky-500 to-blue-600 px-3.5 py-2 text-xs font-semibold text-white shadow-md shadow-blue-950/40 ring-1 ring-white/15 transition hover:from-sky-400 hover:to-blue-500 sm:ml-2 sm:px-4 sm:text-sm"
                >
                    <span class="sm:hidden">Add</span>
                    <span class="hidden sm:inline">Add course</span>
                </a>
            </nav>
        </div>
    </header>

    <main class="mx-auto @yield('main_max_class', 'max-w-5xl') px-4 py-8 sm:px-6 sm:py-11 lg:py-14">
        @if (session('status'))
            <div class="mb-8 rounded-2xl border border-sky-800/50 bg-gradient-to-r from-sky-950/45 to-blue-950/35 px-5 py-4 text-sm text-sky-100 shadow-lg shadow-sky-950/25">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-8 rounded-2xl border border-rose-900/60 bg-rose-950/35 px-5 py-4 text-sm text-rose-100 shadow-lg shadow-rose-950/20">
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
