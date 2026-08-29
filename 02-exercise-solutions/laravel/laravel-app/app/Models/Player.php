<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Player extends Model
{
    use HasFactory;
    protected $fillable = [
        'team_id',
        'name',
        'season',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
