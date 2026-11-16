<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProblemSubmission extends Model
{
    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_WRONG_ANSWER = 'wrong_answer';

    public const STATUS_RUNTIME_ERROR = 'runtime_error';

    public const STATUS_TIME_LIMIT = 'time_limit';

    /** Go only: the build failed, so no test ran. */
    public const STATUS_COMPILE_ERROR = 'compile_error';

    protected $fillable = [
        'problem_id', 'user_id', 'language', 'code', 'status',
        'passed_count', 'total_count', 'duration_ms', 'results', 'problem_revision',
    ];

    protected function casts(): array
    {
        return [
            'results' => 'array',
            'problem_revision' => 'integer',
            'hidden_at' => 'datetime',
            'moderated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function problem(): BelongsTo
    {
        return $this->belongsTo(Problem::class);
    }
}
