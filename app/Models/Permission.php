<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'code', 'description'])]

class Permission extends Model
{
    public function roles(): BelongsToMany
    {

        return $this->belongsToMany(Role::class)
            ->withTimestamps();

    }
}
