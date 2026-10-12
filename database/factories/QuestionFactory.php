<?php

namespace Database\Factories;

use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'content' => $this->faker->sentence().'?',
            'category' => $this->faker->randomElement(['Easy', 'Medium', 'Hard', 'Konsep', 'Praktikum']),
            'explanation' => $this->faker->sentence(),
        ];
    }
}
