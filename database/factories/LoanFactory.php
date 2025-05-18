<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Loan::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => $this->faker->randomFloat(2, 1000, 10000),
            'outstanding_amount' => fn (array $attributes) => $attributes['amount'],
            'terms' => $this->faker->randomElement([3, 6, 12]),
            'currency_code' => $this->faker->randomElement([Loan::CURRENCY_SGD, Loan::CURRENCY_VND]),
            'processed_at' => now(),
            'status' => Loan::STATUS_DUE,
            'created_at' => now(),
        ];
    }
}
