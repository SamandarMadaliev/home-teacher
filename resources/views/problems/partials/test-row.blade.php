@php
    $hasError = isset($errors) && is_numeric($i) && ($errors->has("tests.$i.expected_output") || $errors->has("tests.$i.input"));
@endphp
<div class="problem-test-row {{ $hasError ? 'is-error' : '' }}" data-test-row>
    <input type="hidden" name="tests[{{ $i }}][id]" value="{{ $t['id'] ?? '' }}">

    <div class="flex flex-wrap items-center gap-2 px-3 py-2">
        <span class="cursor-grab select-none px-1 text-slate-400" data-drag-handle title="Drag to reorder" aria-hidden="true">⠿</span>
        <span class="w-14 shrink-0 text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400" data-test-label>Test</span>

        <div class="problem-seg" role="radiogroup" aria-label="Visibility">
            <label><input type="radio" name="tests[{{ $i }}][is_hidden]" value="0" @checked(empty($t['is_hidden']))><span>Example</span></label>
            <label><input type="radio" name="tests[{{ $i }}][is_hidden]" value="1" @checked(! empty($t['is_hidden']))><span>🔒 Hidden</span></label>
        </div>

        <button type="button" class="min-w-0 flex-1 truncate text-left font-mono text-xs text-slate-600 dark:text-slate-400" data-test-summary data-toggle-test></button>
        <span class="text-xs font-semibold" data-test-status aria-live="polite"></span>

        <div class="flex items-center gap-1 text-xs">
            <button type="button" class="btn-ghost px-2 py-1" data-toggle-test aria-label="Expand or collapse test">⌄</button>
            <button type="button" class="btn-ghost px-2 py-1" data-duplicate-test aria-label="Duplicate test">⧉</button>
            <button type="button" class="btn-ghost px-2 py-1 text-rose-600" data-remove-test aria-label="Remove test">✕</button>
        </div>
    </div>

    <div class="problem-test-body space-y-3 border-t border-slate-200/80 p-3 dark:border-slate-800/80">
        <input type="text" name="tests[{{ $i }}][label]" value="{{ $t['label'] ?? '' }}" maxlength="80"
            class="input-field font-sans text-sm" placeholder="Optional label — e.g. “empty input” or “large n”" aria-label="Test label">
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                {{-- Function problems: one field per parameter, filled in by form.js; serialised into the input textarea. --}}
                <div class="space-y-2" data-args></div>
                <div data-stdin-wrap>
                    <div class="mb-1 flex justify-between text-xs text-slate-600 dark:text-slate-400">
                        <label for="t{{ $i }}-in">Input (stdin)</label><span data-count="input"></span>
                    </div>
                    <textarea id="t{{ $i }}-in" name="tests[{{ $i }}][input]" rows="4" spellcheck="false" class="input-field resize-y font-mono text-sm" data-field="input">{{ $t['input'] ?? '' }}</textarea>
                </div>
            </div>
            <div>
                <div class="mb-1 flex justify-between text-xs text-slate-600 dark:text-slate-400">
                    <label for="t{{ $i }}-out" data-expected-label>Expected output</label><span data-count="expected"></span>
                </div>
                <textarea id="t{{ $i }}-out" name="tests[{{ $i }}][expected_output]" rows="4" spellcheck="false" class="input-field resize-y font-mono text-sm" data-field="expected">{{ $t['expected_output'] ?? '' }}</textarea>
                <p class="mt-1 hidden text-xs text-rose-600" data-expected-note></p>
                @if ($hasError)
                    <p class="mt-1 text-xs text-rose-600">{{ $errors->first("tests.$i.expected_output") ?: $errors->first("tests.$i.input") }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
