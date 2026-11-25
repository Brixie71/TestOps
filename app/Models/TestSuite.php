<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of related test cases inside one project.
 *
 * Data foundation only: no status, workflow, or UI requirements are approved
 * yet, so this model carries just the owning project, a name, and an optional
 * description.
 */
#[Fillable([
    'project_id',
    'name',
    'description',
])]
class TestSuite extends Model
{
    use HasFactory;

    /**
     * Get the project that owns the suite.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the test cases grouped by the suite.
     */
    public function testCases(): HasMany
    {
        return $this->hasMany(TestCase::class);
    }
}
