<?php

namespace Tests\Feature\Services\Users;

use App\Services\Users\LifecycleConnectionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class LifecycleConnectionGuardTest extends TestCase
{
    use RefreshDatabase;

    private LifecycleConnectionGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guard = new LifecycleConnectionGuard;
    }

    public function test_passes_when_all_configured_connections_resolve_to_one_connection(): void
    {
        // Resolution must not open or require an external database.
        $this->expectNotToPerformAssertions();

        try {
            $this->guard->assertConsistentConnections();
        } catch (RuntimeException $caught) {
            $this->fail(sprintf(
                'The default connection configuration must pass: %s',
                $caught->getMessage(),
            ));
        }
    }

    public function test_fails_closed_when_the_session_connection_differs(): void
    {
        config([
            'session.connection' => 'lifecycle_alt',
            'database.connections.lifecycle_alt' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ]);

        $this->expectConnectionMismatch();
    }

    public function test_fails_closed_when_the_passport_connection_differs(): void
    {
        config([
            'passport.connection' => 'lifecycle_alt',
            'database.connections.lifecycle_alt' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ]);

        $this->expectConnectionMismatch();
    }

    public function test_fails_closed_when_a_configured_connection_does_not_exist(): void
    {
        config(['passport.connection' => 'missing_connection']);

        $this->expectException(InvalidArgumentException::class);

        $this->guard->assertConsistentConnections();
    }

    private function expectConnectionMismatch(): void
    {
        try {
            $this->guard->assertConsistentConnections();
            $this->fail('The inconsistent connection configuration was not rejected.');
        } catch (RuntimeException $caught) {
            $this->assertStringContainsString('resolve to the same configured database connection', $caught->getMessage());
        }
    }
}
