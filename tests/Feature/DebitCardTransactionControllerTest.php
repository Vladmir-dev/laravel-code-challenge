<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DebitCard $debitCard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCardTransactions()
    {
        // get /debit-card-transactions
        DebitCardTransaction::factory()->count(3)->create([
            'debit_card_id' => $this->debitCard->id,
            'amount' => 100,
            'currency_code' => 'IDR',
        ]);

        $response = $this->getJson("/debit-card-transactions?debit_card_id={$this->debitCard->id}");

        $response->assertStatus(200)
                 ->assertJsonCount(3, 'data')
                 ->assertJsonStructure([
                     'data' => [
                         '*' => ['amount', 'currency_code']
                     ]
                 ]);

        $this->assertDatabaseCount('debit_card_transactions', 3);
    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        // get /debit-card-transactions
        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);
        DebitCardTransaction::factory()->create(['debit_card_id' => $otherDebitCard->id]);

        $response = $this->getJson("/debit-card-transactions?debit_card_id={$otherDebitCard->id}");

        $response->assertStatus(403);
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        // post /debit-card-transactions
        $payload = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 100,
            'currency_code' => 'IDR',
        ];

        $response = $this->postJson('/debit-card-transactions', $payload);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'data' => ['amount', 'currency_code']
                 ])
                 ->assertJsonFragment(['amount' => 100, 'currency_code' => 'IDR']);

        $this->assertDatabaseHas('debit_card_transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 100,
            'currency_code' => 'IDR',
        ]);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        // post /debit-card-transactions
        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);
        $payload = [
            'debit_card_id' => $otherDebitCard->id,
            'amount' => 100,
            'currency_code' => 'IDR',
        ];

        $response = $this->postJson('/debit-card-transactions', $payload);

        $response->assertStatus(403);
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        // get /debit-card-transactions/{debitCardTransaction}
        $transaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $this->debitCard->id,
        ]);

        $response = $this->getJson("/debit-card-transactions/{$transaction->id}");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => ['amount', 'currency_code']
                 ])
                 ->assertJsonFragment(['amount' => 100, 'currency_code' => 'IDR']);
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        // get /debit-card-transactions/{debitCardTransaction}
        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);
        $transaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $otherDebitCard->id,
        ]);

        $response = $this->getJson("/debit-card-transactions/{$transaction->id}");

        $response->assertStatus(403);
    }

    // Extra bonus for extra tests :)

        public function testCustomerCannotCreateADebitCardTransactionWithInvalidCurrency()
    {
        $payload = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 100,
            'currency_code' => 'USD',
        ];

        $response = $this->postJson('/debit-card-transactions', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors('currency_code');
    }

    public function testCustomerCannotCreateADebitCardTransactionWithNonIntegerAmount()
    {
        $payload = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 100.50,
            'currency_code' => 'IDR',
        ];

        $response = $this->postJson('/debit-card-transactions', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors('amount');
    }
}
