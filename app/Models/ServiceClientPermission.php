<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Passport\Client;

class ServiceClientPermission extends Model
{
    protected $fillable = [
        'oauth_client_id',
        'permission',
    ];

    public function oauthClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'oauth_client_id');
    }

    /**
     * Find whether a service client holds the given application permission.
     */
    public static function granted(mixed $oauthClientId, string $permission): bool
    {
        return static::query()
            ->where('oauth_client_id', $oauthClientId)
            ->where('permission', $permission)
            ->exists();
    }

    /**
     * Store service permissions on the same connection as Passport so the
     * foreign key to `oauth_clients` stays valid.
     */
    public function getConnectionName(): ?string
    {
        return parent::getConnectionName() ?? config('passport.connection');
    }
}
