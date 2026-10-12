<?php

namespace Database\Factories;

use App\Models\ExamSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSession>
 */
class ExamSessionFactory extends Factory
{
    protected $model = ExamSession::class;

    public function definition(): array
    {
        return [
            'started_at' => now()->subMinutes(30),
            'finished_at' => null,
            'score' => null,
            'attempt_number' => 1,
            'tab_switches' => 0,
        ];
    }

    /**
     * A completed session with a score.
     */
    public function finished(float $score = 80): static
    {
        return $this->state(fn (array $attributes) => [
            'score' => $score,
            'finished_at' => now(),
        ]);
    }

    /**
     * Specify the attempt number (used for remedial scenarios).
     */
    public function attempt(int $number): static
    {
        return $this->state(fn (array $attributes) => ['attempt_number' => $number]);
    }
}
