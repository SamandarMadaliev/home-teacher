@extends('layouts.app')

@section('main_max_class', 'max-w-5xl')

@section('title', 'Problems — '.config('app.name'))

@push('head')
    @vite('resources/js/problems.js')
@endpush

@php
    $diffClass = fn ($d) => match ($d) {
        'easy' => 'bg-emerald-500/10 text-emerald-600 ring-emerald-500/25',
        'medium' => 'bg-amber-500/10 text-amber-600 ring-amber-500/25',
        default => 'bg-rose-500/10 text-rose-600 ring-rose-500/25',
    };
    $statusTabs = ['all' => 'All', 'todo' => 'To do', 'attempted' => 'Attempted', 'solved' => 'Solved'];
    $query = fn (array $over) => route('problems.index', array_filter(array_merge($filters, $over), fn ($v, $k) => $v !== '' && $v !== null && ! ($k === 'status' && $v === 'all') && ! ($k === 'sort' && $v === 'recent'), ARRAY_FILTER_USE_BOTH));
@endphp

@section('content')
    <div class="mb-8 flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="section-eyebrow text-accent">Practice</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">Coding problems</h1>
            <p class="mt-2 max-w-lg text-sm leading-relaxed text-slate-600 dark:text-slate-500">
                Write your own problems with test cases, then solve them in JavaScript, Python, PHP or Go.
            </p>
        </div>
        <div class="flex shrink-0 gap-2">
            @if ($totalCount > $solvedCount)
                <a href="{{ route('problems.random') }}" class="btn-secondary">🎲 Random unsolved</a>
            @endif
            <a href="{{ route('problems.create') }}" class="btn-primary">New problem</a>
        </div>
    </div>

    @if ($totalCount === 0)
        <div class="card-surface px-8 py-14 text-center">
            <p class="text-lg font-medium text-slate-900 dark:text-slate-100">No problems yet</p>
            <p class="mx-auto mt-2 max-w-md text-sm text-slate-600 dark:text-slate-400">
                A problem is a statement plus input/output test cases. Your solution reads stdin and prints to stdout.
            </p>
            <a href="{{ route('problems.create') }}" class="btn-primary mt-6 inline-flex">Create your first problem</a>
        </div>
    @else
        {{-- Progress --}}
        <section class="card-surface mb-6 p-5" aria-label="Your progress">
            <div class="flex flex-wrap items-center gap-x-8 gap-y-3">
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    <span class="text-2xl font-bold text-slate-900 dark:text-slate-50">{{ $solvedCount }}</span> / {{ $totalCount }} solved
                </p>
                @foreach ($stats as $difficulty => $s)
                    @continue($s['total'] === 0)
                    <div class="min-w-[7rem] flex-1">
                        <div class="mb-1 flex justify-between text-xs"><span class="font-semibold capitalize text-slate-700 dark:text-slate-300">{{ $difficulty }}</span><span class="text-slate-600 dark:text-slate-500">{{ $s['solved'] }}/{{ $s['total'] }}</span></div>
                        <div class="problem-progress" role="progressbar" aria-label="{{ ucfirst($difficulty) }} solved" aria-valuemin="0" aria-valuemax="{{ $s['total'] }}" aria-valuenow="{{ $s['solved'] }}">
                            <span style="width: {{ $s['total'] ? round($s['solved'] / $s['total'] * 100) : 0 }}%"></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        @if ($continue && ! $filtered)
            <a href="{{ route('problems.show', $continue) }}" class="card-surface card-hover-accent group mb-6 flex items-center justify-between gap-4 p-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wider text-accent">Continue</p>
                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-50">{{ $continue->title }}</p>
                    <p class="text-xs text-slate-600 dark:text-slate-500">{{ $continue->submissions_count }} {{ Str::plural('attempt', $continue->submissions_count) }} · last {{ \Illuminate\Support\Carbon::parse($continue->last_attempt_at)->diffForHumans() }}</p>
                </div>
                <span class="btn-secondary shrink-0 text-xs">Resume →</span>
            </a>
        @endif

        {{-- Filters --}}
        <form method="get" action="{{ route('problems.index') }}" class="mb-5 space-y-3" role="search" data-filter-form>
            @if ($filters['status'] !== 'all') <input type="hidden" name="status" value="{{ $filters['status'] }}"> @endif
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative min-w-[12rem] flex-1">
                    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search problems…  ( / )" class="input-field w-full font-sans" aria-label="Search problems" data-search>
                </div>
                <select name="difficulty" class="input-field w-auto font-sans" aria-label="Difficulty" onchange="this.form.submit()">
                    <option value="">Any difficulty</option>
                    @foreach (\App\Models\Problem::DIFFICULTIES as $d)
                        <option value="{{ $d }}" @selected($filters['difficulty'] === $d)>{{ ucfirst($d) }}</option>
                    @endforeach
                </select>
                @if ($allTags)
                    <select name="tag" class="input-field w-auto font-sans" aria-label="Tag" onchange="this.form.submit()">
                        <option value="">Any tag</option>
                        @foreach ($allTags as $tag)
                            <option value="{{ $tag }}" @selected($filters['tag'] === $tag)>{{ $tag }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="sort" class="input-field w-auto font-sans" aria-label="Sort" onchange="this.form.submit()">
                    <option value="recent" @selected($filters['sort'] === 'recent')>Recently edited</option>
                    <option value="title" @selected($filters['sort'] === 'title')>Title</option>
                    <option value="difficulty" @selected($filters['sort'] === 'difficulty')>Difficulty</option>
                </select>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="problem-tabs" role="tablist" aria-label="Status">
                    @foreach ($statusTabs as $key => $label)
                        <a href="{{ $query(['status' => $key]) }}" class="problem-tab" role="tab" aria-selected="{{ $filters['status'] === $key ? 'true' : 'false' }}">{{ $label }}</a>
                    @endforeach
                </div>
                @if ($filtered)
                    <a href="{{ route('problems.index') }}" class="text-xs font-semibold text-slate-600 hover:underline dark:text-slate-400">Clear filters</a>
                @endif
            </div>
        </form>

        @if ($problems->isEmpty())
            <div class="card-surface px-8 py-12 text-center">
                <p class="text-base font-medium text-slate-900 dark:text-slate-100">No problems match these filters</p>
                <a href="{{ route('problems.index') }}" class="link-accent-underline mt-2 inline-block text-sm">Clear filters</a>
            </div>
        @else
            <ul class="space-y-2" role="list">
                @foreach ($problems as $problem)
                    @php
                        $state = $problem->solved ? 'solved' : ($problem->submissions_count > 0 ? 'attempted' : 'todo');
                    @endphp
                    <li>
                        <a href="{{ route('problems.show', $problem) }}" class="card-surface card-hover-accent group flex items-center gap-4 p-4">
                            <span class="w-6 shrink-0 text-center text-lg {{ $state === 'solved' ? 'text-emerald-500' : ($state === 'attempted' ? 'text-amber-500' : 'text-slate-400') }}"
                                title="{{ ucfirst($state === 'todo' ? 'to do' : $state) }}" role="img" aria-label="{{ $state === 'todo' ? 'To do' : ucfirst($state) }}">
                                {{ $state === 'solved' ? '✓' : ($state === 'attempted' ? '◐' : '○') }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <h2 class="truncate text-base font-semibold text-slate-900 dark:text-slate-50">{{ $problem->title }}</h2>
                                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-600 dark:text-slate-500">
                                    <span>{{ $problem->test_cases_count }} {{ Str::plural('test', $problem->test_cases_count) }}</span>
                                    @if ($problem->submissions_count)
                                        <span>· {{ $problem->submissions_count }} {{ Str::plural('attempt', $problem->submissions_count) }}</span>
                                    @endif
                                    @foreach ($problem->tagList() as $tag)
                                        <span class="problem-tag">{{ $tag }}</span>
                                    @endforeach
                                    @if ($problem->visible_cases_count === 0)
                                        <span class="font-semibold text-amber-600">⚠ no examples</span>
                                    @endif
                                </p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $diffClass($problem->difficulty) }}">{{ ucfirst($problem->difficulty) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
@endsection

@push('scripts')
<script>
    // "/" focuses the search box, like most list UIs.
    document.addEventListener('keydown', (e) => {
        const t = e.target;
        if (e.key !== '/' || e.metaKey || e.ctrlKey || (t instanceof HTMLElement && (t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName)))) return;
        const box = document.querySelector('[data-search]');
        if (box) { e.preventDefault(); box.focus(); }
    });
</script>
@endpush
