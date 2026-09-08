<?php

namespace App\Models;

use App\Enums\AssessmentType;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory;

    protected $fillable = [
        'development_plan_id', 'skill_id', 'type',
        'score', 'note', 'recorded_by', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AssessmentType::class,
            // decimal:2 keeps the score a string in PHP so no float rounding
            // can drift a recorded percentage before it reaches the delta.
            'score' => 'decimal:2',
            'recorded_at' => 'datetime',
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

    public function scopeOfType(Builder $query, AssessmentType $type): Builder
    {
        return $query->where('type', $type);
    }
}
