<?php

namespace App\Support;

/**
 * The function a solver implements in a function-style problem, e.g.
 * `twoSum(nums: int[], target: int) -> int[]`. It may take any number of
 * parameters, including none.
 *
 * Test-case storage format: `input` holds one minified JSON value per line,
 * in parameter order (empty for zero parameters); `expected_output` holds
 * the JSON of the return value.
 */
final class FunctionSignature
{
    public const SCALARS = ['int', 'float', 'string', 'bool'];

    public const TYPES = [
        'int', 'float', 'string', 'bool',
        'int[]', 'float[]', 'string[]', 'bool[]',
        'int[][]', 'float[][]', 'string[][]', 'bool[][]',
    ];

    public const MAX_PARAMS = 8;

    /** Languages with a harness. */
    public const LANGUAGES = ['python', 'javascript', 'php', 'go'];

    /**
     * Function names that can't work in Go: a package-level function with one
     * of these names would shadow a builtin or import the Go harness relies on.
     * Problems using one simply don't offer Go (they stay valid elsewhere).
     */
    public const GO_CONFLICTS = [
        'len', 'append', 'string', 'int', 'bool', 'float64', 'nil', 'true', 'false', 'error',
        'os', 'io', 'json', 'reflect', 'strings', 'init', 'main',
    ];

    /**
     * Names that would clash with a keyword, builtin or the harness in at
     * least one supported language.
     */
    public const RESERVED = [
        // Python
        'False', 'None', 'True', 'and', 'as', 'assert', 'async', 'await', 'break', 'class', 'continue', 'def', 'del',
        'elif', 'else', 'except', 'finally', 'for', 'from', 'global', 'if', 'import', 'in', 'is', 'lambda', 'nonlocal',
        'not', 'or', 'pass', 'raise', 'return', 'try', 'while', 'with', 'yield', 'print', 'input', 'len', 'list', 'dict',
        'set', 'str', 'int', 'float', 'bool', 'range', 'map', 'filter', 'sum', 'min', 'max', 'type', 'object', 'self',
        // JavaScript
        'case', 'catch', 'const', 'debugger', 'default', 'delete', 'do', 'export', 'extends', 'false', 'function',
        'instanceof', 'let', 'new', 'null', 'super', 'switch', 'this', 'throw', 'true', 'typeof', 'var', 'void',
        'enum', 'implements', 'interface', 'package', 'private', 'protected', 'public', 'static', 'arguments', 'eval',
        'undefined', 'require', 'module', 'process', 'console',
        // PHP
        'abstract', 'array', 'callable', 'clone', 'declare', 'echo', 'empty', 'enddeclare', 'endfor', 'endforeach',
        'endif', 'endswitch', 'endwhile', 'exit', 'die', 'final', 'fn', 'foreach', 'goto', 'include', 'include_once',
        'insteadof', 'isset', 'match', 'namespace', 'readonly', 'require_once', 'trait', 'unset', 'use', 'list',
        // Go (future) + harness
        'func', 'main', 'chan', 'defer', 'go', 'map', 'range', 'select', 'struct', 'fallthrough', 'Solution',
    ];

    /**
     * @param  list<array{name:string, type:string}>  $params
     */
    public function __construct(
        public readonly string $name,
        public readonly array $params,
        public readonly string $returns,
    ) {}

    /** Build from stored/validated data; null when it isn't a usable signature. */
    public static function fromArray(mixed $data): ?self
    {
        if (! is_array($data)) {
            return null;
        }
        $name = $data['name'] ?? null;
        $returns = $data['returns'] ?? null;
        // Stored signatures are checked for syntax only: the reserved-word list is
        // enforced when saving, so extending it can never strand old problems.
        if (! is_string($name) || ! self::isIdentifier($name) || ! in_array($returns, self::TYPES, true)) {
            return null;
        }

        $params = [];
        foreach (array_values(is_array($data['params'] ?? null) ? $data['params'] : []) as $p) {
            $pName = is_array($p) ? ($p['name'] ?? null) : null;
            $pType = is_array($p) ? ($p['type'] ?? null) : null;
            if (! is_string($pName) || ! self::isIdentifier($pName) || ! in_array($pType, self::TYPES, true)) {
                return null;
            }
            $params[] = ['name' => $pName, 'type' => $pType];
        }

        $names = array_column($params, 'name');
        if (count($params) > self::MAX_PARAMS || count($names) !== count(array_unique($names))) {
            return null;
        }

        return new self($name, $params, $returns);
    }

    /** @return array{name:string, params:list<array{name:string, type:string}>, returns:string} */
    public function toArray(): array
    {
        return ['name' => $this->name, 'params' => $this->params, 'returns' => $this->returns];
    }

    public function supports(string $language): bool
    {
        if ($language === 'go' && in_array($this->name, self::GO_CONFLICTS, true)) {
            return false;
        }

        return in_array($language, self::LANGUAGES, true);
    }

    public static function isIdentifier(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,39}$/', $name);
    }

    public static function isValidName(string $name): bool
    {
        return self::isIdentifier($name)
            && ! in_array($name, self::RESERVED, true)
            && ! str_starts_with($name, '_ht');
    }

    /**
     * Decode a test's `input` into one value per parameter.
     *
     * @return array{0: ?list<mixed>, 1: ?string} [values, error]
     */
    public function decodeArgs(?string $input): array
    {
        $text = rtrim(str_replace("\r\n", "\n", (string) $input), "\n");
        $lines = $text === '' ? [] : explode("\n", $text);
        $count = count($this->params);

        if ($count === 0) {
            return trim($text) === '' ? [[], null] : [null, 'This function takes no arguments, so the input must be empty.'];
        }
        if (count($lines) !== $count) {
            return [null, sprintf('Expected %d %s (one per line), got %d.', $count, $count === 1 ? 'value' : 'values', count($lines))];
        }

        $values = [];
        foreach ($this->params as $i => $p) {
            [$ok, $value] = self::decode($lines[$i]);
            if (! $ok) {
                return [null, "`{$p['name']}` isn't valid JSON".($p['type'] === 'string' ?' — wrap text in double quotes.' : '.')];
            }
            if (! self::matchesType($value, $p['type'])) {
                return [null, "`{$p['name']}` should be ".self::typeLabel($p['type']).', got '.self::describe($value).'.'];
            }
            $values[] = $value;
        }

        return [$values, null];
    }

    /** Validate an expected return value; returns an error message or null. */
    public function returnError(?string $json): ?string
    {
        [$ok, $value] = self::decode((string) $json);
        if (! $ok) {
            return 'The expected return value isn’t valid JSON'.($this->returns === 'string' ? ' — wrap text in double quotes.' : '.');
        }

        return self::matchesType($value, $this->returns)
            ? null
            : 'The expected return value should be '.self::typeLabel($this->returns).', got '.self::describe($value).'.';
    }

    /** Re-encode one-value-per-line input canonically (assumes it decoded). */
    public function canonicalInput(?string $input): string
    {
        [$values] = $this->decodeArgs($input);

        return implode("\n", array_map(self::encode(...), $values ?? []));
    }

    /**
     * Parameter name → raw JSON for display ("nums = [1,2]").
     *
     * @return list<array{name:string, value:string}>
     */
    public function namedArgs(?string $input): array
    {
        $lines = trim((string) $input) === '' ? [] : explode("\n", rtrim(str_replace("\r\n", "\n", (string) $input), "\n"));

        return array_map(fn ($p, $i) => ['name' => $p['name'], 'value' => $lines[$i] ?? ''], $this->params, array_keys($this->params));
    }

    /** @return array{0: bool, 1: mixed} */
    public static function decode(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [false, null];
        }
        try {
            return [true, json_decode($json, false, 64, JSON_THROW_ON_ERROR)];
        } catch (\JsonException) {
            return [false, null];
        }
    }

    public static function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    public static function canonical(string $json): string
    {
        [$ok, $value] = self::decode($json);

        return $ok ? self::encode($value) : trim($json);
    }

    public static function matchesType(mixed $value, string $type): bool
    {
        if (str_ends_with($type, '[]')) {
            if (! is_array($value) || ! array_is_list($value)) {
                return false;
            }
            $inner = substr($type, 0, -2);
            foreach ($value as $item) {
                if (! self::matchesType($item, $inner)) {
                    return false;
                }
            }

            return true;
        }

        return match ($type) {
            'int' => is_int($value),
            'float' => is_int($value) || is_float($value),
            'string' => is_string($value),
            'bool' => is_bool($value),
            default => false,
        };
    }

    /**
     * Compare a returned value with the expected one. Strict about types
     * (`true` ≠ `1`, `"9"` ≠ `9`); floats compare within the judge tolerance.
     * With `$unordered`, the top-level list may come back in any order.
     */
    public static function valuesEqual(mixed $expected, mixed $actual, string $type, bool $unordered = false): bool
    {
        if (str_ends_with($type, '[]')) {
            if (! is_array($actual) || ! array_is_list($actual) || ! is_array($expected) || count($actual) !== count($expected)) {
                return false;
            }
            if ($unordered) {
                $key = fn ($v) => self::encode($v);
                usort($expected, fn ($a, $b) => strcmp($key($a), $key($b)));
                usort($actual, fn ($a, $b) => strcmp($key($a), $key($b)));
            }
            $inner = substr($type, 0, -2);
            foreach ($expected as $i => $e) {
                if (! self::valuesEqual($e, $actual[$i], $inner)) {
                    return false;
                }
            }

            return true;
        }

        return match ($type) {
            'float' => (is_int($actual) || is_float($actual)) && (is_int($expected) || is_float($expected))
                && abs($expected - $actual) <= 1e-6 * max(1.0, abs((float) $expected)),
            default => $expected === $actual,
        };
    }

    /** A targeted hint when a returned value is of the wrong shape, else null. */
    public static function mismatchHint(mixed $expected, mixed $actual, string $type): ?string
    {
        if ($actual === null) {
            return 'Your function returned nothing (null / None). Did you forget to `return` the answer?';
        }
        if (! self::matchesType($actual, $type)) {
            return 'Your function returned '.self::describe($actual).', but '.self::typeLabel($type).' was expected.';
        }
        if (is_array($actual) && is_array($expected) && count($actual) !== count($expected)) {
            return sprintf('You returned %d %s; %d expected.', count($actual), count($actual) === 1 ? 'item' : 'items', count($expected));
        }
        if (is_array($actual) && is_array($expected)) {
            $a = $actual;
            $e = $expected;
            sort($a);
            sort($e);
            if (self::encode($a) === self::encode($e)) {
                return 'The right items, but in a different order.';
            }
        }

        return null;
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'int' => 'an int',
            'float' => 'a number',
            'string' => 'a string',
            'bool' => 'a boolean',
            default => 'a list ('.$type.')',
        };
    }

    public static function describe(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'a boolean',
            is_int($value) => 'an int',
            is_float($value) => 'a float',
            is_string($value) => 'a string',
            is_array($value) => 'a list',
            default => 'an object',
        };
    }
}
