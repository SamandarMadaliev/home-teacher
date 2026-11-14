<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProblemTestCase extends Model
{
    protected $fillable = ['problem_id', 'label', 'input', 'expected_output', 'is_hidden', 'sort_order'];

    protected function casts(): array
    {
        return ['is_hidden' => 'boolean'];
    }

    public function problem(): BelongsTo
    {
        return $this->belongsTo(Problem::class);
    }
}
