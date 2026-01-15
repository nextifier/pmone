<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'description' => fake()->optional(0.7)->paragraphs(2, true),
            'status' => fake()->randomElement(Task::allowedStatuses()),
            'priority' => fake()->optional(0.6)->randomElement(Task::allowedPriorities()),
            'complexity' => fake()->optional(0.5)->randomElement(Task::allowedComplexities()),
            'visibility' => fake()->randomElement([Task::VISIBILITY_PUBLIC, Task::VISIBILITY_PRIVATE]),
            'assignee_id' => null,
            'project_id' => null,
            'estimated_start_at' => fake()->optional(0.4)->dateTimeBetween('now', '+7 days'),
            'estimated_completion_at' => fake()->optional(0.5)->dateTimeBetween('+1 day', '+30 days'),
            'completed_at' => null,
            'order_column' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate that the task is todo.
     */
    public function todo(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Task::STATUS_TODO,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the task is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Task::STATUS_IN_PROGRESS,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the task is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => now()->subDays(rand(1, 7)),
        ]);
    }

    /**
     * Indicate that the task is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Task::STATUS_ARCHIVED,
        ]);
    }

    /**
     * Indicate that the task has high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => Task::PRIORITY_HIGH,
        ]);
    }

    /**
     * Indicate that the task has medium priority.
     */
    public function mediumPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => Task::PRIORITY_MEDIUM,
        ]);
    }

    /**
     * Indicate that the task has low priority.
     */
    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => Task::PRIORITY_LOW,
        ]);
    }

    /**
     * Indicate that the task is assigned to a user.
     */
    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'assignee_id' => $user->id,
        ]);
    }

    /**
     * Indicate that the task belongs to a project.
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
        ]);
    }

    /**
     * Indicate that the task is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => Task::VISIBILITY_PUBLIC,
        ]);
    }

    /**
     * Indicate that the task is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => Task::VISIBILITY_PRIVATE,
        ]);
    }

    /**
     * Indicate that the task is shared.
     */
    public function shared(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => Task::VISIBILITY_SHARED,
        ]);
    }

    /**
     * Indicate that the task is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'estimated_completion_at' => now()->subDays(rand(1, 7)),
            'status' => Task::STATUS_TODO,
            'completed_at' => null,
        ]);
    }
}
