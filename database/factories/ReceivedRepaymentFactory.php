<?php

   namespace Database\Factories;

   use App\Models\Loan;
   use App\Models\ReceivedRepayment;
   use Illuminate\Database\Eloquent\Factories\Factory;

   class ReceivedRepaymentFactory extends Factory
   {
       protected $model = ReceivedRepayment::class;

       public function definition()
       {
           return [
               'loan_id' => Loan::factory(),
               'amount' => $this->faker->randomFloat(2, 100, 1000),
               'received_at' => now(),
           ];
       }
   }