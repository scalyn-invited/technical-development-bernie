<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MatchGame extends Model
{
    use HasFactory;
    protected $table = 'matches';

    protected $fillable = [
        'home_team_id',
        'away_team_id',
        'venue_id',
        'scheduled_at',
        'home_score',
        'away_score',
        'winner_team_id',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function homeTeam()
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam()
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function players() {
        return $this->belongsToMany(Player::class)
            ->withPivot('position', 'status')
            ->withTimestamps();
    }

    public function winner()
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

}
