<?php

namespace Tests\Feature\Models;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_can_be_created_without_an_updated_at_column(): void
    {
        $auditLog = AuditLog::query()->create([
            'actor_user_id' => 1,
            'actor_name' => 'System Admin',
            'actor_email' => 'admin@example.test',
            'target_user_id' => 2,
            'target_name' => 'Test User',
            'target_email' => 'user@example.test',
            'action' => 'user.invited',
            'reason' => 'Account onboarding',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLog->id,
            'action' => 'user.invited',
        ]);

        $this->assertNotNull($auditLog->created_at);
        $this->assertArrayNotHasKey('updated_at', $auditLog->getAttributes());
    }

    public function test_audit_log_json_snapshots_are_cast_to_arrays(): void
    {
        $auditLog = AuditLog::query()->create([
            'action' => 'user.role_changed',
            'before_values' => [
                'role' => 'User',
            ],
            'after_values' => [
                'role' => 'System Admin',
            ],
        ]);

        $storedAuditLog = AuditLog::query()->findOrFail($auditLog->id);

        $this->assertSame(['role' => 'User'], $storedAuditLog->before_values);
        $this->assertSame(['role' => 'System Admin'], $storedAuditLog->after_values);
    }

    public function test_existing_audit_log_cannot_be_updated(): void
    {
        $auditLog = AuditLog::query()->create([
            'action' => 'user.invited',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Audit log records cannot be updated.');

        $auditLog->action = 'user.deleted';
        $auditLog->save();
    }

    public function test_existing_audit_log_cannot_be_deleted(): void
    {
        $auditLog = AuditLog::query()->create([
            'action' => 'user.invited',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Audit log records cannot be deleted.');

        $auditLog->delete();
    }
}
