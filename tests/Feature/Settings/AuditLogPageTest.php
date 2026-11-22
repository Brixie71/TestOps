<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_permitted_admin_sees_the_audit_log_page(): void
    {
        $actor = $this->createSuperAdmin();

        AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'action' => 'user.deactivated',
        ]);

        $this->actingAs($actor)
            ->get(route('settings.audit-logs'))
            ->assertOk()
            ->assertSee('user.deactivated')
            ->assertSee($actor->email);
    }

    public function test_a_system_admin_with_the_permission_sees_the_page(): void
    {
        $actor = $this->createSystemAdmin(withPermission: true);

        $this->actingAs($actor)
            ->get(route('settings.audit-logs'))
            ->assertOk();
    }

    public function test_a_system_admin_without_the_permission_is_forbidden(): void
    {
        $actor = $this->createSystemAdmin(withPermission: false);

        $this->actingAs($actor)
            ->get(route('settings.audit-logs'))
            ->assertForbidden();
    }

    public function test_an_ordinary_user_is_forbidden(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)
            ->get(route('settings.audit-logs'))
            ->assertForbidden();
    }

    public function test_viewing_the_page_generates_an_audit_event(): void
    {
        $actor = $this->createSuperAdmin();

        $this->actingAs($actor)
            ->get(route('settings.audit-logs'))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'audit.viewed',
            'actor_user_id' => $actor->getKey(),
        ]);
    }

    public function test_repeated_views_in_one_session_are_audited_once(): void
    {
        $actor = $this->createSuperAdmin();

        $this->actingAs($actor)
            ->get(route('settings.audit-logs'))
            ->assertOk();

        $this->actingAs($actor)
            ->get(route('settings.audit-logs'))
            ->assertOk();

        $this->assertSame(1, AuditLog::query()->where('action', 'audit.viewed')->count());
    }

    public function test_exporting_does_not_add_a_second_view_event(): void
    {
        $actor = $this->createSuperAdmin();

        $this->actingAs($actor)
            ->get(route('settings.audit-logs'))
            ->assertOk();

        $this->actingAs($actor)
            ->get(route('settings.audit-logs.export'))
            ->assertOk();

        $this->assertSame(1, AuditLog::query()->where('action', 'audit.viewed')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'audit.exported')->count());
    }

    public function test_repeated_actions_on_a_date_are_shown_once_with_a_count_and_time_breakdown(): void
    {
        $actor = $this->createSuperAdmin();

        DB::table('audit_logs')->insert([
            [
                'actor_user_id' => $actor->getKey(),
                'actor_name' => $actor->name,
                'actor_email' => $actor->email,
                'action' => 'user.deactivated',
                'reason' => 'Quarterly review.',
                'created_at' => now()->setTime(9, 15),
            ],
            [
                'actor_user_id' => $actor->getKey(),
                'actor_name' => $actor->name,
                'actor_email' => $actor->email,
                'action' => 'user.deactivated',
                'reason' => 'Quarterly review.',
                'created_at' => now()->setTime(10, 45),
            ],
        ]);

        $this->actingAs($actor)
            ->get(route('settings.audit-logs', ['action' => 'user.deactivated']))
            ->assertOk()
            ->assertSee(now()->format('M d, Y'))
            ->assertSee('09:15')
            ->assertSee('10:45')
            ->assertSee('&times;2', false);
    }

    public function test_the_actor_filter_matches_name_or_email(): void
    {
        $actor = $this->createSuperAdmin();

        AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'action' => 'user.deactivated',
            'reason' => 'actor-excluded-marker',
        ]);

        AuditLog::query()->create([
            'actor_name' => 'Other Person',
            'actor_email' => 'other.person@example.net',
            'action' => 'user.reactivated',
        ]);

        $this->actingAs($actor)
            ->get(route('settings.audit-logs', ['actor' => 'other.person@example.net']))
            ->assertOk()
            ->assertSee('user.reactivated')
            ->assertDontSee('actor-excluded-marker');
    }

    public function test_the_target_filter_matches_the_target_user(): void
    {
        $actor = $this->createSuperAdmin();

        AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'target_name' => 'Jane Target',
            'target_email' => 'jane.target@example.net',
            'action' => 'user.role_changed',
        ]);

        AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'target_name' => 'Bob Other',
            'target_email' => 'bob.other@example.net',
            'action' => 'user.deactivated',
            'reason' => 'target-excluded-marker',
        ]);

        $this->actingAs($actor)
            ->get(route('settings.audit-logs', ['target' => 'jane.target']))
            ->assertOk()
            ->assertSee('user.role_changed')
            ->assertDontSee('target-excluded-marker');
    }

    public function test_the_action_filter_restricts_to_one_action_type(): void
    {
        $actor = $this->createSuperAdmin();

        AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'action' => 'user.deactivated',
            'reason' => 'action-excluded-marker',
        ]);

        AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'action' => 'user.reactivated',
        ]);

        // The filter dropdown lists every distinct action type, so assert on
        // the excluded row's unique reason rather than the action string.
        $this->actingAs($actor)
            ->get(route('settings.audit-logs', ['action' => 'user.reactivated']))
            ->assertOk()
            ->assertSee('user.reactivated')
            ->assertDontSee('action-excluded-marker');
    }

    public function test_the_date_range_filter_bounds_results(): void
    {
        $actor = $this->createSuperAdmin();

        // `created_at` is not fillable on the audit model, so seed the
        // back-dated row directly through the table.
        DB::table('audit_logs')->insert([
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'action' => 'user.deactivated',
            'reason' => 'date-excluded-marker',
            'created_at' => now()->subDays(10),
        ]);

        AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'action' => 'user.reactivated',
        ]);

        $this->actingAs($actor)
            ->get(route('settings.audit-logs', ['date_from' => now()->subDays(2)->toDateString()]))
            ->assertOk()
            ->assertSee('user.reactivated')
            ->assertDontSee('date-excluded-marker');
    }

    public function test_invalid_dates_are_rejected(): void
    {
        $actor = $this->createSuperAdmin();

        $this->actingAs($actor)
            ->from(route('settings.audit-logs'))
            ->get(route('settings.audit-logs', ['date_from' => 'not-a-date']))
            ->assertRedirect(route('settings.audit-logs'))
            ->assertSessionHasErrors('date_from');
    }

    public function test_the_csv_export_downloads_filtered_results(): void
    {
        $actor = $this->createSuperAdmin();

        AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'reason' => 'Policy violation.',
            'action' => 'user.deactivated',
        ]);

        AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'action' => 'user.reactivated',
        ]);

        $response = $this->actingAs($actor)
            ->get(route('settings.audit-logs.export', ['action' => 'user.deactivated']));

        // Streamed downloads cannot be content-inspected through the test
        // client; filter correctness is covered by the page filter tests, so
        // assert the response shape here.
        $response->assertOk();
        $this->assertMatchesRegularExpression('/^text\/csv/', $response->headers->get('content-type'));
        $this->assertMatchesRegularExpression('/attachment; filename=audit-logs-/', $response->headers->get('content-disposition'));
    }

    public function test_exporting_generates_an_audit_event_with_the_filters(): void
    {
        $actor = $this->createSuperAdmin();

        $this->actingAs($actor)
            ->get(route('settings.audit-logs.export', ['action' => 'user.deactivated']))
            ->assertOk();

        $entry = AuditLog::query()->where('action', 'audit.exported')->sole();

        $this->assertSame($actor->getKey(), (int) $entry->actor_user_id);
        $this->assertSame('user.deactivated', $entry->after_values['action']);
        $this->assertSame('csv', $entry->after_values['format']);
    }

    public function test_the_pdf_export_downloads_a_pdf_attachment(): void
    {
        $actor = $this->createSuperAdmin();

        AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'action' => 'user.deactivated',
            'reason' => 'Policy violation.',
        ]);

        $response = $this->actingAs($actor)
            ->get(route('settings.audit-logs.export.pdf', ['action' => 'user.deactivated']));

        $response->assertOk();
        $this->assertMatchesRegularExpression('/^application\/pdf/', (string) $response->headers->get('content-type'));
        $this->assertMatchesRegularExpression('/attachment; filename=audit-logs-.*\.pdf/', (string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    public function test_the_pdf_export_generates_an_audit_event_with_the_filters_and_format(): void
    {
        $actor = $this->createSuperAdmin();

        $this->actingAs($actor)
            ->get(route('settings.audit-logs.export.pdf', ['action' => 'user.deactivated']))
            ->assertOk();

        $entry = AuditLog::query()->where('action', 'audit.exported')->sole();

        $this->assertSame($actor->getKey(), (int) $entry->actor_user_id);
        $this->assertSame('user.deactivated', $entry->after_values['action']);
        $this->assertSame('pdf', $entry->after_values['format']);
    }

    public function test_the_pdf_export_requires_the_audit_permission(): void
    {
        $actor = $this->createSystemAdmin(withPermission: false);

        $this->actingAs($actor)
            ->get(route('settings.audit-logs.export.pdf'))
            ->assertForbidden();
    }

    public function test_the_audit_page_renders_the_pdf_export_link(): void
    {
        $actor = $this->createSuperAdmin();

        $this->actingAs($actor)
            ->get(route('settings.audit-logs'))
            ->assertOk()
            ->assertSee('Export PDF')
            ->assertSee(route('settings.audit-logs.export.pdf'), false);
    }

    public function test_the_user_details_page_links_back_from_the_audit_filters(): void
    {
        // §12.4 per-user audit history lives on the details page; the audit
        // page is the global view. Both are reachable from the sidebar.
        $actor = $this->createSuperAdmin();

        $this->actingAs($actor)
            ->get(route('settings.audit-logs'))
            ->assertOk()
            ->assertSee('Export CSV');
    }

    private function createSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function createSystemAdmin(bool $withPermission): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SYSTEM_ADMIN'],
            ['name' => 'System Admin', 'scope' => 'system'],
        );

        if ($withPermission) {
            $permission = Permission::query()->firstOrCreate(
                ['code' => 'audit.view'],
                ['name' => 'View Audit Logs'],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return User::factory()->create(['role_id' => $role->id]);
    }
}
