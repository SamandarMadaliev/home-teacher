<?php

namespace App\Http\Requests;

use App\Models\Problem;
use App\Services\CodeRunner;
use App\Services\LanguageRuntimeProbe;
use App\Support\FunctionSignature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProblemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $languages = array_keys(LanguageRuntimeProbe::LANGUAGES);

        return [
            'title' => ['required', 'string', 'max:255'],
            'difficulty' => ['required', Rule::in(Problem::DIFFICULTIES)],
            'description' => ['required', 'string', 'max:65535'],
            'io_mode' => ['nullable', Rule::in(Problem::IO_MODES)],
            'signature' => ['exclude_unless:io_mode,function', 'required', 'array'],
            'signature.name' => ['exclude_unless:io_mode,function', 'required', 'string', $this->identifierRule('Function name')],
            'signature.params' => ['exclude_unless:io_mode,function', 'nullable', 'array', 'max:'.FunctionSignature::MAX_PARAMS],
            'signature.params.*.name' => ['exclude_unless:io_mode,function', 'required', 'string', 'distinct', $this->identifierRule('Parameter name')],
            'signature.params.*.type' => ['exclude_unless:io_mode,function', 'required', Rule::in(FunctionSignature::TYPES)],
            'signature.returns' => ['exclude_unless:io_mode,function', 'required', Rule::in(FunctionSignature::TYPES)],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['nullable', 'string', 'max:30'],
            'hints' => ['nullable', 'array', 'max:10'],
            'hints.*' => ['nullable', 'string', 'max:1000'],
            'reference_solution' => ['nullable', 'string', 'max:'.CodeRunner::MAX_CODE_BYTES],
            'reference_language' => ['nullable', Rule::in($languages)],
            'starter_code' => ['nullable', 'array'],
            'starter_code.*' => ['nullable', 'string', 'max:'.CodeRunner::MAX_CODE_BYTES],
            'time_limit_ms' => ['nullable', 'integer', 'between:'.Problem::MIN_TIME_LIMIT_MS.','.Problem::MAX_TIME_LIMIT_MS],
            'compare_mode' => ['nullable', Rule::in(Problem::COMPARE_MODES)],
            'tests' => ['required', 'array', 'min:1', 'max:50'],
            'tests.*.id' => ['nullable', 'integer'],
            'tests.*.label' => ['nullable', 'string', 'max:80'],
            'tests.*.input' => ['nullable', 'string', 'max:'.CodeRunner::MAX_STDIN_BYTES],
            'tests.*.expected_output' => ['required', 'string', 'max:'.CodeRunner::MAX_OUTPUT_BYTES],
            'tests.*.is_hidden' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'tests.*.expected_output.required' => 'Test :position needs an expected output.',
            'tests.*.input.max' => 'Test :position input is too large.',
            'signature.params.*.name.distinct' => 'Two parameters can’t share a name.',
            'signature.params.max' => 'A function can take at most '.FunctionSignature::MAX_PARAMS.' parameters.',
        ];
    }

    private function identifierRule(string $what): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($what) {
            if (! is_string($value) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,39}$/', $value)) {
                $fail("{$what} must start with a letter or _ and contain only letters, digits and _.");
            } elseif (! FunctionSignature::isValidName($value)) {
                $fail("{$what} “{$value}” is a reserved word in one of the supported languages.");
            }
        };
    }

    /** Function problems: every test must match the signature. */
    public function after(): array
    {
        return [function (Validator $v) {
            if (! $this->isFunction() || $v->errors()->hasAny(['signature', 'signature.*', 'signature.params.*', 'tests'])) {
                return;
            }
            $sig = FunctionSignature::fromArray($this->input('signature'));
            if ($sig === null) {
                $v->errors()->add('signature', 'The function signature is incomplete.');

                return;
            }
            foreach (array_values((array) $this->input('tests', [])) as $i => $t) {
                $n = $i + 1;
                if ($error = $sig->decodeArgs($t['input'] ?? null)[1]) {
                    $v->errors()->add("tests.$i.input", "Test {$n}: {$error}");
                }
                if (($t['expected_output'] ?? '') !== '' && ($error = $sig->returnError($t['expected_output']))) {
                    $v->errors()->add("tests.$i.expected_output", "Test {$n}: {$error}");
                }
            }
        }];
    }

    public function isFunction(): bool
    {
        return $this->input('io_mode') === Problem::IO_FUNCTION;
    }

    /**
     * Problem columns, normalised (tags de-duplicated, empty hints dropped,
     * unknown starter-code languages ignored).
     *
     * @return array<string, mixed>
     */
    public function problemAttributes(): array
    {
        $d = $this->validated();
        $known = array_keys(LanguageRuntimeProbe::LANGUAGES);

        $tags = collect($d['tags'] ?? [])
            ->map(fn ($t) => mb_strtolower(trim((string) $t)))
            ->filter()->unique()->values()->all();

        $hints = collect($d['hints'] ?? [])
            ->map(fn ($h) => trim((string) $h))
            ->filter()->values()->all();

        $starter = collect($d['starter_code'] ?? [])
            ->filter(fn ($code, $lang) => in_array($lang, $known, true) && is_string($code) && trim($code) !== '')
            ->all();

        $reference = trim((string) ($d['reference_solution'] ?? '')) === '' ? null : $d['reference_solution'];

        $fn = $this->isFunction();
        $compareModes = $fn ? Problem::FUNCTION_COMPARE_MODES : Problem::STDIN_COMPARE_MODES;
        $compare = in_array($d['compare_mode'] ?? null, $compareModes, true) ? $d['compare_mode'] : $compareModes[0];

        return [
            'title' => $d['title'],
            'difficulty' => $d['difficulty'],
            'description' => $d['description'],
            'io_mode' => $fn ? Problem::IO_FUNCTION : Problem::IO_STDIN,
            'signature' => $fn ? FunctionSignature::fromArray($d['signature'])?->toArray() : null,
            'tags' => $tags ?: null,
            'hints' => $hints ?: null,
            'reference_solution' => $reference,
            'reference_language' => $reference === null ? null : ($d['reference_language'] ?? null),
            'starter_code' => $starter ?: null,
            'time_limit_ms' => $d['time_limit_ms'] ?? Problem::DEFAULT_TIME_LIMIT_MS,
            'compare_mode' => $compare,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function tests(): array
    {
        $tests = array_values($this->validated()['tests']);
        $sig = $this->isFunction() ? FunctionSignature::fromArray($this->validated()['signature']) : null;
        if ($sig === null) {
            return $tests;
        }

        // Store arguments and return values canonically (minified JSON).
        return array_map(fn ($t) => [
            ...$t,
            'input' => $sig->canonicalInput($t['input'] ?? null),
            'expected_output' => FunctionSignature::canonical($t['expected_output']),
        ], $tests);
    }
}
