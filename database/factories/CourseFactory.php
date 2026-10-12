<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('IF-###?')),
            'name' => $this->faker->randomElement([
                'Basis Data',
                'Jaringan Komputer',
                'Pemrograman Web',
                'Sistem Operasi',
                'Keamanan Jaringan',
            ]).' '.$this->faker->numberBetween(1, 3),
        ];
    }
}
