<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemOutletOwnership;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ItemOutletOwnership>
 */
class ItemOutletOwnershipFactory extends Factory
{
    protected $model = ItemOutletOwnership::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'outlet_id' => Outlet::factory(),
            'current_status' => 'in_stock',
        ];
    }

    /**
     * Stock that is running low.
     */
    public function almostOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_status' => 'almost_out',
        ]);
    }

    /**
     * Stock that has run out.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_status' => 'out_of_stock',
        ]);
    }
}
