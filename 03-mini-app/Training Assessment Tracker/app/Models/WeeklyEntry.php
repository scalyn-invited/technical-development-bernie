<?php

namespace App\Models;

use App\Enums\WeeklyEntryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyEntry extends Model
{
    /** @use HasFactory<\Database\Factories\WeeklyEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'development_plan_id', 'week_number', 'skill_id', 'objective',
        'evidence', 'outcome_score', 'status', 'recorded_by', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WeeklyEntryStatus::class,
            'week_number' => 'integer',
            'outcome_score' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    public function developmentPlan(): BelongsTo
    {
        return $this->belongsTo(DevelopmentPlan::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** The open-weeks queue: everything not yet closed. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            WeeklyEntryStatus::Planned,
            WeeklyEntryStatus::Evidenced,
        ]);
    }
}
