<?php

namespace Database\Factories;

use App\Models\ScheduledRepayment;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduledRepaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ScheduledRepayment::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'loan_id' => Loan::factory(),
            'amount' => $this->faker->randomFloat(2, 100, 1000),
            'due_date' => $this->faker->dateTimeBetween('now', '+1 year'),
            'status' => ScheduledRepayment::STATUS_DUE,
        ];
    }
}
