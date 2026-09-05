<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\User;
use App\Policies\OrderPolicy;
use PHPUnit\Framework\TestCase;

class OrderPolicyTest extends TestCase
{
    private OrderPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new OrderPolicy();
    }

    private function userWithRole(string $role, int $id): User
    {
        $user = new User(['role' => $role]);
        $user->id = $id;

        return $user;
    }

    private function orderOwnedBy(int $userId): Order
    {
        return new Order(['user_id' => $userId]);
    }

    public function test_approver_can_view_any_order(): void
    {
        $approver = $this->userWithRole('approver', 1);
        $order = $this->orderOwnedBy(999);

        $this->assertTrue($this->policy->view($approver, $order));
    }

    public function test_owner_can_view_their_own_order(): void
    {
        $user = $this->userWithRole('employee', 5);
        $order = $this->orderOwnedBy(5);

        $this->assertTrue($this->policy->view($user, $order));
    }

    public function test_non_owner_non_approver_cannot_view_order(): void
    {
        $user = $this->userWithRole('employee', 5);
        $order = $this->orderOwnedBy(999);

        $this->assertFalse($this->policy->view($user, $order));
    }

    public function test_non_owner_cannot_update_order(): void
    {
        $user = $this->userWithRole('employee', 5);
        $order = $this->orderOwnedBy(999);

        $this->assertFalse($this->policy->update($user, $order));
    }

    public function test_non_owner_cannot_delete_order(): void
    {
        $user = $this->userWithRole('employee', 5);
        $order = $this->orderOwnedBy(999);

        $this->assertFalse($this->policy->delete($user, $order));
    }

    public function test_any_authenticated_user_can_view_any_index(): void
    {
        $user = $this->userWithRole('employee', 1);

        $this->assertTrue($this->policy->viewAny($user));
    }
}