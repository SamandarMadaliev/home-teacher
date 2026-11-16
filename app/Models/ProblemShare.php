<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read access to someone else's problem. Only the problem's owner creates
 * these (by email). `include_solution` lets the recipient see the owner's
 * solutions before solving the problem themselves.
 */
class ProblemShare extends Model
{
    protected $fillable = ['problem_id', 'user_id', 'include_solution'];

    protected function casts(): array
    {
        return [
            'include_solution' => 'boolean',
            'seen_at' => 'datetime',
        ];
    }

    public function problem(): BelongsTo
    {
        return $this->belongsTo(Problem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
