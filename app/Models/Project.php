<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Ownership (owner_id) and the automatic-assignment setting are
// deliberately not mass-assignable; they are controlled through the
// factory's forceFill and future authorized ownership workflows.
#[Fillable([
    'name',
    'code',
    'description',
    'status',
])]
class Project extends Model
{
    use HasFactory;

    // Defines the many-to-one relationship
    public function owner(): BelongsTo
    {

        return $this->belongsTo(User::class, 'owner_id');

        // A few things to understand:
        // - owner() is the relationship method.
        // - BelongsTo is the return type.
        // - User::class identifies the related model.
        // - 'owner_id' tells Laravel which foreign key to use.

    }

    // Defines the many-to-many relationship
    public function members(): BelongsToMany
    {

        return $this->belongsToMany(User::class)
            ->withPivot('role_id') // Includes the membership's assigned role
            ->withTimestamps(); // Reads and maintains pivot timestamps

    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'auto_assign_representative_owner' => 'boolean',
        ];
    }
}
