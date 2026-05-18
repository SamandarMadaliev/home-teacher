@extends('layouts.app')

@section('main_max_class', 'max-w-7xl')

@section('title', 'Home — '.config('app.name'))

@section('content')
    @if (! empty($guest) && $guest)
        <section class="home-guest-hero home-hero-wrap border border-sky-400/55 bg-white/93 shadow-2xl shadow-slate-400/35 ring-1 ring-slate-900/6 dark:border-sky-500/25 dark:bg-slate-950/80 dark:shadow-black/40 dark:ring-white/5" aria-label="Welcome">
            <div class="home-hero-inner px-6 py-12 sm:px-10 sm:py-14 lg:px-12 lg:py-16">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="section-eyebrow text-sky-600 dark:text-sky-400/95">{{ config('app.name') }}</p>
                    <h1 class="home-page-title mt-3 text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl lg:text-[2.65rem] lg:leading-tight">
                        Your courses. Your progress. Your account.
                    </h1>
                    <p class="mx-auto mt-4 max-w-xl text-[15px] leading-relaxed text-slate-600 dark:text-slate-400">
                        Watch lessons from folders on your machine, save progress, take notes, and build roadmaps — each user gets a private library.
                    </p>
                    <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                        @if (filled(config('services.google.client_id')) && filled(config('services.google.client_secret')))
                            <a href="{{ route('auth.google.redirect') }}" class="btn-google min-w-[10.5rem] px-6">
                                <svg class="size-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                                </svg>
                                Google
                            </a>
                        @endif
                        <a href="{{ route('register') }}" class="btn-primary min-w-[10.5rem] px-6">Create account</a>
                        <a href="{{ route('login') }}" class="btn-secondary min-w-[10.5rem] px-6">Sign in</a>
                    </div>
                </div>

                <ul class="mx-auto mt-12 grid max-w-4xl gap-4 sm:grid-cols-3 sm:gap-5" role="list">
                    <li class="home-guest-feature">
                        <span class="home-guest-feature-icon text-sky-600 dark:text-sky-400" aria-hidden="true">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a3 3 0 01-3 3m-3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                        <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-slate-100">Private library</p>
                        <p class="mt-1.5 text-xs leading-relaxed text-slate-600 dark:text-slate-500">Courses and progress stay tied to your account.</p>
                    </li>
                    <li class="home-guest-feature">
                        <span class="home-guest-feature-icon text-sky-600 dark:text-sky-400" aria-hidden="true">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 00-1.06-.44z" />
                            </svg>
                        </span>
                        <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-slate-100">Local videos</p>
                        <p class="mt-1.5 text-xs leading-relaxed text-slate-600 dark:text-slate-500">Point at folders on disk — nothing is uploaded.</p>
                    </li>
                    <li class="home-guest-feature">
                        <span class="home-guest-feature-icon text-sky-600 dark:text-sky-400" aria-hidden="true">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                            </svg>
                        </span>
                        <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-slate-100">Track progress</p>
                        <p class="mt-1.5 text-xs leading-relaxed text-slate-600 dark:text-slate-500">Resume lessons, notes, and roadmaps where you left off.</p>
                    </li>
                </ul>
            </div>
        </section>
    @else
    {{-- Page intro --}}
    <header class="mb-10 sm:mb-12">
        <h1 class="home-page-title text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">Home</h1>
        <p class="mt-2 max-w-2xl text-[15px] leading-relaxed text-slate-600 dark:text-slate-400">
            Continue watching, follow your roadmaps, and browse everything in your library.
        </p>
    </header>

    {{-- Row 1: hero — latest watched --}}
    <section class="mb-12 sm:mb-16" aria-label="Continue watching">
        @if ($lastWatchedCourse)
            @php
                $heroPct = $lastWatchedCourse->aggregateProgressPercent();
            @endphp
            <div class="home-hero-wrap border border-sky-400/55 bg-white/93 shadow-2xl shadow-slate-400/35 ring-1 ring-slate-900/6 dark:border-sky-500/25 dark:bg-slate-950/80 dark:shadow-black/40 dark:ring-white/5">
                <a href="{{ $continueUrl }}" class="home-hero-inner group block text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/90 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-100 dark:focus-visible:ring-sky-400/90 dark:focus-visible:ring-offset-slate-950">
                    <div class="relative flex flex-col gap-8 p-6 sm:flex-row sm:items-stretch sm:gap-10 sm:p-8 lg:p-10">
                        <span class="absolute left-0 top-6 bottom-6 w-1 rounded-full bg-gradient-to-b from-sky-400 via-blue-500 to-indigo-600 sm:top-8 sm:bottom-8 lg:top-10 lg:bottom-10" aria-hidden="true"></span>
                        <div class="flex min-w-0 flex-1 flex-col pl-4 sm:pl-6">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="section-eyebrow text-sky-600 dark:text-sky-400/95">Continue watching</span>
                                <span class="rounded-full bg-slate-900/[0.04] px-2.5 py-0.5 text-[11px] font-medium tabular-nums text-slate-600 ring-1 ring-slate-300/85 dark:bg-white/5 dark:text-slate-500 dark:ring-white/10">
                                    {{ $lastWatchedCourse->videos_count }} {{ $lastWatchedCourse->videos_count === 1 ? 'lesson' : 'lessons' }}
                                </span>
                            </div>
                            <p class="mt-4 text-xl font-semibold leading-snug text-slate-900 dark:text-white sm:text-2xl lg:text-[1.65rem] lg:leading-tight">
                                {{ $lastWatchedCourse->title }}
                            </p>
                            @if ($continueVideo)
                                <p class="mt-3 flex flex-wrap items-baseline gap-x-2 text-sm text-slate-600 dark:text-slate-400">
                                    <span class="text-slate-500 dark:text-slate-500">Next up</span>
                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ $continueVideo->title }}</span>
                                </p>
                            @elseif ($lastWatchedCourse->videos_count > 0)
                                <p class="mt-3 text-sm text-slate-600 dark:text-slate-500">Open the course to choose a lesson.</p>
                            @else
                                <p class="mt-3 text-sm text-slate-600 dark:text-slate-500">No lessons indexed yet — open the course to rescan.</p>
                            @endif

                            <div class="mt-8 flex flex-wrap items-center gap-4">
                                <span class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-sky-500/35 ring-1 ring-white/25 transition group-hover:from-sky-400 group-hover:to-blue-500 dark:shadow-sky-950/45 dark:ring-white/15 dark:group-hover:shadow-sky-900/50">
                                    Resume
                                    <svg class="h-4 w-4 transition group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                    </svg>
                                </span>
                                <span class="text-xs text-slate-500 dark:text-slate-600">Press the card anywhere to open</span>
                            </div>
                        </div>

                        @if ($lastWatchedCourse->videos_count > 0)
                            <div class="flex w-full shrink-0 flex-col justify-center border-t border-slate-200/98 pt-6 sm:w-72 sm:border-l sm:border-t-0 sm:border-slate-200/98 sm:pl-10 sm:pt-0 lg:w-80 dark:border-white/5 sm:dark:border-white/10">
                                <div class="flex items-end justify-between gap-3">
                                    <span class="text-xs font-medium text-slate-600 dark:text-slate-500">Overall progress</span>
                                    <span class="text-2xl font-bold tabular-nums tracking-tight text-sky-600 dark:text-sky-300">{{ $heroPct }}<span class="text-lg font-semibold text-sky-500 dark:text-sky-400/80">%</span></span>
                                </div>
                                <div
                                    class="mt-4 h-3 overflow-hidden rounded-full bg-slate-200 ring-1 ring-slate-300/95 dark:bg-slate-900 dark:ring-slate-700/90"
                                    role="progressbar"
                                    aria-valuenow="{{ $heroPct }}"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                >
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-sky-400 via-sky-500 to-blue-500 transition-[width] duration-500 ease-out"
                                        style="width: {{ $heroPct }}%"
                                    ></div>
                                </div>
                                <p class="mt-3 text-[11px] leading-relaxed text-slate-600 dark:text-slate-600">Average completion across lessons in this course.</p>
                            </div>
                        @endif
                    </div>
                </a>
            </div>
        @else
            <div class="empty-state-soft px-6 py-14 text-center sm:px-12 sm:py-16">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-500/35 to-blue-600/22 ring-1 ring-sky-500/38 dark:from-sky-500/25 dark:to-blue-600/15 dark:ring-sky-500/30">
                    <svg class="h-8 w-8 text-sky-600 dark:text-sky-400/90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                    </svg>
                </div>
                <p class="mt-6 text-lg font-semibold text-slate-800 dark:text-slate-100">No watch history yet</p>
                <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-600 dark:text-slate-500">
                    Start any lesson — your most recent course will appear here for quick access.
                </p>
                <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('courses.create') }}" class="btn-primary px-6">Add course</a>
                    <a href="{{ route('courses.index') }}" class="btn-secondary px-6">Browse library</a>
                </div>
            </div>
        @endif
    </section>

    <div class="section-divider mb-12 sm:mb-16" aria-hidden="true"></div>

    {{-- Row 2: roadmaps --}}
    <section class="mb-12 sm:mb-16" aria-labelledby="home-roadmaps-heading">
        <div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <p class="section-eyebrow text-violet-600 dark:text-violet-400/95">Learning paths</p>
                <h2 id="home-roadmaps-heading" class="mt-2 text-2xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-3xl">Roadmaps</h2>
                <p class="mt-2 max-w-xl text-sm leading-relaxed text-slate-600 dark:text-slate-500">Stack courses in the order you want to complete them.</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2 sm:justify-end">
                <a href="{{ route('roadmaps.index') }}" class="btn-secondary text-sm">View all</a>
                <a href="{{ route('roadmaps.create') }}" class="btn-primary text-sm">New roadmap</a>
            </div>
        </div>

        @if ($roadmaps->isEmpty())
            <div class="empty-state-soft px-6 py-12 text-center">
                <p class="text-sm text-slate-600 dark:text-slate-500">No roadmaps yet.</p>
                <a href="{{ route('roadmaps.create') }}" class="mt-4 inline-flex text-sm font-semibold text-violet-700 transition hover:text-violet-900 dark:text-violet-400 dark:hover:text-violet-300">Create your first roadmap</a>
            </div>
        @else
            <ul class="courses-grid courses-grid--roomy" role="list">
                @foreach ($roadmaps as $roadmap)
                    <li class="flex min-h-0">
                        <a href="{{ route('roadmaps.show', $roadmap) }}" class="roadmap-card-home group w-full">
                            <div class="flex items-start justify-between gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-500/20 text-violet-700 ring-1 ring-violet-500/35 transition group-hover:bg-violet-500/28 dark:bg-violet-500/15 dark:text-violet-300 dark:ring-violet-500/25 dark:group-hover:bg-violet-500/25" aria-hidden="true">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75H15.75M9 12H15.75M9 17.25H15.75M4.5 6.75h.008v.008H4.5V6.75zm0 5.25h.008v.008H4.5v-.008zm0 5.25h.008v.008H4.5v-.008zM19.5 6.75h.008v.008H19.5V6.75zm0 5.25h.008v.008H19.5v-.008zm0 5.25h.008v.008H19.5v-.008z" />
                                    </svg>
                                </span>
                            </div>
                            <div class="mt-4 min-w-0 flex-1">
                                <h3 class="line-clamp-2 text-base font-semibold leading-snug text-slate-900 transition group-hover:text-slate-950 dark:text-slate-50 dark:group-hover:text-white">
                                    {{ $roadmap->title }}
                                </h3>
                                @if ($roadmap->description)
                                    <p class="mt-2 line-clamp-2 text-xs leading-relaxed text-slate-600 dark:text-slate-500">{{ $roadmap->description }}</p>
                                @endif
                            </div>
                            <div class="mt-5 flex items-center justify-between border-t border-slate-200/95 pt-4 dark:border-white/5">
                                <span class="text-xs tabular-nums text-slate-600 dark:text-slate-500">{{ $roadmap->courses_count }} {{ $roadmap->courses_count === 1 ? 'course' : 'courses' }}</span>
                                <span class="text-xs font-semibold text-violet-700 transition group-hover:text-violet-900 dark:text-violet-300/95 dark:group-hover:text-violet-200">Open →</span>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div class="section-divider mb-12 sm:mb-16" aria-hidden="true"></div>

    {{-- Row 3: courses --}}
    <section aria-labelledby="home-courses-heading">
        <div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <p class="section-eyebrow text-sky-600 dark:text-sky-400/95">Library</p>
                <h2 id="home-courses-heading" class="mt-2 text-2xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-3xl">Courses</h2>
                <p class="mt-2 max-w-xl text-sm leading-relaxed text-slate-600 dark:text-slate-500">Your three most recently active courses.</p>
            </div>
            @if ($coursesTotalCount > 0)
                <a href="{{ route('courses.index') }}" class="btn-secondary shrink-0 self-start text-sm sm:self-auto">
                    Show all{{ $coursesTotalCount > 3 ? ' ('.$coursesTotalCount.')' : '' }}
                </a>
            @endif
        </div>

        @if ($courses->isEmpty())
            <div class="empty-state-soft px-8 py-14 text-center">
                <p class="text-lg font-medium text-slate-800 dark:text-slate-100">No courses yet</p>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-500">
                    <a href="{{ route('courses.create') }}" class="font-semibold text-sky-600 underline decoration-sky-500/55 underline-offset-4 transition hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300">Add a course</a>
                    <span class="text-slate-600 dark:text-slate-600"> by pointing at a folder of videos.</span>
                </p>
            </div>
        @else
            <ul class="courses-grid courses-grid--roomy" role="list">
                @foreach ($courses as $course)
                    @php
                        $accents = ['course-card-accent-sky', 'course-card-accent-slate', 'course-card-accent-emerald'];
                        $accentClass = $accents[$loop->index % 3];
                        $coursePct = $course->aggregateProgressPercent();
                    @endphp
                    <li class="flex min-h-0">
                        <a
                            href="{{ route('courses.show', $course) }}"
                            class="group course-card-box {{ $accentClass }} h-full w-full min-h-[12rem] flex-1"
                        >
                            <div class="min-w-0 flex-1">
                                <h3 class="line-clamp-3 text-lg font-semibold leading-snug text-slate-900 transition group-hover:text-slate-950 dark:text-slate-50 dark:group-hover:text-white">
                                    {{ $course->title }}
                                </h3>
                                <p class="mt-3 text-sm text-slate-600 dark:text-slate-500">
                                    {{ $course->videos_count }} {{ $course->videos_count === 1 ? 'lesson' : 'lessons' }}
                                </p>
                                @if ($course->videos_count > 0)
                                    <div class="mt-5">
                                        <div class="flex items-center justify-between gap-2 text-xs text-slate-600 dark:text-slate-500">
                                            <span>Progress</span>
                                            <span class="tabular-nums font-semibold text-slate-700 dark:text-slate-300">{{ $coursePct }}%</span>
                                        </div>
                                        <div
                                            class="mt-2.5 h-2.5 overflow-hidden rounded-full bg-slate-200 ring-1 ring-slate-300/95 dark:bg-slate-900/90 dark:ring-slate-700/80"
                                            role="progressbar"
                                            aria-valuenow="{{ $coursePct }}"
                                            aria-valuemin="0"
                                            aria-valuemax="100"
                                            aria-label="Overall progress for {{ $course->title }}"
                                        >
                                            <div
                                                class="h-full rounded-full bg-gradient-to-r from-sky-500 to-blue-500 transition-[width] duration-300"
                                                style="width: {{ $coursePct }}%"
                                            ></div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="mt-6 flex items-center justify-between border-t border-slate-200/97 pt-4 dark:border-slate-700/50">
                                <span class="text-xs font-semibold text-sky-600 transition group-hover:text-sky-800 dark:text-sky-400/95 dark:group-hover:text-sky-300">Open course</span>
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-sky-600 ring-1 ring-slate-300/95 transition group-hover:bg-sky-100 group-hover:text-sky-700 group-hover:ring-sky-400/55 dark:bg-slate-800/90 dark:text-sky-400 dark:ring-slate-600/70 dark:group-hover:bg-sky-500/15 dark:group-hover:text-sky-300 dark:group-hover:ring-sky-500/35"
                                    aria-hidden="true"
                                >
                                    →
                                </span>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
    @endif
@endsection
