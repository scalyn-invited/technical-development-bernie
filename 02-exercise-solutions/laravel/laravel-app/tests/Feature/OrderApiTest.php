<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class OrderApiTest extends TestCase
{
     use RefreshDatabase; // migrates a fresh DB for each test, wraps in a transaction
    
     // Testing that a user can only see their own orders
     public function test_authenticated_user_can_list_their_own_orders(): void
    {
        $user = User::factory()->create(['role' => 'requester']);
        $otherUser = User::factory()->create(['role' => 'requester']);

        Order::factory()->count(2)->create(['user_id' => $user->id]);
        Order::factory()->count(3)->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data'); // only their own orders, per OrderController::index
    }

    // Testing approver can list all orders
    public function test_approver_can_list_all_orders(): void
    {
        $approver = User::factory()->create(['role' => 'approver']);
        Order::factory()->count(4)->create();

        $response = $this->actingAs($approver, 'sanctum')
            ->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonCount(4, 'data');
    }

    public function test_index_response_has_expected_structure(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(2)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'customer_id', 'total', 'status', 'created_at']],
                'meta' => ['total', 'per_page', 'current_page'],
                'links' => ['first', 'last'],
            ])
            ->assertJsonPath('meta.total', 2); // catches the array_merge_recursive collision bug
    }

    public function test_show_response_has_expected_structure(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/orders/{$order->id}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'customer_id', 'total', 'status', 'created_at'],
            ]);
    }

    // Testing unauthenticated access (401)
    public function test_guest_cannot_list_orders(): void
    {
        $response = $this->getJson('/api/orders');

        $response->assertStatus(401)
            ->assertJson([
                'error' => true,
                'code' => 'unauthenticated',
            ]);
    }

    // Testing store — success + validation failure
    public function test_user_can_create_an_order(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $payload = [
            'customer_id' => $customer->id,
            'total' => 99.50,
            'status' => 'pending',
            'placed_at' => now()->toDateString(),
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.customer_id', $customer->id);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_cannot_assign_order_to_another_user_via_payload(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', [
                'customer_id' => $customer->id,
                'total' => 50.00,
                'status' => 'pending',
                'placed_at' => now()->toDateString(),
                'user_id' => $otherUser->id, // attacker-supplied — should be ignored
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'user_id' => $user->id, // must be the authenticated user, not the spoofed one
        ]);

        $this->assertDatabaseMissing('orders', [
            'customer_id' => $customer->id,
            'user_id' => $otherUser->id,
        ]);
    }

    public function test_creating_order_fails_with_invalid_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', [
                'customer_id' => 999, // doesn't exist
                'total' => -5,        // fails min:0.01
                'status' => 'bogus',  // not in allowed list
                // placed_at missing entirely
            ]);

        $response->assertStatus(422)
            ->assertJson(['error' => true, 'code' => 'validation_failed'])
            ->assertJsonValidationErrors(['customer_id', 'total', 'status', 'placed_at'], 'details');
    }
    
    // Testing policy-based authorization (show/update/destroy → 403)
    public function test_user_cannot_view_another_users_order(): void
    {
        $owner = User::factory()->create(['role' => 'requester']);
        $intruder = User::factory()->create(['role' => 'requester']);
        $order = Order::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($intruder, 'sanctum')
            ->getJson("/api/orders/{$order->id}");

        $response->assertStatus(403)
            ->assertJson(['error' => true, 'code' => 'forbidden']);
    }

    public function test_approver_can_view_any_order(): void
    {
        $owner = User::factory()->create();
        $approver = User::factory()->create(['role' => 'approver']);
        $order = Order::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($approver, 'sanctum')
            ->getJson("/api/orders/{$order->id}")
            ->assertStatus(200);
    }

    public function test_owner_can_delete_their_own_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/orders/{$order->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_user_access_404_for_nonexistent_order(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/orders/9999') // assuming this ID doesn't exist
            ->assertStatus(404)
            ->assertJson(['error' => true, 'code' => 'not_found']);
    }

    public function test_non_owner_cannot_delete_others_order(): void
    {
        $owner = User::factory()->create(['role' => 'employee']);
        $intruder = User::factory()->create(['role' => 'employee']);
        $order = Order::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($intruder, 'sanctum')
            ->deleteJson("/api/orders/{$order->id}");

        $response->assertStatus(403)
            ->assertJson(['error' => true, 'code' => 'forbidden']);

        $this->assertDatabaseHas('orders', ['id' => $order->id]); // must still exist
    }

    public function test_order_creation_requires_existing_customer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', [
                'customer_id' => 999999,
                'total' => 50,
                'status' => 'pending',
                'placed_at' => now()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id'], 'details');
    }

    public function test_order_creation_rejects_total_below_minimum(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', [
                'customer_id' => $customer->id,
                'total' => 0,
                'status' => 'pending',
                'placed_at' => now()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['total'], 'details');
    }

    public function test_owner_can_update_their_order(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => 'pending']);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/orders/{$order->id}", [
                'customer_id' => $customer->id,
                'total' => 123.45,
                'status' => 'completed',
                'placed_at' => now()->toDateString(),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'completed']);
    }

    public function test_non_owner_cannot_update_others_order(): void
    {
        $owner = User::factory()->create(['role' => 'employee']);
        $intruder = User::factory()->create(['role' => 'employee']);
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['user_id' => $owner->id, 'status' => 'pending']);

        $response = $this->actingAs($intruder, 'sanctum')
            ->putJson("/api/orders/{$order->id}", [
                'customer_id' => $customer->id,
                'total' => 1,
                'status' => 'cancelled',
                'placed_at' => now()->toDateString(),
            ]);

        $response->assertStatus(403)
            ->assertJson(['error' => true, 'code' => 'forbidden']);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']); // unchanged
    }

    public function test_approver_can_update_any_order(): void
    {
        $owner = User::factory()->create(['role' => 'employee']);
        $approver = User::factory()->create(['role' => 'approver']);
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['user_id' => $owner->id, 'status' => 'pending']);

        $this->actingAs($approver, 'sanctum')
            ->putJson("/api/orders/{$order->id}", [
                'customer_id' => $customer->id,
                'total' => 10,
                'status' => 'completed',
                'placed_at' => now()->toDateString(),
            ])
            ->assertStatus(200);
    }

    public function test_update_fails_with_invalid_status(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/orders/{$order->id}", [
                'customer_id' => $customer->id,
                'total' => 10,
                'status' => 'not-a-real-status',
                'placed_at' => now()->toDateString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status'], 'details');
    }

    public function test_update_returns_404_for_nonexistent_order(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $missingId = (Order::max('id') ?? 0) + 1;

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/orders/{$missingId}", [
                'customer_id' => $customer->id,
                'total' => 10,
                'status' => 'pending',
                'placed_at' => now()->toDateString(),
            ])
            ->assertStatus(404)
            ->assertJson(['error' => true, 'code' => 'not_found']);
    }

    public function test_patch_with_partial_fields_currently_requires_all_fields(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => 'pending']);

        // StoreOrderRequest marks every field 'required', so a PATCH with just one
        // field is rejected today rather than treated as a partial update. This
        // pins down that current behavior — if it's ever meant to change, this
        // test should force a deliberate decision, not a silent regression.
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/orders/{$order->id}", [
                'status' => 'completed',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id', 'total', 'placed_at'], 'details');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']); // unchanged
    }

    public function test_index_query_count_does_not_scale_with_order_count(): void
    {
        $approver = User::factory()->create(['role' => 'approver']);
        Order::factory()->count(3)->create();

        DB::enableQueryLog();
        $this->actingAs($approver, 'sanctum')->getJson('/api/orders')->assertStatus(200);
        $queriesForThree = count(DB::getQueryLog());
        DB::flushQueryLog();

        Order::factory()->count(7)->create(); // 10 total now, still within one page (paginate(10))
        DB::flushQueryLog(); // discard the factory-insert noise from the line above

        $this->actingAs($approver, 'sanctum')->getJson('/api/orders')->assertStatus(200);
        $queriesForTen = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $queriesForThree,
            $queriesForTen,
            'Query count should not scale with number of orders returned (possible N+1).'
        );
    }
}


