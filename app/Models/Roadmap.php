<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Roadmap extends Model
{
    protected $fillable = [
        'title',
        'description',
    ];

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'roadmap_course')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }
}
