<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Support\FunctionSignature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Problem extends Model
{
    use BelongsToUser;

    public const DIFFICULTIES = ['easy', 'medium', 'hard'];

    public const COMPARE_EXACT = 'exact';

    public const COMPARE_TRIM_LINES = 'trim_lines';

    public const COMPARE_TOKENS = 'tokens';

    public const COMPARE_FLOAT = 'float';

    /** Function mode only: the top-level returned list may be in any order. */
    public const COMPARE_UNORDERED = 'unordered';

    public const COMPARE_MODES = [self::COMPARE_EXACT, self::COMPARE_TRIM_LINES, self::COMPARE_TOKENS, self::COMPARE_FLOAT, self::COMPARE_UNORDERED];

    public const STDIN_COMPARE_MODES = [self::COMPARE_TRIM_LINES, self::COMPARE_EXACT, self::COMPARE_TOKENS, self::COMPARE_FLOAT];

    public const FUNCTION_COMPARE_MODES = [self::COMPARE_EXACT, self::COMPARE_UNORDERED];

    public const IO_STDIN = 'stdin';

    public const IO_FUNCTION = 'function';

    public const IO_MODES = [self::IO_STDIN, self::IO_FUNCTION];

    public const DEFAULT_TIME_LIMIT_MS = 4000;

    public const MIN_TIME_LIMIT_MS = 500;

    public const MAX_TIME_LIMIT_MS = 8000;

    protected $fillable = [
        'user_id', 'title', 'difficulty', 'description', 'io_mode', 'signature', 'tags', 'hints',
        'reference_solution', 'reference_language', 'starter_code',
        'time_limit_ms', 'compare_mode',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'hints' => 'array',
            'starter_code' => 'array',
            'signature' => 'array',
            'time_limit_ms' => 'integer',
        ];
    }

    public function testCases(): HasMany
    {
        return $this->hasMany(ProblemTestCase::class)->orderBy('sort_order')->orderBy('id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ProblemSubmission::class)->latest('id');
    }

    public function isSolved(): bool
    {
        return $this->submissions()->where('status', ProblemSubmission::STATUS_ACCEPTED)->exists();
    }

    public function descriptionHtml(): string
    {
        return VideoNote::markdownToHtml($this->description);
    }

    /** @return list<string> */
    public function tagList(): array
    {
        return array_values($this->tags ?? []);
    }

    /** @return list<string> */
    public function hintList(): array
    {
        return array_values(array_filter($this->hints ?? [], fn ($h) => is_string($h) && trim($h) !== ''));
    }

    public function timeLimitMs(): int
    {
        return $this->time_limit_ms ?: self::DEFAULT_TIME_LIMIT_MS;
    }

    public function compareMode(): string
    {
        $allowed = $this->isFunction() ? self::FUNCTION_COMPARE_MODES : self::STDIN_COMPARE_MODES;

        return in_array($this->compare_mode, $allowed, true) ? $this->compare_mode : $allowed[0];
    }

    /** LeetCode-style: the solver writes one function described by {@see functionSignature()}. */
    public function isFunction(): bool
    {
        return $this->io_mode === self::IO_FUNCTION && $this->functionSignature() !== null;
    }

    public function functionSignature(): ?FunctionSignature
    {
        return $this->io_mode === self::IO_FUNCTION ? FunctionSignature::fromArray($this->signature) : null;
    }
}
