<?php

namespace App\Services;

use App\Support\FunctionSignature;

/**
 * Turns a solver's function into a runnable program.
 *
 * The solver's code comes first, unchanged, so error line numbers match the
 * editor. A generated harness is appended: it reads one JSON value per line
 * from stdin, calls the function (or `Solution().<name>` when the code is a
 * LeetCode-style class) and prints `<marker><json result>`. Everything the
 * solver printed before the marker is their own debug output.
 *
 * The marker is random per run so solver code can't fake a return value.
 */
class FunctionHarness
{
    public static function marker(): string
    {
        return '@@HT_RETURN_'.bin2hex(random_bytes(8)).'@@';
    }

    public function wrap(string $language, FunctionSignature $sig, string $code, string $marker): string
    {
        $name = $sig->name; // validated identifier, safe to interpolate
        $count = count($sig->params);

        return match ($language) {
            'python' => $code."\n\n".$this->python($name, $count, $marker),
            'javascript' => $code."\n\n".$this->javascript($name, $count, $marker),
            'php' => $this->phpPrefix($code).$this->php($name, $count, $marker),
            default => throw new \InvalidArgumentException("Function-style problems don't support {$language} yet."),
        };
    }

    /**
     * Go can't take an appended harness (imports must come first in a file),
     * so the solver's code becomes `solution.go` and the harness `main.go`,
     * both in package main. Line numbers in solution.go match the editor.
     *
     * @return array{0: ?array<string, string>, 1: ?string} [files, friendly error]
     */
    public function goFiles(FunctionSignature $sig, string $code, string $marker): array
    {
        if (preg_match('/^\s*func\s+main\s*\(\s*\)/m', $code)) {
            return [null, "Remove your `func main()` — the judge calls `{$sig->name}` for you."];
        }

        return [['solution.go' => self::withMainPackage($code), 'main.go' => $this->goMain($sig, $marker)], null];
    }

    /**
     * Ensure the code is in package main without shifting any line: a missing
     * clause is prefixed onto line 1 (`package main; …`), another package
     * name is renamed in place. LeetCode pastes have no package clause.
     */
    public static function withMainPackage(string $code): string
    {
        $lead = '(?:\s+|\/\/[^\n]*|\/\*.*?\*\/)*';
        if (preg_match('/\A('.$lead.')package\s+(\w+)/s', $code, $m, PREG_OFFSET_CAPTURE)) {
            return $m[2][0] === 'main' ? $code : substr_replace($code, 'main', $m[2][1], strlen($m[2][0]));
        }

        return 'package main; '.$code;
    }

    public static function goType(string $type): string
    {
        $depth = substr_count($type, '[]');
        $base = ['int' => 'int', 'float' => 'float64', 'string' => 'string', 'bool' => 'bool'][str_replace('[]', '', $type)];

        return str_repeat('[]', $depth).$base;
    }

    /** `func twoSum(nums []int, target int) []int` */
    public static function goSignature(FunctionSignature $sig): string
    {
        $params = implode(', ', array_map(fn ($p) => $p['name'].' '.self::goType($p['type']), $sig->params));

        return "func {$sig->name}({$params}) ".self::goType($sig->returns);
    }

    /**
     * Turn `go build` output into one friendly sentence (or null).
     * Errors in main.go can only mean the solver's function doesn't fit.
     */
    public static function explainGoCompile(string $output, FunctionSignature $sig): ?string
    {
        if (preg_match('/main\.go:\d+:\d+: undefined: '.preg_quote($sig->name, '/').'\b/', $output)) {
            return "Function `{$sig->name}` is not defined. Did you rename it?";
        }
        if (preg_match('/main\.go:\d+/', $output) && ! preg_match('/solution\.go:\d+/', $output)) {
            return 'Your function must have exactly this signature: `'.self::goSignature($sig).'`';
        }
        if (preg_match('/GOPROXY=off|cannot find module providing package|is not in std/', $output)) {
            return 'Only the Go standard library is available.';
        }
        if (preg_match('/solution\.go:\d+:\d+: undefined: ([a-z][a-z0-9]*)\b/', $output, $m)
            && in_array($m[1], ['sort', 'strings', 'strconv', 'math', 'fmt', 'slices', 'maps', 'unicode', 'bytes', 'container', 'bits', 'cmp'], true)) {
            return "Add `import \"{$m[1]}\"` at the top of your code.";
        }
        if (str_contains($output, 'imported and not used')) {
            return 'Go refuses to compile unused imports — remove the ones you don’t use.';
        }
        if (str_contains($output, 'declared and not used')) {
            return 'Go refuses to compile unused variables — use or remove them.';
        }
        if (str_contains($output, 'missing return')) {
            return 'Every path through your function must end with a `return`.';
        }

        return null;
    }

    private function goMain(FunctionSignature $sig, string $marker): string
    {
        $decls = '';
        $args = [];
        foreach ($sig->params as $i => $p) {
            $decls .= "\tvar a{$i} ".self::goType($p['type'])."\n\t_htDecode(_htLines, {$i}, \"{$p['name']}\", &a{$i})\n";
            $args[] = "a{$i}";
        }
        // Go rejects unused variables, so zero-parameter functions skip stdin.
        $read = $sig->params ? "\t_htLines := _htReadLines()\n" : '';
        $call = $sig->name.'('.implode(', ', $args).')';

        return <<<GO
package main

// Generated judge harness — every name starts with _ht so it can't clash
// with the solver's helpers, and it avoids builtins solvers often redefine.

import (
	"encoding/json"
	"io"
	"os"
	"reflect"
	"strings"
)

func main() {
{$read}{$decls}	_htEmit({$call})
}

func _htReadLines() []string {
	b, _ := io.ReadAll(os.Stdin)
	return strings.Split(string(b), "\\n")
}

func _htDecode(lines []string, i int, name string, dst interface{}) {
	if i >= len(lines) {
		os.Stderr.WriteString("Missing argument " + name + "\\n")
		os.Exit(1)
	}
	if err := json.Unmarshal([]byte(lines[i]), dst); err != nil {
		os.Stderr.WriteString("Bad argument " + name + ": " + err.Error() + "\\n")
		os.Exit(1)
	}
}

// nil slices encode as null; normalise them, at any depth, to [].
func _htNormalize(v reflect.Value) interface{} {
	if v.Kind() == reflect.Slice {
		out := []interface{}{}
		for i := 0; i < v.Len(); i++ {
			out = append(out, _htNormalize(v.Index(i)))
		}
		return out
	}
	return v.Interface()
}

func _htEmit(result interface{}) {
	var sb strings.Builder
	enc := json.NewEncoder(&sb)
	enc.SetEscapeHTML(false)
	if err := enc.Encode(_htNormalize(reflect.ValueOf(result))); err != nil {
		os.Stderr.WriteString("Your function returned a value that can't be compared: " + err.Error() + "\\n")
		os.Exit(1)
	}
	os.Stdout.WriteString("\\n{$marker}" + sb.String())
}

GO;
    }

    /**
     * Split the raw run output into the solver's prints and the returned JSON.
     *
     * @param  array<string, mixed>  $run  a {@see CodeRunner::run()} result
     * @return array<string, mixed> the run plus `returned` (?string JSON) and `prints` (string)
     */
    public function extract(array $run, string $marker): array
    {
        $stdout = (string) $run['stdout'];
        $pos = strrpos($stdout, $marker);

        if ($pos === false) {
            $run['returned'] = null;
            $run['prints'] = $stdout;
            if (! $run['timed_out'] && $run['exit_code'] === 0) {
                $run['stderr'] = trim($run['stderr']."\nYour program stopped before your function returned. Did it call exit()?");
            }

            return $run;
        }

        $prints = rtrim(substr($stdout, 0, $pos), "\n");
        $run['prints'] = $prints;
        $run['returned'] = trim(substr($stdout, $pos + strlen($marker)));

        return $run;
    }

    private function python(string $name, int $count, string $marker): string
    {
        return <<<PY
# ── harness: calls your function with each test's arguments ──
def _ht_main():
    import sys as _sys, json as _json
    _lines = _sys.stdin.read().split("\\n")
    _args = [_json.loads(_lines[_i]) for _i in range({$count})]
    _fn = globals().get("{$name}")
    if not callable(_fn):
        _cls = globals().get("Solution")
        _fn = getattr(_cls(), "{$name}", None) if isinstance(_cls, type) else None
    if not callable(_fn):
        _sys.stderr.write("Function `{$name}` is not defined. Did you rename it?\\n")
        _sys.exit(1)
    _result = _fn(*_args)

    def _default(_o):
        if isinstance(_o, (set, frozenset)):
            raise TypeError("returned a set — return a list instead")
        raise TypeError("returned a value of type " + type(_o).__name__ + ", which can't be compared")

    try:
        _out = _json.dumps(_result, separators=(",", ":"), ensure_ascii=False, allow_nan=False, default=_default)
    except (TypeError, ValueError) as _e:
        _sys.stderr.write("Your function " + str(_e) + ".\\n")
        _sys.exit(1)
    _sys.stdout.write("\\n{$marker}" + _out + "\\n")
    _sys.stdout.flush()


_ht_main()

PY;
    }

    private function javascript(string $name, int $count, string $marker): string
    {
        return <<<JS
// ── harness: calls your function with each test's arguments ──
;(async () => {
    // The runner dir may resolve as an ES module, where `require` is undefined.
    const _htFs = typeof require === 'function' ? require('fs') : process.getBuiltinModule('fs');
    const _htLines = _htFs.readFileSync(0, 'utf8').split('\\n');
    const _htArgs = [];
    for (let i = 0; i < {$count}; i++) _htArgs.push(JSON.parse(_htLines[i]));
    let _htFn = typeof {$name} === 'function' ? {$name} : null;
    if (!_htFn && typeof Solution === 'function') {
        const s = new Solution();
        if (typeof s.{$name} === 'function') _htFn = s.{$name}.bind(s);
    }
    if (!_htFn) {
        process.stderr.write('Function `{$name}` is not defined. Did you rename it?\\n');
        process.exit(1);
    }
    let _htResult = await _htFn(..._htArgs);
    if (_htResult === undefined) _htResult = null;
    if (_htResult instanceof Set || _htResult instanceof Map) {
        process.stderr.write('Your function returned a ' + _htResult.constructor.name + ' — return an array instead.\\n');
        process.exit(1);
    }
    if (ArrayBuffer.isView(_htResult)) _htResult = Array.from(_htResult);
    process.stdout.write('\\n{$marker}' + JSON.stringify(_htResult) + '\\n');
})();

JS;
    }

    /** Make sure the appended harness lands in PHP mode, not inline HTML. */
    private function phpPrefix(string $code): string
    {
        if (! str_contains($code, '<?php')) {
            return "<?php\n".$code."\n";
        }
        $tokens = @token_get_all($code);
        $last = end($tokens);
        $inPhp = ! (is_array($last) && in_array($last[0], [T_INLINE_HTML, T_CLOSE_TAG], true));

        return $code."\n".($inPhp ? '' : "<?php\n");
    }

    private function php(string $name, int $count, string $marker): string
    {
        return <<<PHP

// ── harness: calls your function with each test's arguments ──
(static function (): void {
    \$lines = explode("\\n", (string) stream_get_contents(STDIN));
    \$args = [];
    for (\$i = 0; \$i < {$count}; \$i++) {
        \$args[] = json_decode(\$lines[\$i], true, 64, JSON_THROW_ON_ERROR);
    }
    if (function_exists('{$name}')) {
        \$fn = '{$name}';
    } elseif (class_exists('Solution') && method_exists('Solution', '{$name}')) {
        \$fn = [new \\Solution(), '{$name}'];
    } else {
        fwrite(STDERR, "Function `{$name}` is not defined. Did you rename it?\\n");
        exit(1);
    }
    \$result = \$fn(...\$args);
    // PHP arrays with gaps (after unset/array_filter) would encode as objects.
    \$listify = static function (\$v) use (&\$listify) {
        return is_array(\$v) ? array_map(\$listify, array_values(\$v)) : \$v;
    };
    echo "\\n{$marker}".json_encode(\$listify(\$result), JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\\n";
})();

PHP;
    }
}
