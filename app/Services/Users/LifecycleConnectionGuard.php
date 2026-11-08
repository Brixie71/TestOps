<?php

namespace App\Services\Users;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fail-closed guard for user lifecycle transactions.
 *
 * Lifecycle changes (soft delete, OAuth credential revocation, database
 * session purge, audit records) are only transactionally atomic when they all
 * use the same database connection. The guard resolves the effective
 * Laravel database connections — including the null/fallback behaviour of
 * the Passport and session configuration — and refuses to continue when they
 * do not resolve to one and the same connection.
 */
class LifecycleConnectionGuard
{
    /**
     * Assert that the lifecycle, session, and Passport connections resolve to
     * the same configured database connection.
     *
     * Resolving connection names never opens a database connection, so the
     * check is deterministic and requires no external database.
     *
     * @throws RuntimeException when the resolved connections differ
     */
    public function assertConsistentConnections(): void
    {
        $names = [
            DB::connection()->getName(),
            DB::connection(config('session.connection'))->getName(),
            DB::connection(config('passport.connection'))->getName(),
        ];

        if (count(array_unique($names)) > 1) {
            throw new RuntimeException(sprintf(
                'User lifecycle changes are transactionally atomic only when the lifecycle connection (%s), the session connection (%s), and the Passport connection (%s) resolve to the same configured database connection. Fix the database, SESSION_CONNECTION, or PASSPORT_CONNECTION configuration before performing user lifecycle changes.',
                $names[0],
                $names[1],
                $names[2],
            ));
        }
    }
}
