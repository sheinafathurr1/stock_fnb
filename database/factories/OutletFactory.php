<?php

namespace Database\Factories;

use App\Models\Outlet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Outlet>
 */
class OutletFactory extends Factory
{
    protected $model = Outlet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shortName = fake()->unique()->words(2, true);
        $name = 'Barista ' . ucfirst($shortName);

        return [
            'nama' => $name,
            'kode_outlet' => strtoupper(fake()->unique()->lexify('???')),
            'icon' => '☕',
        ];
    }
}

