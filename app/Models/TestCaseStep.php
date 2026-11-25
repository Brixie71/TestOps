<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ordered step of a test case: the action to perform and its expected
 * result.
 */
#[Fillable([
    'position',
    'action',
    'expected_result',
])]
class TestCaseStep extends Model
{
    use HasFactory;

    /**
     * Get the case that owns the step.
     */
    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TestCase::class);
    }
}
