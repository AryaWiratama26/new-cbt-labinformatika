<?php

namespace Database\Factories;

use App\Models\Option;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Option>
 */
class OptionFactory extends Factory
{
    protected $model = Option::class;

    public function definition(): array
    {
        return [
            'content' => $this->faker->words(3, true),
            'is_correct' => false,
        ];
    }

    /**
     * Mark the option as the correct answer.
     */
    public function correct(): static
    {
        return $this->state(fn (array $attributes) => ['is_correct' => true]);
    }
}
