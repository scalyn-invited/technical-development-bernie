<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->role === 'approver') {
            return true;
        }
        return false;
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->role === 'approver') {
            return true;
        }
        return $user->id === $order->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Order $order): bool
    {
        if ($user->role === 'approver') {
            return true;
        }
        return $user->id === $order->user_id;
    }

    public function delete(User $user, Order $order): bool
    {
        if ($user->role === 'approver') {
            return true;
        }
        return $user->id === $order->user_id;
    }

    public function restore(User $user, Order $order): bool
    {
        return $this->delete($user, $order);
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return $this->delete($user, $order);
    }
}
