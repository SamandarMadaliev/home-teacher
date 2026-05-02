<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css'])
    @stack('head')
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
    <header class="border-b border-zinc-800 bg-zinc-900/80 backdrop-blur">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
            <a href="{{ route('courses.index') }}" class="text-lg font-semibold tracking-tight text-white hover:text-zinc-300">
                {{ config('app.name') }}
            </a>
            <nav class="flex gap-4 text-sm text-zinc-400">
                <a href="{{ route('courses.index') }}" class="hover:text-white">Courses</a>
                <a href="{{ route('courses.create') }}" class="hover:text-white">Add course</a>
            </nav>
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-emerald-800/80 bg-emerald-950/40 px-4 py-3 text-sm text-emerald-100">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-red-900/80 bg-red-950/40 px-4 py-3 text-sm text-red-100">
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
