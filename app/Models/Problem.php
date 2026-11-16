<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Support\FunctionSignature;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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

    public const VISIBILITY_PRIVATE = 'private';

    /** A "community" problem: every member can open and solve it. */
    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITIES = [self::VISIBILITY_PRIVATE, self::VISIBILITY_PUBLIC];

    /** A private problem can be shared with at most this many people; more makes it public. */
    public const MAX_SHARES = 4;

    protected $fillable = [
        'user_id', 'title', 'difficulty', 'description', 'io_mode', 'signature', 'tags', 'hints',
        'reference_solution', 'reference_language', 'starter_code',
        'time_limit_ms', 'compare_mode', 'show_reference_solution',
    ];
    // visibility / published_at / revision / moderated_* are set with forceFill only.

    /** Mirrors the column defaults so a freshly created model has them too. */
    protected $attributes = [
        'visibility' => self::VISIBILITY_PRIVATE,
        'show_reference_solution' => false,
        'revision' => 1,
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'hints' => 'array',
            'starter_code' => 'array',
            'signature' => 'array',
            'time_limit_ms' => 'integer',
            'show_reference_solution' => 'boolean',
            'revision' => 'integer',
            'published_at' => 'datetime',
            'moderated_at' => 'datetime',
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

    public function shares(): HasMany
    {
        return $this->hasMany(ProblemShare::class)->orderBy('id');
    }

    /** Submissions made by one person (each user only ever sees their own). */
    public function submissionsBy(User|int|null $user): HasMany
    {
        return $this->submissions()->where('user_id', $user instanceof User ? $user->id : $user);
    }

    public function isSolvedBy(User|int|null $user): bool
    {
        return $user !== null
            && $this->submissionsBy($user)->where('status', ProblemSubmission::STATUS_ACCEPTED)->exists();
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->user_id !== null && $this->user_id === $user->id;
    }

    public function isPublic(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }

    public function isModerated(): bool
    {
        return $this->moderated_at !== null;
    }

    public function shareFor(?User $user): ?ProblemShare
    {
        return $user === null ? null : $this->shares()->where('user_id', $user->id)->first();
    }

    /**
     * Problems this user may open: their own, ones shared with them, and
     * public ones that an admin hasn't hidden.
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('problems.user_id', $user->id)
            ->orWhereExists(fn ($s) => $s->from('problem_shares')
                ->whereColumn('problem_shares.problem_id', 'problems.id')
                ->where('problem_shares.user_id', $user->id))
            ->orWhere(fn (Builder $p) => $p->where('visibility', self::VISIBILITY_PUBLIC)->whereNull('moderated_at')));
    }

    /** Shared with this user by someone else. */
    public function scopeSharedWith(Builder $query, User $user): Builder
    {
        return $query->whereExists(fn ($s) => $s->from('problem_shares')
            ->whereColumn('problem_shares.problem_id', 'problems.id')
            ->where('problem_shares.user_id', $user->id));
    }

    /** "My list": own problems plus the ones shared with me (not all of the community). */
    public function scopeInLibraryOf(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('problems.user_id', $user->id)->orWhere(fn ($s) => $s->sharedWith($user)));
    }

    /** Public problems by other people, not hidden by an admin. */
    public function scopeCommunity(Builder $query): Builder
    {
        return $query->where('visibility', self::VISIBILITY_PUBLIC)->whereNull('moderated_at');
    }

    /**
     * Community solutions: each person's latest accepted submission per
     * language that they haven't hidden and no admin has hidden.
     */
    public function solutionsQuery(bool $includeModerated = false)
    {
        $latest = ProblemSubmission::query()
            ->selectRaw('max(id)')
            ->where('problem_id', $this->id)
            ->where('status', ProblemSubmission::STATUS_ACCEPTED)
            ->whereNull('hidden_at')
            ->when(! $includeModerated, fn ($q) => $q->whereNull('moderated_at'))
            ->groupBy('user_id', 'language');

        return ProblemSubmission::query()->whereIn('id', $latest);
    }

    /**
     * Everything that decides whether a solution is right. When this changes,
     * {@see $revision} goes up and older submissions are marked as such.
     */
    public function judgingFingerprint(): string
    {
        $tests = DB::table('problem_test_cases')->where('problem_id', $this->id)
            ->orderBy('sort_order')->orderBy('id')
            ->get(['input', 'expected_output', 'is_hidden'])
            ->map(fn ($t) => [(string) $t->input, (string) $t->expected_output, (bool) $t->is_hidden])
            ->all();

        return hash('sha256', json_encode([
            $this->io_mode, $this->signature, $this->compareMode(), $this->timeLimitMs(), $tests,
        ]));
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

    /** LeetCode-style: the solver writes one function described by {@see FunctionSignature()}. */
    public function isFunction(): bool
    {
        return $this->io_mode === self::IO_FUNCTION && $this->functionSignature() !== null;
    }

    public function functionSignature(): ?FunctionSignature
    {
        return $this->io_mode === self::IO_FUNCTION ? FunctionSignature::fromArray($this->signature) : null;
    }
}
