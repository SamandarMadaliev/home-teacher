@extends('layouts.app')

@section('main_max_class', 'max-w-6xl')

@php
    $editing = $problem->exists;
    $tests = old('tests', $editing
        ? $problem->testCases->map(fn ($t) => ['id' => $t->id, 'label' => $t->label, 'input' => $t->input, 'expected_output' => $t->expected_output, 'is_hidden' => $t->is_hidden])->all()
        : [['input' => '', 'expected_output' => '', 'is_hidden' => false]]);
    $tags = old('tags', $problem->tagList());
    $hints = old('hints', $problem->hintList());
    $starterValues = old('starter_code', $problem->starter_code ?? []);
    $compare = old('compare_mode', $problem->compareMode());
    $timeLimit = (int) old('time_limit_ms', $problem->timeLimitMs());
    $refLang = old('reference_language', $problem->reference_language ?: ($languages[0]['key'] ?? 'python'));
    $ioMode = old('io_mode', $problem->io_mode ?: 'stdin');
    $signatureData = old('signature', $problem->signature ?? ['name' => 'solve', 'params' => [], 'returns' => 'int']);
    $signatureData['params'] = array_values($signatureData['params'] ?? []);
    $sigConfig = [
        'mode' => $ioMode,
        'signature' => $signatureData,
        'types' => $types,
        'reserved' => $reserved,
        'functionLanguages' => $functionLanguages,
    ];
    $sigErrors = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'signature'))->flatten()->unique()->values();
    $sections = ['details' => 'Details', 'statement' => 'Statement', 'format' => 'Input format', 'tests' => 'Test cases', 'solution' => 'Reference solution', 'hints' => 'Hints', 'settings' => 'Settings'];
    $functionCompareModes = [
        'exact' => ['Exact value', 'The returned value must equal the expected one. Numbers in a float return may differ by up to 0.000001.'],
        'unordered' => ['Any order', 'The returned list may be in any order — e.g. “return all pairs”.'],
    ];
    $compareModes = [
        'trim_lines' => ['Ignore trailing whitespace', 'Trailing spaces on a line and blank lines at the end don’t matter. Recommended.'],
        'exact' => ['Exact match', 'The output must match character for character.'],
        'tokens' => ['Ignore all whitespace', 'Compares words and numbers only; spacing and line breaks don’t matter.'],
        'float' => ['Numbers with tolerance', 'Like the previous, but numbers may differ by up to 0.000001.'],
    ];
@endphp

@section('title', ($editing ? 'Edit problem' : 'New problem').' — '.config('app.name'))

@push('head')
    @vite('resources/js/problems.js')
@endpush

@section('content')
    <a href="{{ $editing ? route('problems.show', $problem) : route('problems.index') }}" class="back-link">
        <span aria-hidden="true">←</span> {{ $editing ? 'Back to problem' : 'Problems' }}
    </a>
    <h1 class="mt-6 mb-8 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-50">{{ $editing ? 'Edit problem' : 'New problem' }}</h1>

    <form
        action="{{ $editing ? route('problems.update', $problem) : route('problems.store') }}"
        method="post"
        id="problem-form"
        data-problem-form
        data-preview-url="{{ route('problems.preview') }}"
        data-reference-url="{{ route('problems.reference-check') }}"
        data-has-reference="{{ $problem->reference_solution ? '1' : '0' }}"
        data-default-limit="{{ \App\Models\Problem::DEFAULT_TIME_LIMIT_MS }}"
    >
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="problem-form-grid">
            {{-- Side navigation + readiness --}}
            <aside class="problem-form-nav hidden space-y-6 lg:block" aria-label="Form sections">
                <nav class="space-y-0.5">
                    @foreach ($sections as $id => $label)
                        <a href="#{{ $id }}" data-nav="{{ $id }}">{{ $label }}<span class="text-xs" data-nav-badge="{{ $id }}"></span></a>
                    @endforeach
                </nav>
                <div>
                    <p class="mb-2 px-2 text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">Readiness</p>
                    <ul class="space-y-1.5 px-2 text-xs" data-readiness role="list"></ul>
                </div>
            </aside>

            <div class="min-w-0 space-y-6">
                {{-- Details --}}
                <section id="details" class="card-surface scroll-mt-4 space-y-5 p-6">
                    <h2 class="text-base font-bold text-slate-900 dark:text-slate-50">Details</h2>
                    <div class="grid gap-5 sm:grid-cols-[1fr_auto]">
                        <div>
                            <label for="title" class="mb-2 block text-sm font-medium text-slate-800 dark:text-slate-200">Title</label>
                            <input type="text" name="title" id="title" required maxlength="255" class="input-field font-sans"
                                value="{{ old('title', $problem->title) }}" placeholder="e.g. Sum of two numbers" data-title>
                            @error('title') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <span class="mb-2 block text-sm font-medium text-slate-800 dark:text-slate-200">Difficulty</span>
                            <div class="problem-seg" role="radiogroup" aria-label="Difficulty">
                                @foreach (\App\Models\Problem::DIFFICULTIES as $d)
                                    <label><input type="radio" name="difficulty" value="{{ $d }}" @checked(old('difficulty', $problem->difficulty) === $d)><span>{{ ucfirst($d) }}</span></label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div>
                        <label for="tag-input" class="mb-2 block text-sm font-medium text-slate-800 dark:text-slate-200">Tags</label>
                        <div class="input-field flex flex-wrap items-center gap-1.5 py-1.5" data-tags>
                            <div class="contents" data-tag-list>
                                @foreach ($tags as $tag)
                                    <span class="problem-tag" data-tag>{{ $tag }}<input type="hidden" name="tags[]" value="{{ $tag }}"><button type="button" aria-label="Remove tag {{ $tag }}" data-remove-tag>✕</button></span>
                                @endforeach
                            </div>
                            <input type="text" id="tag-input" class="min-w-[8rem] flex-1 border-0 bg-transparent p-1 text-sm outline-none focus:ring-0" placeholder="Type a tag and press Enter" maxlength="30" data-tag-input>
                        </div>
                    </div>
                </section>

                {{-- Statement --}}
                <section id="statement" class="card-surface scroll-mt-4 p-6">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-base font-bold text-slate-900 dark:text-slate-50">Statement <span class="text-xs font-normal text-slate-600 dark:text-slate-500">(markdown)</span></h2>
                        <div class="problem-seg" role="radiogroup" aria-label="Editor mode">
                            @foreach (['write' => 'Write', 'split' => 'Split', 'preview' => 'Preview'] as $mode => $label)
                                <label><input type="radio" name="_statement_mode" value="{{ $mode }}" @checked($mode === 'write') data-statement-mode><span>{{ $label }}</span></label>
                            @endforeach
                        </div>
                    </div>
                    <div class="mb-2 flex flex-wrap items-center gap-1" data-md-toolbar role="toolbar" aria-label="Formatting">
                        @foreach (['bold' => '<b>B</b>', 'italic' => '<i>I</i>', 'code' => '&lt;/&gt;', 'codeblock' => '{ }', 'ul' => '• List', 'ol' => '1. List', 'link' => 'Link'] as $action => $icon)
                            <button type="button" class="btn-ghost px-2 py-1 text-xs" data-md="{{ $action }}">{!! $icon !!}</button>
                        @endforeach
                        <span class="mx-2 h-4 w-px bg-slate-300 dark:bg-slate-700" aria-hidden="true"></span>
                        @foreach (['Input', 'Output', 'Constraints', 'Example'] as $heading)
                            <button type="button" class="btn-ghost px-2 py-1 text-xs" data-scaffold="{{ $heading }}">+ {{ $heading }}</button>
                        @endforeach
                    </div>
                    <div class="grid gap-4" data-statement-wrap>
                        <textarea name="description" id="description" rows="12" required maxlength="65535"
                            class="input-field resize-y font-mono text-sm" data-statement
                            placeholder="Read two integers from stdin and print their sum.">{{ old('description', $problem->description) }}</textarea>
                        <div class="hidden min-h-[8rem] rounded-xl border border-slate-200/95 p-4 dark:border-slate-800/90" data-statement-preview aria-live="polite">
                            <div class="note-prose problem-prose text-sm leading-relaxed text-slate-800 dark:text-slate-200" data-preview-body></div>
                        </div>
                    </div>
                    @error('description') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </section>

                {{-- Input format: function signature or stdin --}}
                <section id="format" class="card-surface scroll-mt-4 space-y-5 p-6" data-format>
                    <script type="application/json" data-sig-config>@json($sigConfig)</script>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-base font-bold text-slate-900 dark:text-slate-50">Input format</h2>
                            <p class="mt-1 text-xs text-slate-600 dark:text-slate-500" data-mode-text="function">Solvers write one function; its return value is compared to the expected value.</p>
                            <p class="mt-1 text-xs text-slate-600 dark:text-slate-500" data-mode-text="stdin">Solvers write a whole program that reads stdin and prints the answer.</p>
                        </div>
                        <div class="problem-seg" role="radiogroup" aria-label="Input format">
                            <label><input type="radio" name="io_mode" value="function" @checked($ioMode === 'function') data-io-mode><span>ƒ Function</span></label>
                            <label><input type="radio" name="io_mode" value="stdin" @checked($ioMode !== 'function') data-io-mode><span>Stdin / stdout</span></label>
                        </div>
                    </div>

                    @if ($sigErrors->isNotEmpty())
                        <div class="rounded-lg bg-rose-500/10 p-3 text-xs text-rose-700 dark:text-rose-300 is-error">
                            @foreach ($sigErrors as $msg) <p>{{ $msg }}</p> @endforeach
                        </div>
                    @endif

                    <div class="space-y-5" data-mode-panel="function">
                        <div class="grid gap-5 sm:grid-cols-[1fr_auto]">
                            <div>
                                <label for="sig-name" class="mb-2 block text-sm font-medium text-slate-800 dark:text-slate-200">Function name</label>
                                <input type="text" id="sig-name" name="signature[name]" maxlength="40" spellcheck="false" autocomplete="off"
                                    class="input-field font-mono text-sm" placeholder="twoSum" data-sig-name>
                                <p class="mt-1 text-xs text-rose-600" data-sig-name-error></p>
                            </div>
                            <div>
                                <label for="sig-returns" class="mb-2 block text-sm font-medium text-slate-800 dark:text-slate-200">Returns</label>
                                <select id="sig-returns" name="signature[returns]" class="input-field w-auto py-2 font-mono text-sm" data-sig-returns></select>
                            </div>
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-sm font-medium text-slate-800 dark:text-slate-200">Parameters <span class="text-xs font-normal text-slate-600 dark:text-slate-500" data-param-count></span></span>
                                <button type="button" class="btn-secondary text-xs" data-add-param>+ Add parameter</button>
                            </div>
                            <div class="space-y-2" data-params></div>
                            <p class="hidden rounded-lg border border-dashed border-slate-300 p-3 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-400" data-params-empty>
                                This function takes no arguments — tests only need an expected return value.
                            </p>
                        </div>

                        <div>
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <span class="text-sm font-medium text-slate-800 dark:text-slate-200">What solvers get</span>
                                <div class="problem-seg" role="radiogroup" aria-label="Preview language">
                                    @foreach (['python' => 'Python', 'javascript' => 'JavaScript', 'php' => 'PHP', 'go' => 'Go'] as $k => $l)
                                        <label><input type="radio" name="_sig_preview" value="{{ $k }}" @checked($k === 'python') data-sig-preview-lang><span>{{ $l }}</span></label>
                                    @endforeach
                                </div>
                            </div>
                            <pre class="problem-pre max-h-60" data-sig-preview></pre>
                        </div>

                        <details data-sig-paste>
                            <summary class="cursor-pointer text-xs font-semibold text-slate-600 dark:text-slate-400">Paste a signature instead…</summary>
                            <div class="mt-2 space-y-2">
                                <textarea rows="2" spellcheck="false" class="input-field font-mono text-xs" data-sig-paste-text
                                    placeholder="def twoSum(self, nums: List[int], target: int) -> List[int]:"></textarea>
                                <div class="flex items-center gap-3">
                                    <button type="button" class="btn-secondary text-xs" data-sig-paste-apply>Use this signature</button>
                                    <span class="text-xs text-slate-600 dark:text-slate-400" data-sig-paste-status></span>
                                </div>
                            </div>
                        </details>
                    </div>
                    <p class="text-xs text-slate-600 dark:text-slate-500" data-mode-panel="stdin">
                        Each test’s input is fed to the program on stdin and its printed output is compared with the expected output.
                        Switch to <strong>ƒ Function</strong> for LeetCode-style problems.
                    </p>
                </section>

                {{-- Test cases --}}
                <section id="tests" class="card-surface scroll-mt-4 p-6">
                    <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base font-bold text-slate-900 dark:text-slate-50">Test cases <span class="text-xs font-normal text-slate-600 dark:text-slate-500" data-test-count></span></h2>
                        <div class="flex gap-2">
                            <button type="button" class="btn-secondary text-xs" data-paste-open>Paste many</button>
                            <button type="button" class="btn-secondary text-xs" data-collapse-all>Collapse all</button>
                            <button type="button" class="btn-primary text-xs" data-add-test>+ Add test</button>
                        </div>
                    </div>
                    <p class="mb-4 text-xs text-slate-600 dark:text-slate-500">
                        <span data-mode-text="stdin">Solutions read stdin and print to stdout.</span>
                        <span data-mode-text="function">Enter each argument as JSON — <code>[1,2]</code>, <code>"text"</code>, <code>true</code>.</span>
                        <strong>Example</strong> tests are shown to the solver; <strong>🔒 Hidden</strong> tests only report pass/fail.
                    </p>
                    @error('tests') <p class="mb-2 text-xs text-rose-600">{{ $message }}</p> @enderror

                    <div class="space-y-3" data-tests>
                        @foreach ($tests as $i => $t)
                            @include('problems.partials.test-row', ['i' => $i, 't' => $t])
                        @endforeach
                    </div>
                    <template data-test-template>
                        @include('problems.partials.test-row', ['i' => '__INDEX__', 't' => ['input' => '', 'expected_output' => '', 'is_hidden' => false]])
                    </template>
                </section>

                {{-- Reference solution --}}
                <section id="solution" class="card-surface scroll-mt-4 space-y-4 p-6">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-slate-50">Reference solution <span class="text-xs font-normal text-slate-600 dark:text-slate-500">(optional, recommended)</span></h2>
                        <p class="mt-1 text-xs text-slate-600 dark:text-slate-500">
                            Write a correct solution once, then <strong>Check all</strong> to verify your tests, or <strong>Fill</strong> to generate the expected outputs for you.
                        </p>
                    </div>
                    @if (empty($languages))
                        <p class="text-sm text-slate-600 dark:text-slate-400">No language runtimes detected. Install one on the <a href="{{ route('playground.show') }}" class="link-accent-underline">Playground</a> page to use this.</p>
                    @else
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <select name="reference_language" class="input-field w-auto py-1.5 text-sm font-sans" data-ref-language aria-label="Reference language">
                                @foreach ($languages as $l)
                                    <option value="{{ $l['key'] }}" @selected($refLang === $l['key'])>{{ $l['label'] }}</option>
                                @endforeach
                            </select>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="btn-ghost text-xs" data-ref-stub data-mode-panel="function" title="Insert the function stub for this language">Insert stub</button>
                                <button type="button" class="btn-secondary text-xs" data-ref-run="check">Check all</button>
                                <button type="button" class="btn-secondary text-xs" data-ref-run="fill-empty">Fill empty outputs</button>
                                <button type="button" class="btn-secondary text-xs" data-ref-run="regenerate">Regenerate all</button>
                            </div>
                        </div>
                        <div class="overflow-hidden rounded-xl border border-slate-200/95 dark:border-slate-800/90">
                            <div class="problem-editor h-72" data-ref-editor></div>
                        </div>
                        <textarea name="reference_solution" class="hidden" data-ref-code>{{ old('reference_solution', $problem->reference_solution) }}</textarea>
                        <p class="text-xs text-slate-600 dark:text-slate-400" data-ref-status aria-live="polite"></p>
                    @endif
                    <label class="flex items-start gap-2 text-sm text-slate-800 dark:text-slate-200">
                        <input type="hidden" name="show_reference_solution" value="0">
                        <input type="checkbox" name="show_reference_solution" value="1" class="mt-1" @checked(old('show_reference_solution', $problem->show_reference_solution))>
                        <span>Show this solution to people who solve the problem
                            <span class="block text-xs text-slate-600 dark:text-slate-500">Only matters when the problem is shared. People you share it with “including your solution” can see it before solving.</span></span>
                    </label>
                </section>

                {{-- Hints --}}
                <section id="hints" class="card-surface scroll-mt-4 space-y-3 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-900 dark:text-slate-50">Hints <span class="text-xs font-normal text-slate-600 dark:text-slate-500">(optional)</span></h2>
                            <p class="mt-1 text-xs text-slate-600 dark:text-slate-500">Revealed one at a time on the solve page, easiest first.</p>
                        </div>
                        <button type="button" class="btn-secondary text-xs" data-add-hint>+ Add hint</button>
                    </div>
                    <div class="space-y-2" data-hints>
                        @foreach ($hints as $hint)
                            <div class="flex gap-2" data-hint-row>
                                <textarea name="hints[]" rows="2" maxlength="1000" class="input-field flex-1 resize-y font-sans text-sm" aria-label="Hint">{{ $hint }}</textarea>
                                <button type="button" class="btn-ghost self-start px-2 py-1 text-rose-600" data-remove-hint aria-label="Remove hint">✕</button>
                            </div>
                        @endforeach
                    </div>
                    <template data-hint-template>
                        <div class="flex gap-2" data-hint-row>
                            <textarea name="hints[]" rows="2" maxlength="1000" class="input-field flex-1 resize-y font-sans text-sm" aria-label="Hint"></textarea>
                            <button type="button" class="btn-ghost self-start px-2 py-1 text-rose-600" data-remove-hint aria-label="Remove hint">✕</button>
                        </div>
                    </template>
                </section>

                {{-- Settings --}}
                <section id="settings" class="card-surface scroll-mt-4 space-y-6 p-6">
                    <h2 class="text-base font-bold text-slate-900 dark:text-slate-50">Settings</h2>

                    <div>
                        <label for="time_limit_ms" class="mb-1 block text-sm font-medium text-slate-800 dark:text-slate-200">
                            Time limit per test: <span data-limit-label>{{ number_format($timeLimit / 1000, 1) }}s</span>
                        </label>
                        <input type="range" name="time_limit_ms" id="time_limit_ms" min="{{ \App\Models\Problem::MIN_TIME_LIMIT_MS }}" max="{{ \App\Models\Problem::MAX_TIME_LIMIT_MS }}" step="500" value="{{ $timeLimit }}" class="w-full max-w-md" data-limit>
                        <p class="mt-1 text-xs text-slate-600 dark:text-slate-500">Go function problems are compiled once before the tests, so the limit applies to running only. Go stdin problems still get the maximum.</p>
                        @error('time_limit_ms') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <fieldset>
                        <legend class="mb-2 text-sm font-medium text-slate-800 dark:text-slate-200">How answers are compared</legend>
                        <div class="grid gap-2 sm:grid-cols-2" data-mode-panel="function">
                            @foreach ($functionCompareModes as $value => [$title, $desc])
                                <label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200/95 p-3 has-[:checked]:border-accent has-[:checked]:bg-accent/5 dark:border-slate-800/90">
                                    <input type="radio" name="compare_mode" value="{{ $value }}" class="mt-0.5" @checked($compare === $value && $ioMode === 'function') data-compare>
                                    <span><span class="block text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $title }}</span><span class="block text-xs text-slate-600 dark:text-slate-400">{{ $desc }}</span></span>
                                </label>
                            @endforeach
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2" data-mode-panel="stdin">
                            @foreach ($compareModes as $value => [$title, $desc])
                                <label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200/95 p-3 has-[:checked]:border-accent has-[:checked]:bg-accent/5 dark:border-slate-800/90">
                                    <input type="radio" name="compare_mode" value="{{ $value }}" class="mt-0.5" @checked($compare === $value && $ioMode !== 'function') data-compare>
                                    <span><span class="block text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $title }}</span><span class="block text-xs text-slate-600 dark:text-slate-400">{{ $desc }}</span></span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <details>
                        <summary class="cursor-pointer text-sm font-medium text-slate-800 dark:text-slate-200">Starter code <span class="text-xs font-normal text-slate-600 dark:text-slate-500">(optional — overrides the generated template per language)</span></summary>
                        <div class="mt-3 space-y-4">
                            @foreach ($allLanguages as $key => $label)
                                <div>
                                    <label for="starter-{{ $key }}" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400">{{ $label }}</label>
                                    <textarea id="starter-{{ $key }}" name="starter_code[{{ $key }}]" rows="5" spellcheck="false" class="input-field resize-y font-mono text-xs"
                                        placeholder="{{ $starterDefaults[$key] ?? '' }}" data-starter-override="{{ $key }}" data-stdin-placeholder="{{ $starterDefaults[$key] ?? '' }}">{{ $starterValues[$key] ?? '' }}</textarea>
                                </div>
                            @endforeach
                        </div>
                    </details>
                </section>

                {{-- Danger zone is a separate form below; keep layout spacing --}}
                @if ($editing && ($otherSolvers ?? 0) > 0)
                    <p class="rounded-xl bg-amber-500/10 px-4 py-3 text-xs text-amber-800 dark:text-amber-300">
                        {{ $otherSolvers }} other {{ $otherSolvers === 1 ? 'person has' : 'people have' }} submitted to this problem.
                        Changing the tests, function signature, comparison or time limit marks their earlier solutions as written for an older version (they stay solved).
                    </p>
                @endif

                <div class="problem-savebar" data-savebar>
                    <p class="text-xs text-slate-600 dark:text-slate-400" data-dirty-label aria-live="polite">No changes yet</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ $editing ? route('problems.show', $problem) : route('problems.index') }}" class="btn-secondary text-xs">Cancel</a>
                        <button type="submit" name="continue" value="1" class="btn-secondary text-xs">Save &amp; keep editing</button>
                        <button type="submit" class="btn-primary text-xs">{{ $editing ? 'Save changes' : 'Create problem' }} <kbd class="ml-1 hidden text-[10px] opacity-70 sm:inline">⌘S</kbd></button>
                    </div>
                </div>

                @if ($editing)
                    <div class="rounded-2xl border border-rose-300/60 p-5 dark:border-rose-900/50">
                        <h2 class="text-sm font-bold text-rose-700 dark:text-rose-400">Danger zone</h2>
                        <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">
                            Deleting removes this problem, its test cases and all {{ $problem->submissions()->count() }} submissions{{ ($otherSolvers ?? 0) > 0 ? " — including those of {$otherSolvers} other ".($otherSolvers === 1 ? 'person' : 'people') : '' }}. This can’t be undone.
                        </p>
                        <button type="submit" form="delete-problem-form" class="btn-secondary mt-3 text-xs text-rose-600"
                            onclick="return confirm({{ Js::from(($otherSolvers ?? 0) > 0 ? "Delete this problem? {$otherSolvers} other ".($otherSolvers === 1 ? 'person' : 'people').' will lose their submissions and solutions too.' : 'Delete this problem and its submissions?') }})">Delete problem</button>
                    </div>
                @endif
            </div>
        </div>

        <dialog data-paste-dialog class="m-auto w-[min(40rem,94vw)] rounded-2xl border border-slate-200 bg-white p-6 text-slate-900 backdrop:bg-black/50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
            <h2 class="text-base font-bold">Paste many test cases</h2>
            <p class="mt-1 mb-3 text-xs text-slate-600 dark:text-slate-400">
                Separate input from expected output with a line containing <code>---</code>, and one case from the next with a line containing <code>===</code>.
                <span data-mode-text="function">For function problems put one argument per line — or paste LeetCode examples as they are (<code>Input: nums = [2,7], target = 9</code> / <code>Output: [0,1]</code>).</span>
            </p>
            <textarea rows="12" spellcheck="false" class="input-field resize-y font-mono text-xs" data-paste-text
                placeholder="1 2&#10;---&#10;3&#10;===&#10;5 5&#10;---&#10;10"></textarea>
            <label class="mt-3 flex items-center gap-2 text-xs"><input type="checkbox" checked data-paste-hidden> Mark the new cases as hidden</label>
            <div class="mt-4 flex items-center justify-between">
                <span class="text-xs text-slate-600 dark:text-slate-400" data-paste-count>0 cases parsed</span>
                <div class="flex gap-2">
                    <button type="button" class="btn-secondary text-xs" data-paste-cancel>Cancel</button>
                    <button type="button" class="btn-primary text-xs" data-paste-import>Add cases</button>
                </div>
            </div>
        </dialog>
    </form>

    @if ($editing)
        <form id="delete-problem-form" action="{{ route('problems.destroy', $problem) }}" method="post" class="hidden">@csrf @method('DELETE')</form>
    @endif
@endsection
