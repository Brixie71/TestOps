<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // The owner_id field is the important part: using User::factory()
            // tells Laravel to create an owner automatically whenever a project is created through this factory.

            'owner_id' => User::factory(),
            // Owned projects default to a permanent assignment; automatic
            // assignment uses permanent ownership as well.
            'owner_assignment_type' => 'permanent',
            'name' => fake()->company(),
            'code' => fake()->unique()->bothify('PRJ-####'),
            'description' => fake()->optional()->sentence(),
            'status' => 'active',
            'auto_assign_representative_owner' => false,
        ];
    }

    /**
     * Create a model instance bypassing mass-assignment protection.
     *
     * owner_id, owner_assignment_type, and auto_assign_representative_owner
     * are not mass-assignable, so attributes like states (withoutOwner,
     * withTemporaryOwner, withAutoAssignment) must be assigned explicitly
     * through forceFill instead of the allowlist.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): Project
    {
        return parent::newModel()->forceFill($attributes);
    }

    /**
     * Create an unassigned project with no owner.
     */
    public function withoutOwner(): static
    {
        return $this->state(fn () => [
            'owner_id' => null,
            'owner_assignment_type' => null,
        ]);
    }

    /**
     * Represent temporary ownership: the project keeps its owner but the
     * assignment is marked temporary (no automatic expiration).
     */
    public function withTemporaryOwner(): static
    {
        return $this->state(fn () => [
            'owner_assignment_type' => 'temporary',
        ]);
    }

    /**
     * Enable automatic Representative Admin ownership assignment.
     *
     * Automatic assignment always resolves to permanent ownership; this
     * state never implies a temporary assignment and does not unassign
     * the project's existing owner.
     */
    public function withAutoAssignment(): static
    {
        return $this->state(fn () => [
            'auto_assign_representative_owner' => true,
        ]);
    }
}
