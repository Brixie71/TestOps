<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'description', 'scope'])]

class Role extends Model
{
    public function users(): HasMany
    {

        return $this->hasMany(User::class);

    }

    public function permissions(): BelongsToMany
    {

        return $this->belongsToMany(Permission::class)
            ->withTimestamps();

    }

    public function projectMembers(): BelongsToMany
    {
        // Why this relationship is useful: users() retrieves users assigned through the system-level users.role_id column.
        // projectMembers() retrieves users assigned through project_user. Keeping these separate prevents system roles and
        // project roles from getting mixed up.

        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot('project_id')
            ->withTimestamps();

    }
}
