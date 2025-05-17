<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DebitCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        // get /debit-cards
        DebitCard::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'disabled_at' => null,
        ]);

        $response = $this->getJson('/debit-cards');

        $response->assertStatus(200)
                 ->assertJsonCount(3, 'data')
                 ->assertJsonStructure([
                     'data' => [
                         '*' => ['id', 'number', 'type', 'expiration_date', 'is_active']
                     ]
                 ]);

        $this->assertDatabaseCount('debit_cards', 3);
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        // get /debit-cards
        $otherUser = User::factory()->create();
        DebitCard::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->getJson('/debit-cards');

        $response->assertStatus(200)
                 ->assertJsonCount(0, 'data');

        $this->assertDatabaseCount('debit_cards', 1);
        $this->assertDatabaseMissing('debit_cards', ['user_id' => $this->user->id]);
    }

    public function testCustomerCanCreateADebitCard()
    {
        // post /debit-cards
        $payload = ['type' => 'visa'];

        $response = $this->postJson('/debit-cards', $payload);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'data' => ['id', 'number', 'type', 'expiration_date', 'is_active']
                 ])
                 ->assertJsonFragment(['type' => 'visa', 'is_active' => true]);

        $this->assertDatabaseHas('debit_cards', [
            'user_id' => $this->user->id,
            'type' => 'visa',
            'disabled_at' => null,
        ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/debit-cards/{$debitCard->id}");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => ['id', 'number', 'type', 'expiration_date', 'is_active']
                 ])
                 ->assertJsonFragment(['id' => $debitCard->id]);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $otherUser = User::factory()->create();
        $debitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->getJson("/debit-cards/{$debitCard->id}");

        $response->assertStatus(403);
    }

    public function testCustomerCanActivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id,
            'disabled_at' => now(),
        ]);

        $response = $this->putJson("/debit-cards/{$debitCard->id}", ['is_active' => true]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['is_active' => true]);

        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
            'disabled_at' => null,
        ]);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id,
            'disabled_at' => null,
        ]);

        $response = $this->putJson("/debit-cards/{$debitCard->id}", ['is_active' => false]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['is_active' => false]);

        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
            'disabled_at' => now()->toDateTimeString(),
        ]);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // put api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/debit-cards/{$debitCard->id}", ['is_active' => 'invalid']);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors('is_active');
    }

    public function testCustomerCanDeleteADebitCard()
    {
        // delete api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/debit-cards/{$debitCard->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('debit_cards', ['id' => $debitCard->id]);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // delete api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);
        DebitCardTransaction::factory()->create(['debit_card_id' => $debitCard->id]);

        $response = $this->deleteJson("/debit-cards/{$debitCard->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('debit_cards', ['id' => $debitCard->id, 'deleted_at' => null]);
    }

    

    // Extra bonus for extra tests :)
        public function testCustomerCannotCreateADebitCardWithoutType()
    {
        $payload = [];

        $response = $this->postJson('/debit-cards', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors('type');
    }
}
