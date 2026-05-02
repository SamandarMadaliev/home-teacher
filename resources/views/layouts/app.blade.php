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
<body class="app-backdrop min-h-full text-slate-100/95 antialiased">
    <header class="sticky top-0 z-50 border-b border-slate-800/80 bg-slate-950/80 backdrop-blur-xl">
        <div class="mx-auto @yield('main_max_class', 'max-w-5xl') flex items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <a href="{{ route('courses.index') }}" class="group flex items-center gap-2.5" aria-label="{{ config('app.name') }}, home">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-sky-400 to-blue-600 text-lg shadow-lg shadow-blue-950/50 ring-1 ring-sky-400/35" aria-hidden="true">
                    <span class="text-sm font-bold text-slate-950">▶</span>
                </span>
                <span class="text-lg font-semibold tracking-tight text-slate-50 transition group-hover:text-white">
                    {{ config('app.name') }}
                </span>
            </a>
            <nav class="flex items-center gap-1 sm:gap-2">
                <a
                    href="{{ route('courses.index') }}"
                    class="rounded-lg px-3 py-2 text-sm font-medium text-slate-300/90 transition hover:bg-slate-800/70 hover:text-white"
                >
                    Courses
                </a>
                <a
                    href="{{ route('courses.create') }}"
                    class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 px-3 py-2 text-xs font-semibold text-white shadow-md shadow-blue-950/35 transition hover:from-sky-400 hover:to-blue-500 sm:px-4 sm:text-sm"
                >
                    Add course
                </a>
            </nav>
        </div>
    </header>

    <main class="mx-auto @yield('main_max_class', 'max-w-5xl') px-4 py-10 sm:px-6 sm:py-12">
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
