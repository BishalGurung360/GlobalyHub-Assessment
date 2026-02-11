<?php

namespace Database\Factories;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => fake()->uuid(),
            'user_id' => User::factory(),
            'channel' => fake()->randomElement(['log', 'email', 'sms']),
            'title' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'payload' => null,
            'status' => NotificationStatus::Pending,
            'attempts' => 0,
            'max_attempts' => 3,
            'scheduled_at' => null,
            'processed_at' => null,
            'failed_at' => null,
            'last_error' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => NotificationStatus::Sent,
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => NotificationStatus::Failed,
            'failed_at' => now(),
            'last_error' => fake()->sentence(),
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => NotificationStatus::Processing,
            'attempts' => fake()->numberBetween(1, $attributes['max_attempts'] - 1),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => NotificationStatus::Pending,
        ]);
    }

    public function scheduled(\DateTimeInterface $scheduledAt): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_at' => $scheduledAt,
        ]);
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function channel(string $channel): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => $channel,
        ]);
    }

    public function withAttempts(int $attempts): static
    {
        return $this->state(fn (array $attributes) => [
            'attempts' => $attempts,
        ]);
    }
}
