<?php

namespace Tests\Feature\Profile;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_update_their_own_profile_details(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'contact_number' => '09170000000',
            'job_title' => 'Analyst',
            'department' => 'Quality',
        ]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'New Name',
                'contact_number' => '09991234567',
                'job_title' => 'Senior Analyst',
                'department' => 'Engineering',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('New Name', $user->name);
        $this->assertSame('09991234567', $user->contact_number);
        $this->assertSame('Senior Analyst', $user->job_title);
        $this->assertSame('Engineering', $user->department);

        $audit = AuditLog::query()
            ->where('action', 'user.profile_updated')
            ->where('target_user_id', $user->getKey())
            ->sole();

        $this->assertSame($user->getKey(), $audit->actor_user_id);
        $this->assertSame('Old Name', $audit->before_values['name']);
        $this->assertSame('New Name', $audit->after_values['name']);
        $this->assertSame('Analyst', $audit->before_values['job_title']);
        $this->assertSame('Senior Analyst', $audit->after_values['job_title']);
    }

    public function test_values_are_trimmed_and_blank_optional_fields_become_null(): void
    {
        $user = User::factory()->create([
            'contact_number' => '09170000000',
            'job_title' => 'Analyst',
            'department' => 'Quality',
        ]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => '  Trimmed Name  ',
                'contact_number' => '   ',
                'job_title' => '',
                'department' => '  Engineering  ',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('Trimmed Name', $user->name);
        $this->assertNull($user->contact_number);
        $this->assertNull($user->job_title);
        $this->assertSame('Engineering', $user->department);
    }

    public function test_the_name_may_be_cleared(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => ''])
            ->assertRedirect(route('profile'))
            ->assertSessionHasNoErrors();

        $this->assertNull($user->refresh()->name);
    }

    public function test_identity_and_security_fields_are_ignored(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'job_title' => 'Analyst',
        ]);

        $originalEmail = $user->email;

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Original Name',
                'job_title' => 'Analyst',
                'email' => 'attacker@example.com',
                'account_status' => 'invited',
                'password' => 'Injected#Password1',
                'role_id' => 999,
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame($originalEmail, $user->email);
        $this->assertSame('active', $user->account_status);
        $this->assertNull($user->role_id);
        $this->assertSame('Original Name', $user->name);
    }

    public function test_profile_field_lengths_are_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile'))
            ->patch(route('profile.update'), [
                'name' => str_repeat('a', 256),
                'contact_number' => str_repeat('1', 31),
                'job_title' => str_repeat('b', 101),
                'department' => str_repeat('c', 101),
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHasErrors(['name', 'contact_number', 'job_title', 'department']);

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_an_unchanged_submission_writes_no_audit_event(): void
    {
        $user = User::factory()->create([
            'name' => 'Same Name',
            'contact_number' => '09170000000',
            'job_title' => 'Analyst',
            'department' => 'Quality',
        ]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Same Name',
                'contact_number' => '09170000000',
                'job_title' => 'Analyst',
                'department' => 'Quality',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_the_profile_page_renders_the_editable_details_form(): void
    {
        $user = User::factory()->create([
            'name' => 'Profile Owner',
            'contact_number' => '09170000000',
            'job_title' => 'Analyst',
            'department' => 'Quality',
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee(route('profile.update'), false)
            ->assertSee('Profile Owner')
            ->assertSee('09170000000')
            ->assertSee('Analyst')
            ->assertSee('Quality');
    }

    public function test_guests_cannot_update_a_profile(): void
    {
        $this->patch(route('profile.update'), ['name' => 'Nobody'])
            ->assertRedirect(route('login'));
    }
}
