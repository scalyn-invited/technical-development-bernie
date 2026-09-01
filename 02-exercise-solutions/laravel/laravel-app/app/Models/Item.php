<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Item extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'product_name', 'price', 'quantity'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}