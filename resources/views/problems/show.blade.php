@extends('layouts.app')

@section('main_max_class', 'max-w-[1700px]')

@section('title', $problem->title.' — '.config('app.name'))

@push('head')
    @vite('resources/js/problems.js')
@endpush

@php
    $hasRuntimes = ! empty($languages);
    $examples = $samples->map(fn ($c) => [
        'number' => $problem->testCases->search(fn ($t) => $t->id === $c->id) + 1,
        'label' => $c->label,
        'input' => (string) $c->input,
        'expected' => $c->expected_output,
    ])->values();
    $difficultyClass = match ($problem->difficulty) {
        'easy' => 'bg-emerald-500/10 text-emerald-600 ring-emerald-500/25',
        'medium' => 'bg-amber-500/10 text-amber-600 ring-amber-500/25',
        default => 'bg-rose-500/10 text-rose-600 ring-rose-500/25',
    };
    $config = [
        'storageKey' => 'home-teacher-problem-'.$problem->id,
        'urls' => [
            'run' => route('problems.run', $problem),
            'runExamples' => route('problems.run-examples', $problem),
            'submit' => route('problems.submit', $problem),
            'submission' => route('problems.submissions.show', [$problem, ':id']),
            'hideSubmission' => route('problems.submissions.hide', [$problem, ':id']),
            'solutions' => route('problems.solutions.index', $problem),
            'solution' => route('problems.solutions.show', [$problem, ':id']),
            'reference' => route('problems.solutions.reference', $problem),
            'hideAll' => route('problems.solutions.hide-all', $problem),
            'moderateSolution' => route('problems.solutions.moderate', [$problem, ':id']),
            'moderate' => route('problems.moderate', $problem),
            'shares' => route('problems.shares.store', $problem),
            'share' => route('problems.shares.update', [$problem, ':id']),
            'visibility' => route('problems.visibility', $problem),
        ],
        'languageLabels' => collect(\App\Services\LanguageRuntimeProbe::LANGUAGES)->map(fn ($l) => $l['label']),
        'solutions' => ['access' => $solutionsAccess, 'count' => $solutionsCount],
        'canModerate' => $canModerate,
        // Owner only: the Share dialog's initial state.
        'share' => $isOwner ? [
            'visibility' => $problem->visibility,
            'max_shares' => \App\Models\Problem::MAX_SHARES,
            'shares' => $shares->map(fn ($s) => ['id' => $s->id, 'name' => $s->user->name, 'email' => $s->user->email, 'include_solution' => $s->include_solution])->values(),
            'own_solutions' => $submissions->where('status', 'accepted')->whereNull('hidden_at')->count(),
        ] : null,
        'languages' => collect($languages)->map(fn ($l) => ['key' => $l['key'], 'label' => $l['label']])->values(),
        'starter' => $starter,
        'defaultLanguage' => $problem->reference_language,
        'signature' => $signature?->toArray(),
        'examples' => $examples,
        'hints' => $problem->hintList(),
        'attempts' => $attempts,
        'submissions' => $submissions->map(fn ($s) => [
            'id' => $s->id,
            'language' => $s->language,
            'status' => $s->status,
            'passed_count' => $s->passed_count,
            'total_count' => $s->total_count,
            'duration_ms' => $s->duration_ms,
            'created_at' => $s->created_at->toIso8601String(),
        ])->values(),
        'nextUrl' => $next ? route('problems.show', $next) : null,
        'nextTitle' => $next?->title,
    ];
    $tabBtn = 'problem-tab';
@endphp

@section('content')
    <div class="problem-shell" data-problem-solve data-mobile-tab="problem">
        <script type="application/json" data-config>@json($config)</script>

        {{-- Header --}}
        <header class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                <a href="{{ route('problems.index') }}" class="back-link shrink-0"><span aria-hidden="true">←</span> Problems</a>
                <h1 class="truncate text-lg font-bold tracking-tight text-slate-900 dark:text-slate-50 sm:text-xl">{{ $problem->title }}</h1>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $difficultyClass }}">{{ ucfirst($problem->difficulty) }}</span>
                <span data-solved-badge class="rounded-full bg-accent/10 px-2.5 py-0.5 text-xs font-semibold text-accent ring-1 ring-accent/25 {{ $solved ? '' : 'hidden' }}">✓ Solved</span>
                <span class="text-xs text-slate-600 dark:text-slate-500" data-attempts>{{ $attempts }} {{ Str::plural('attempt', $attempts) }}</span>
                @unless ($isOwner)
                    <span class="text-xs text-slate-600 dark:text-slate-500">· by {{ $problem->user?->name ?? 'unknown' }}{{ $share ? ' · shared with you' : ($problem->isPublic() ? ' · community' : '') }}</span>
                @endunless
            </div>
            <div class="flex shrink-0 items-center gap-2">
                @if ($next)
                    <a href="{{ route('problems.show', $next) }}" class="btn-secondary text-xs" title="Next unsolved: {{ $next->title }}">Next unsolved →</a>
                @endif
                <button type="button" class="btn-secondary text-xs" data-shortcuts-open title="Keyboard shortcuts (?)">⌨ Shortcuts</button>
                @if ($canModerate)
                    <button type="button" class="btn-secondary text-xs text-rose-600" data-moderate-problem data-moderated="{{ $problem->isModerated() ? '1' : '0' }}">
                        {{ $problem->isModerated() ? 'Restore to community (admin)' : 'Hide from community (admin)' }}
                    </button>
                @endif
                @if ($isOwner)
                    <button type="button" class="btn-secondary text-xs" data-share-open>Share · <span data-visibility-badge></span></button>
                    <a href="{{ route('problems.edit', $problem) }}" class="btn-secondary text-xs">Edit</a>
                @endif
            </div>
        </header>

        @if ($problem->isModerated() && ($isOwner || $canModerate))
            <p class="rounded-xl bg-rose-500/10 px-4 py-2 text-xs text-rose-700 dark:text-rose-300">
                An admin has hidden this problem from the community. You and the people it is shared with still have access.
            </p>
        @endif

        {{-- Mobile pane switcher --}}
        <div class="problem-mobile-tabs card-surface p-1" role="tablist" aria-label="Sections">
            @foreach (['problem' => 'Problem', 'code' => 'Code', 'result' => 'Result'] as $key => $label)
                <button type="button" role="tab" class="problem-tab flex-1 text-center" data-mobile-tab-btn="{{ $key }}" aria-selected="{{ $key === 'problem' ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>

        <div class="problem-panes" data-panes>
            {{-- ───── Statement ───── --}}
            <section class="problem-pane card-surface" data-pane="statement" aria-label="Problem statement">
                <div class="problem-tabs border-b border-slate-200/95 px-2 dark:border-slate-800/90" role="tablist">
                    <button type="button" role="tab" class="{{ $tabBtn }}" data-statement-tab="description" aria-selected="true">Description</button>
                    @if ($problem->hintList())
                        <button type="button" role="tab" class="{{ $tabBtn }}" data-statement-tab="hints" aria-selected="false">Hints ({{ count($problem->hintList()) }})</button>
                    @endif
                    <button type="button" role="tab" class="{{ $tabBtn }}" data-statement-tab="submissions" aria-selected="false">Submissions (<span data-submissions-count>{{ $attempts }}</span>)</button>
                    <button type="button" role="tab" class="{{ $tabBtn }}" data-statement-tab="solutions" aria-selected="false">Solutions{{ $solutionsCount ? " ($solutionsCount)" : '' }}</button>
                </div>

                <div class="problem-pane-body p-5">
                    <div data-statement-panel="description" class="space-y-6">
                        @if ($problem->tagList())
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($problem->tagList() as $tag)
                                    <a href="{{ route('problems.index', ['tag' => $tag]) }}" class="problem-tag">{{ $tag }}</a>
                                @endforeach
                            </div>
                        @endif

                        <div class="note-prose problem-prose text-sm leading-relaxed text-slate-800 dark:text-slate-200">{!! $problem->descriptionHtml() !!}</div>

                        @foreach ($examples as $i => $ex)
                            <div>
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                                    Example {{ $i + 1 }}@if ($ex['label']) <span class="font-normal normal-case"> · {{ $ex['label'] }}</span>@endif
                                </p>
                                @php
                                    $exInput = $signature
                                        ? (collect($signature->namedArgs($ex['input']))->map(fn ($a) => $a['name'].' = '.$a['value'])->implode("\n") ?: '(no arguments)')
                                        : $ex['input'];
                                @endphp
                                <div class="grid gap-2 sm:grid-cols-2">
                                    @foreach (['Input' => $exInput, ($signature ? 'Returns' : 'Output') => $ex['expected']] as $label => $text)
                                        <div class="min-w-0">
                                            <div class="mb-1 flex items-center justify-between">
                                                <p class="text-xs text-slate-600 dark:text-slate-500">{{ $label }}</p>
                                                <button type="button" class="text-xs text-slate-500 hover:text-slate-900 dark:hover:text-slate-100"
                                                    onclick="navigator.clipboard?.writeText(this.closest('div.min-w-0').querySelector('pre').textContent).then(() => { this.textContent = 'Copied'; setTimeout(() => this.textContent = 'Copy', 1200) })">Copy</button>
                                            </div>
                                            <pre class="problem-pre">{{ $text === '' ? '(empty)' : $text }}</pre>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                        @if ($hiddenCount)
                            <p class="text-xs text-slate-600 dark:text-slate-500">🔒 Plus {{ $hiddenCount }} hidden {{ Str::plural('test', $hiddenCount) }}. Time limit {{ number_format($problem->timeLimitMs() / 1000, 1) }}s per test.</p>
                        @endif
                    </div>

                    @if ($problem->hintList())
                        <div data-statement-panel="hints" class="hidden space-y-3">
                            <p class="text-xs text-slate-600 dark:text-slate-400">Hints are revealed one at a time.</p>
                            <div class="space-y-3" data-hints-list></div>
                            <button type="button" class="btn-secondary text-xs" data-hint-next></button>
                        </div>
                    @endif

                    <div data-statement-panel="submissions" class="hidden">
                        <div>
                            <p data-submissions-empty class="text-sm text-slate-600 dark:text-slate-400">No submissions yet. Write your solution and press Submit.</p>
                            <ul class="divide-y divide-slate-200/90 dark:divide-slate-800/90" role="list" data-submissions-list></ul>
                        </div>
                        <div class="hidden" data-submission-viewer></div>
                    </div>

                    {{-- Filled by solutions.js; code is fetched only when the server allows it. --}}
                    <div data-statement-panel="solutions" class="hidden"></div>
                </div>
            </section>

            <div class="problem-splitter" data-splitter="x" role="separator" aria-orientation="vertical" aria-label="Resize statement" tabindex="0"></div>

            <div class="problem-right" data-right>
                {{-- ───── Editor ───── --}}
                <section class="problem-pane card-surface" data-pane="editor" aria-label="Code editor">
                    @if (! $hasRuntimes)
                        <div class="p-6 text-sm text-slate-600 dark:text-slate-400">
                            No language runtimes detected on this machine. Install one on the
                            <a href="{{ route('playground.show') }}" class="link-accent-underline">Playground</a> page, then come back.
                        </div>
                    @else
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/95 px-3 py-2 dark:border-slate-800/90">
                            <div class="flex items-center gap-3">
                                <select class="input-field w-auto py-1.5 text-sm font-sans" data-language aria-label="Language">
                                    @foreach ($languages as $l)
                                        <option value="{{ $l['key'] }}">{{ $l['label'] }}</option>
                                    @endforeach
                                </select>
                                <span class="text-xs text-slate-500" data-saved aria-live="polite"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" class="btn-ghost text-xs" data-reset title="Reset to the starter template">Reset</button>
                                <button type="button" class="btn-ghost hidden text-xs lg:inline-flex" data-focus aria-pressed="false" title="Focus mode (F)">⛶ Focus</button>
                                <button type="button" class="btn-secondary text-xs" data-run title="Run (⌘/Ctrl + ')"><span data-run-label>Run examples</span></button>
                                <button type="button" class="btn-primary text-xs" data-submit title="Submit (⌘/Ctrl + Enter)"><span data-submit-label>Submit</span></button>
                            </div>
                        </div>
                        <div class="problem-editor" data-editor></div>
                    @endif
                </section>

                <div class="problem-splitter" data-splitter="y" role="separator" aria-orientation="horizontal" aria-label="Resize console" tabindex="0"></div>

                {{-- ───── Console ───── --}}
                <section class="problem-pane card-surface" data-pane="console" aria-label="Results">
                    <div class="problem-tabs border-b border-slate-200/95 px-2 dark:border-slate-800/90" role="tablist">
                        <button type="button" role="tab" class="{{ $tabBtn }}" data-console-tab="cases" aria-selected="true">Examples</button>
                        <button type="button" role="tab" class="{{ $tabBtn }}" data-console-tab="custom" aria-selected="false">Custom input</button>
                        <button type="button" role="tab" class="{{ $tabBtn }}" data-console-tab="result" aria-selected="false">Result</button>
                    </div>
                    <div class="problem-pane-body p-4" aria-live="polite">
                        <div data-console-panel="cases">
                            @if ($examples->isEmpty())
                                <p class="text-sm text-slate-600 dark:text-slate-400">This problem has no visible examples. Use Custom input to try your code, or Submit to run every test.</p>
                            @endif
                            <div data-compile-out="cases"></div>
                            <div data-cases-board></div>
                        </div>
                        <div data-console-panel="custom" class="hidden space-y-3">
                            @if ($signature)
                                <p class="text-xs text-slate-600 dark:text-slate-400">Call <code class="font-mono">{{ $signature->name }}</code> with your own arguments (JSON), then press Run custom.</p>
                                <div class="space-y-2" data-custom-args></div>
                            @else
                                <label class="block text-xs text-slate-600 dark:text-slate-400" for="custom-stdin">Input (stdin)</label>
                                <textarea id="custom-stdin" data-custom-stdin rows="4" class="input-field resize-y font-mono text-sm" placeholder="Type any input, then press Run custom"></textarea>
                            @endif
                            <div data-custom-output></div>
                        </div>
                        <div data-console-panel="result" class="hidden space-y-4">
                            <div data-result-summary><p class="text-sm text-slate-600 dark:text-slate-400">Press Submit to run all {{ $problem->testCases->count() }} tests.</p></div>
                            <div data-compile-out="result"></div>
                            <div data-result-board></div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <dialog data-shortcuts-dialog class="m-auto w-[min(26rem,92vw)] rounded-2xl border border-slate-200 bg-white p-6 text-slate-900 backdrop:bg-black/50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
            <h2 class="mb-4 text-base font-bold">Keyboard shortcuts</h2>
            <dl class="space-y-2 text-sm">
                @foreach ([
                    ['⌘/Ctrl + Enter', 'Submit (press again to cancel)'],
                    ["⌘/Ctrl + '", 'Run examples / custom input'],
                    ['F', 'Toggle focus mode'],
                    ['Esc', 'Leave focus mode'],
                    ['← → ↑ ↓', 'Resize a pane (when its handle is focused)'],
                    ['?', 'Show this help'],
                ] as [$keys, $what])
                    <div class="flex justify-between gap-4"><dt class="font-mono text-xs">{{ $keys }}</dt><dd class="text-slate-600 dark:text-slate-400">{{ $what }}</dd></div>
                @endforeach
            </dl>
            <button type="button" class="btn-secondary mt-5 text-xs" data-shortcuts-close>Close</button>
        </dialog>

        @if ($isOwner)
            <dialog data-share-dialog class="m-auto w-[min(32rem,94vw)] rounded-2xl border border-slate-200 bg-white p-6 text-slate-900 backdrop:bg-black/50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                <h2 class="text-base font-bold">Share “{{ $problem->title }}”</h2>

                <fieldset class="mt-4 space-y-2">
                    <legend class="sr-only">Who can open this problem</legend>
                    @foreach ([
                        'private' => ['Only me', 'Nobody else can see it.'],
                        'people' => ['Specific people', 'Up to '.\App\Models\Problem::MAX_SHARES.' people you add by email. They can solve it but not edit it.'],
                        'community' => ['Community', 'Every member can see and solve it.'],
                    ] as $value => [$title, $desc])
                        <label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200/95 p-3 has-[:checked]:border-accent has-[:checked]:bg-accent/5 dark:border-slate-800/90">
                            <input type="radio" name="share-mode" value="{{ $value }}" class="mt-0.5" data-share-mode @disabled($value === 'community' && $problem->isModerated())>
                            <span><span class="block text-sm font-semibold">{{ $title }}</span><span class="block text-xs text-slate-600 dark:text-slate-400">{{ $desc }}</span></span>
                        </label>
                    @endforeach
                </fieldset>

                <form class="mt-5 space-y-2" data-share-form>
                    <div class="flex items-center justify-between">
                        <label for="share-email" class="text-sm font-semibold">Add a person</label>
                        <span class="text-xs text-slate-600 dark:text-slate-500" data-share-count></span>
                    </div>
                    <div class="flex gap-2">
                        <input id="share-email" type="email" autocomplete="off" placeholder="their account email" class="input-field flex-1 font-sans" data-share-email>
                        <button type="submit" class="btn-primary text-xs">Add</button>
                    </div>
                    <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                        <input type="checkbox" data-share-include> Let them see my solution without solving it first
                    </label>
                    <p class="text-xs" data-share-message aria-live="polite"></p>
                </form>

                <ul class="mt-3 divide-y divide-slate-200/90 dark:divide-slate-800/90" role="list" data-share-list></ul>
                <p class="mt-2 text-xs text-slate-600 dark:text-slate-500" data-share-empty>Not shared with anyone yet.</p>

                <p class="mt-4 text-xs text-slate-600 dark:text-slate-500">
                    People who solve the problem can see each other’s accepted code (and yours) in Solutions. Anyone can hide their own from the Submissions tab.
                </p>
                <div class="mt-4 flex justify-end">
                    <button type="button" class="btn-secondary text-xs" data-share-close>Done</button>
                </div>
            </dialog>
        @endif
    </div>
@endsection
