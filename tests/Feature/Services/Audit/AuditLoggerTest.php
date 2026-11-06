<?php

namespace Tests\Feature\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_actor_and_target_identity_snapshots(): void
    {
        $actor = User::factory()->create([
            'name' => 'System Administrator',
            'email' => 'admin@example.com',
        ]);

        $target = User::factory()->create([
            'name' => 'Invited User',
            'email' => 'invited@example.com',
        ]);

        $auditLog = app(AuditLogger::class)->log(
            $actor,
            $target,
            'user.invited',
            ['account_status' => 'invited'],
            ['account_status' => 'active'],
            'Account invitation accepted.',
        );

        $this->assertInstanceOf(AuditLog::class, $auditLog);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $actor->id,
            'actor_name' => 'System Administrator',
            'actor_email' => 'admin@example.com',
            'target_user_id' => $target->id,
            'target_name' => 'Invited User',
            'target_email' => 'invited@example.com',
            'action' => 'user.invited',
            'reason' => 'Account invitation accepted.',
        ]);

        $this->assertSame(
            ['account_status' => 'invited'],
            $auditLog->before_values,
        );

        $this->assertSame(
            ['account_status' => 'active'],
            $auditLog->after_values,
        );
    }

    public function test_it_allows_nullable_actor_and_target_identities(): void
    {
        $auditLog = app(AuditLogger::class)->log(
            null,
            null,
            'system.maintenance',
        );

        $this->assertNull($auditLog->actor_user_id);
        $this->assertNull($auditLog->actor_name);
        $this->assertNull($auditLog->actor_email);

        $this->assertNull($auditLog->target_user_id);
        $this->assertNull($auditLog->target_name);
        $this->assertNull($auditLog->target_email);

        $this->assertSame('system.maintenance', $auditLog->action);
    }

    public function test_it_recursively_removes_sensitive_snapshot_fields(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $auditLog = app(AuditLogger::class)->log(
            $actor,
            $target,
            'user.updated',
            [
                'account_status' => 'invited',
                'password' => 'must-not-be-stored',
                'profile' => [
                    'department' => 'Engineering',
                    'token' => 'must-not-be-stored',
                    'security' => [
                        'client_secret' => 'must-not-be-stored',
                        'authorization' => 'must-not-be-stored',
                    ],
                ],
            ],
            [
                'account_status' => 'active',
                'role' => 'SYSTEM_ADMIN',
                'credentials' => [
                    'secret' => 'must-not-be-stored',
                    'refresh_token' => 'must-not-be-stored',
                ],
                'remember_token' => 'must-not-be-stored',
            ],
        );

        $this->assertSame([
            'account_status' => 'invited',
            'profile' => [
                'department' => 'Engineering',
                'security' => [],
            ],
        ], $auditLog->before_values);

        $this->assertSame([
            'account_status' => 'active',
            'role' => 'SYSTEM_ADMIN',
        ], $auditLog->after_values);

        $this->assertStringNotContainsString(
            'must-not-be-stored',
            json_encode([
                $auditLog->before_values,
                $auditLog->after_values,
            ], JSON_THROW_ON_ERROR),
        );
    }

    public function test_sensitive_snapshot_keys_are_case_insensitive(): void
    {
        $auditLog = app(AuditLogger::class)->log(
            null,
            null,
            'user.updated',
            [
                'Password' => 'must-not-be-stored',
                'Email' => 'user@example.com',
            ],
        );

        $this->assertSame([
            'Email' => 'user@example.com',
        ], $auditLog->before_values);
    }
}
