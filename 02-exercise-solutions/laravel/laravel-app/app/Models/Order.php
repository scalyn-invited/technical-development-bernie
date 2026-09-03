<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['customer_id', 'user_id', 'total', 'status', 'placed_at'];
    protected $dates = ['placed_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }


    // In Order.php model
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeRecentFirst($query)
    {
        return $query->orderBy('placed_at', 'desc');
    }

    // Accessor for formatted total
    protected $appends = ['formatted_total'];

    public function getFormattedTotalAttribute()
    {
        return '$' . number_format($this->total, 2);
    }
}