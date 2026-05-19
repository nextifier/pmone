<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectPaymentGateway>
 */
class ProjectPaymentGatewayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'provider' => 'xendit',
            'label' => fake()->randomElement(['Production', 'Sandbox', null]),
            'mode' => fake()->randomElement(['live', 'test']),
            'is_active' => true,
            'secret_key' => 'xnd_'.fake()->regexify('[A-Za-z0-9]{40}'),
            'public_key' => null,
            'webhook_token' => fake()->regexify('[A-Za-z0-9]{32}'),
            'config' => ['currency' => 'IDR'],
        ];
    }

    public function default(): self
    {
        return $this->state(['is_active' => true]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
