<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\Vouche;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vouche>
 */
class VoucheFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Vouche::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vouched_by_id' => Supplier::factory(),
            'vouched_for_id' => Supplier::factory(),
            'message' => $this->faker->paragraph(2), // 2 paragraph message
        ];
    }

    /**
     * Create a vouch with a specific voucher and vouchee.
     */
    public function between(Supplier|int $vouchedBy, Supplier|int $vouchedFor): static
    {
        return $this->state(function (array $attributes) use ($vouchedBy, $vouchedFor) {
            return [
                'vouched_by_id' => $vouchedBy instanceof Supplier ? $vouchedBy->id : $vouchedBy,
                'vouched_for_id' => $vouchedFor instanceof Supplier ? $vouchedFor->id : $vouchedFor,
            ];
        });
    }
}
