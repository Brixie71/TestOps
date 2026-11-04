<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])] // prevents sensitive data from being exposed in serialized output.

// #[Hidden(['password', 'remember_token'])] does not encrypt passwords.
// It prevents those attributes from appearing when the User model is converted into an array or JSON.
// Hides sensitive attributes from serialized output
//
// Example :
// Without hidden attributes, serialized user data might look like:
//
// JSON
// {
//    "name": "Brix",
//    "email": "brix@example.com",
//    "password": "$2y$12$...",
//    "remember_token": "abc123"
// }
//
// With #[Hidden], those sensitive fields are excluded:
// {
//    "name": "Brix",
//    "email": "brix@example.com",
// }
//

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed', // Automatically hashes assigned passwords. protects the stored password.
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->withPivot('role_id')
            ->withTimestamps();
    }
}
