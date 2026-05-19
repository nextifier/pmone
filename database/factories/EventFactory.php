<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $editionNumber = fake()->numberBetween(1, 30);
        $startDate = fake()->dateTimeBetween('+1 month', '+1 year');
        $startDate->setTime(10, 0);
        $endDate = (clone $startDate)->modify('+'.fake()->numberBetween(1, 5).' days');
        $endDate->setTime(18, 0);

        return [
            'project_id' => Project::factory(),
            'title' => fake()->company().' Expo',
            'edition_number' => $editionNumber,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'location' => fake()->optional(0.8)->address(),
            'location_link' => fake()->optional(0.5)->url(),
            'hall' => fake()->optional(0.5)->randomElement(['Hall A', 'Hall B', 'Hall A1-A2', 'Hall C']),
            'status' => 'draft',
            'visibility' => 'private',
            'hotel_reservation_enabled' => true,
        ];
    }

    /**
     * Factory state for events with hotel reservation feature explicitly disabled.
     */
    public function withoutHotelReservation(): static
    {
        return $this->state(fn () => ['hotel_reservation_enabled' => false]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'visibility' => 'public',
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
        ]);
    }

    public function withOrderDeadline(?string $deadline = null): static
    {
        return $this->state(fn (array $attributes) => [
            'order_form_deadline' => $deadline ?? now()->addDays(30),
        ]);
    }

    public function withPromotionPostDeadline(?string $deadline = null): static
    {
        return $this->state(fn (array $attributes) => [
            'promotion_post_deadline' => $deadline ?? now()->addDays(30),
        ]);
    }

    /**
     * Opt out of the {@see configure()} hook that auto-seeds an active
     * payment gateway on the project. Use this in tests that need to
     * assert the absence-of-gateway path explicitly.
     */
    public function withoutPaymentGateway(): static
    {
        return $this->state(function () {
            self::$skipGatewaySeed = true;

            return [];
        });
    }

    /**
     * @var bool Tracks the most recently-applied opt-out state.
     */
    private static bool $skipGatewaySeed = false;

    /**
     * Auto-seed an active payment gateway on the parent project when the
     * event is created with hotel_reservation_enabled. Hotel reservation
     * routes are gated by both the flag AND project gateway availability
     * (EnsureHotelReservationEnabled middleware), so factories produce
     * test fixtures that match the runtime invariant.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Event $event) {
            $skip = self::$skipGatewaySeed;
            self::$skipGatewaySeed = false;

            if ($skip || ! $event->hotel_reservation_enabled) {
                return;
            }

            $project = $event->project;
            if (! $project || $project->paymentGateways()->exists()) {
                return;
            }

            // Use a deterministic label + mode so collisions with explicit
            // ProjectPaymentGateway::factory() calls in tests (which
            // randomize label/mode) stay vanishingly rare.
            ProjectPaymentGateway::factory()->create([
                'project_id' => $project->id,
                'is_active' => true,
                'mode' => 'test',
                'label' => 'Auto Seed',
            ]);
        });
    }
}
