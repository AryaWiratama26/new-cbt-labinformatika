<?php

namespace Database\Factories;

use App\Models\Classroom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    protected $model = Classroom::class;

    public function definition(): array
    {
        return [
            'name' => strtoupper($this->faker->unique->numerify('IF-?##')),
            'academic_year' => $this->faker->numberBetween(2024, 2026).'/'.$this->faker->numberBetween(2025, 2027),
            'semester' => $this->faker->randomElement(['Ganjil', 'Genap']),
        ];
    }
}
