<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Exam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    protected $model = Exam::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => 'Ujian '.$this->faker->randomElement(['Praktikum', 'Quiz', 'UTS', 'UAS']),
            'description' => $this->faker->sentence(),
            'is_active' => true,
            'passing_grade' => 70,
            'max_attempts' => 1,
            'max_tab_switches' => 3,
            'require_fullscreen' => false,
        ];
    }

    /**
     * Exam that allows a retake after a failing score.
     */
    public function withRemedial(int $maxAttempts = 2): static
    {
        return $this->state(fn (array $attributes) => ['max_attempts' => $maxAttempts]);
    }

    /**
     * Exam that enforces fullscreen mode.
     */
    public function requiringFullscreen(): static
    {
        return $this->state(fn (array $attributes) => ['require_fullscreen' => true]);
    }
}
