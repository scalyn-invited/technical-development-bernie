<?php

namespace App\Models;

use App\Enums\PlanStatus;
use Database\Factories\DevelopmentPlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevelopmentPlan extends Model
{
    /** @use HasFactory<DevelopmentPlanFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'key_gaps', 'weekly_focus', 'status',
        'activated_at', 'completed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PlanStatus::class,
            'activated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /** Baselines define plan membership, including retired catalogue skills. */
    public function baselineAssessments(): HasMany
    {
        return $this->assessments()->where('type', 'baseline');
    }

    public function weeklyEntries(): HasMany
    {
        return $this->hasMany(WeeklyEntry::class);
    }

    /**
     * The skills on this plan, reached through the assessments table.
     * assessments is a real entity in its own right, not a join table, so the
     * pivot carries the score and who recorded it.
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'assessments')
            ->withPivot(['type', 'score', 'note', 'recorded_at'])
            ->withTimestamps();
    }

    public function scopeStatus(Builder $query, PlanStatus $status): Builder
    {
        return $query->where('status', $status);
    }
}
