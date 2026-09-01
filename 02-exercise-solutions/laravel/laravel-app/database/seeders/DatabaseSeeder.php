<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Item;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create 10 customers, each with 25 orders, each with 5 items
        Customer::factory(10)
            ->has(Order::factory(25)
                ->has(Item::factory(5))
            )
            ->create();
    }
}