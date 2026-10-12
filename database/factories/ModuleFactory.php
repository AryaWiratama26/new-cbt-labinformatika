<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    protected $model = Module::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'module_number' => $this->faker->numberBetween(1, 8),
            'name' => 'Modul '.$this->faker->randomElement([
                'Pengantar', 'Instalasi', 'Konfigurasi', 'Pengujian', 'Optimasi',
            ]),
            'description' => $this->faker->sentence(),
        ];
    }
}
