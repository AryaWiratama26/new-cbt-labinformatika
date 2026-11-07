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
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'content' => $this->faker->sentence().'?',
            'category' => $this->faker->randomElement(['Easy', 'Medium', 'Hard', 'Konsep', 'Praktikum']),
            'explanation' => $this->faker->sentence(),
        ];
    }

    public function coding(string $language = 'python'): static
    {
        return $this->state(fn () => [
            'type' => Question::TYPE_CODING,
            'code_language' => $language,
            'starter_code' => "def solve():\n    pass",
            'solution_code' => "def solve():\n    return 42",
        ]);
    }
}
