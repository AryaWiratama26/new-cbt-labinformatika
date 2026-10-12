<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->numerify('3120######'),
            'role' => 'mahasiswa',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * A lab-staff administrator account.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'name' => $this->faker->name(),
            'username' => $this->faker->unique()->bothify('admin####'),
        ]);
    }

    /**
     * A student attached to the given classroom.
     */
    public function student(?Classroom $classroom = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'mahasiswa',
            'classroom_id' => $classroom?->id,
        ]);
    }

    /**
     * Login with a known plaintext password.
     */
    public function withPassword(string $plain): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => Hash::make($plain),
        ]);
    }
}
