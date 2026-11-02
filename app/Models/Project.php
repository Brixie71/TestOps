<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
}
