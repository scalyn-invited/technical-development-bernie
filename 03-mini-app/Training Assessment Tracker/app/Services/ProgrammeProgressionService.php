<?php

namespace App\Services;

use App\Enums\AssessmentType;
use App\Enums\PlanStatus;
use App\Enums\WeeklyEntryStatus;
use App\Exceptions\ProgressionConflict;
use App\Models\Assessment;
use App\Models\DevelopmentPlan;
use App\Models\Skill;
use App\Models\User;
use App\Models\WeeklyEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class ProgrammeProgressionService
{
    // Every assessment write and transition locks the same parent row.
    public function activate(DevelopmentPlan $plan, User $actor): DevelopmentPlan
    {
        return DB::transaction(function () use ($plan, $actor) {
            $plan = DevelopmentPlan::lockForUpdate()->findOrFail($plan->id);
            Gate::forUser($actor)->authorize('update', $plan);
            $this->requireState($plan, PlanStatus::Draft);
            $baselines = $plan->baselineAssessments()->get();
            if ($baselines->isEmpty() || $baselines->contains(fn ($row) => ! is_numeric($row->score) || $row->score < 0 || $row->score > 100)) {
                throw new ProgressionConflict('Activation requires a non-empty baseline set with scores between 0 and 100.');
            }
            if ($plan->assessments()->where('type', AssessmentType::Final)->exists()) {
                throw new ProgressionConflict('A draft plan cannot contain final scores.');
            }
            $plan->update(['status' => PlanStatus::Active, 'activated_at' => now()]);

            return $plan;
        });
    }

    public function recordBaseline(DevelopmentPlan $plan, User $actor, array $data): Assessment
    {
        return DB::transaction(function () use ($plan, $actor, $data) {
            $plan = DevelopmentPlan::lockForUpdate()->findOrFail($plan->id);
            Gate::forUser($actor)->authorize('create', [Assessment::class, $plan]);
            $this->requireState($plan, PlanStatus::Draft);
            if (($data['type'] ?? null) !== 'baseline') {
                throw new ProgressionConflict('Submit the full final score set to the completion endpoint.');
            }
            $data = Validator::make($data, [
                'skill_id' => ['required', 'integer', 'exists:skills,id'],
                'score' => ['required', 'numeric', 'between:0,100'],
                'note' => ['nullable', 'string', 'max:2000'],
            ])->validate();

            $skill = Skill::lockForUpdate()->findOrFail($data['skill_id']);
            if (! $skill->is_active) {
                throw new ProgressionConflict('New baselines require an active catalogue skill.');
            }

            return $plan->assessments()->create($data + [
                'type' => AssessmentType::Baseline,
                'recorded_by' => $actor->id,
                'recorded_at' => now(),
            ]);
        });
    }

    public function complete(DevelopmentPlan $plan, User $actor, array $finals): DevelopmentPlan
    {
        return DB::transaction(function () use ($plan, $actor, $finals) {
            $plan = DevelopmentPlan::lockForUpdate()->findOrFail($plan->id);
            Gate::forUser($actor)->authorize('update', $plan);
            $this->requireState($plan, PlanStatus::Active);
            $validated = Validator::make(['finals' => $finals], [
                'finals' => ['required', 'array', 'min:1'],
                'finals.*.skill_id' => ['required', 'integer', 'distinct'],
                'finals.*.score' => ['required', 'numeric', 'between:0,100'],
                'finals.*.note' => ['nullable', 'string', 'max:2000'],
            ])->validate()['finals'];
            $expected = $plan->baselineAssessments()->pluck('skill_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $actual = collect($validated)->pluck('skill_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($expected === [] || $expected !== $actual) {
                throw new ProgressionConflict('Final scores must match the baseline skill set exactly.');
            }
            if ($plan->weeklyEntries()->where('status', '!=', 'closed')->exists()) {
                throw new ProgressionConflict('All weeks must be closed before completion.');
            }
            if ($plan->assessments()->where('type', AssessmentType::Final)->exists()) {
                throw new ProgressionConflict('Final scores have already been recorded.');
            }
            foreach ($validated as $final) {
                $plan->assessments()->create([
                    'skill_id' => $final['skill_id'],
                    'score' => $final['score'],
                    'note' => $final['note'] ?? null,
                    'type' => AssessmentType::Final,
                    'recorded_by' => $actor->id,
                    'recorded_at' => now(),
                ]);
            }
            $plan->update(['status' => PlanStatus::Completed, 'completed_at' => now()]);

            return $plan;
        });
    }

    public function correctBaseline(DevelopmentPlan $plan, Assessment $assessment, User $actor, array $data): Assessment
    {
        return DB::transaction(function () use ($plan, $assessment, $actor, $data) {
            $plan = DevelopmentPlan::lockForUpdate()->findOrFail($plan->id);
            $assessment = $plan->assessments()->findOrFail($assessment->id);
            Gate::forUser($actor)->authorize('update', $assessment);
            $this->requireState($plan, PlanStatus::Draft);
            if ($assessment->type !== AssessmentType::Baseline) {
                throw new ProgressionConflict('Only draft baseline scores can be corrected.');
            }
            $data = Validator::make($data, [
                'score' => ['sometimes', 'required', 'numeric', 'between:0,100'],
                'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
                'skill_id' => ['prohibited'],
                'type' => ['prohibited'],
            ])->validate();
            $assessment->update($data + ['recorded_by' => $actor->id, 'recorded_at' => now()]);

            return $assessment;
        });
    }

    public function createWeek(DevelopmentPlan $plan, User $actor, array $data): WeeklyEntry
    {
        return DB::transaction(function () use ($plan, $actor, $data) {
            $plan = DevelopmentPlan::lockForUpdate()->findOrFail($plan->id);
            Gate::forUser($actor)->authorize('create', [WeeklyEntry::class, $plan]);
            $this->requireState($plan, PlanStatus::Active);
            $data = Validator::make($data, [
                'week_number' => ['required', 'integer', 'between:1,255'],
                'skill_id' => ['required', 'integer', 'exists:skills,id'],
                'objective' => ['required', 'string', 'max:2000'],
            ])->validate();
            // Skill lock serializes new open weeks with catalogue deactivation.
            $skill = Skill::lockForUpdate()->findOrFail($data['skill_id']);
            if (! $skill->is_active || ! $plan->baselineAssessments()->where('skill_id', $skill->id)->exists()) {
                throw new ProgressionConflict('The focus must be an active skill baselined on this plan.');
            }
            $weeks = $plan->weeklyEntries()->orderBy('week_number')->get();
            $numbers = $weeks->pluck('week_number')->all();
            if ($numbers !== ($weeks->isEmpty() ? [] : range(1, $weeks->count()))
                || (int) $data['week_number'] !== $weeks->count() + 1
                || $weeks->contains(fn ($week) => $week->status !== WeeklyEntryStatus::Closed)) {
                throw new ProgressionConflict('Create the next contiguous week only after all earlier weeks are closed.');
            }

            return $plan->weeklyEntries()->create($data + [
                'status' => WeeklyEntryStatus::Planned, 'recorded_by' => $actor->id,
            ]);
        });
    }

    public function updateWeek(DevelopmentPlan $plan, WeeklyEntry $entry, User $actor, array $data): WeeklyEntry
    {
        return DB::transaction(function () use ($plan, $entry, $actor, $data) {
            $plan = DevelopmentPlan::lockForUpdate()->findOrFail($plan->id);
            $entry = $plan->weeklyEntries()->findOrFail($entry->id);
            Gate::forUser($actor)->authorize('update', $entry);
            $this->requireState($plan, PlanStatus::Active);
            if ($entry->status === WeeklyEntryStatus::Closed) {
                throw new ProgressionConflict('A closed week is read-only.');
            }
            if (! $plan->baselineAssessments()->where('skill_id', $entry->skill_id)->exists()) {
                throw new ProgressionConflict('The focus skill must have a baseline on this plan.');
            }
            $data = Validator::make($data, [
                'status' => ['sometimes', 'in:planned,evidenced,closed'],
                'objective' => ['sometimes', 'required', 'string', 'max:2000'],
                'evidence' => ['sometimes', 'nullable', 'string', 'max:2000'],
                'outcome_score' => ['sometimes', 'nullable', 'numeric', 'between:0,100'],
            ])->validate();
            $target = isset($data['status']) ? WeeklyEntryStatus::from($data['status']) : $entry->status;
            $next = $entry->status === WeeklyEntryStatus::Planned ? WeeklyEntryStatus::Evidenced : WeeklyEntryStatus::Closed;
            if ($target !== $entry->status && $target !== $next) {
                throw new ProgressionConflict('Weeks must progress planned → evidenced → closed.');
            }
            // Validate the locked persisted row plus only fields actually submitted.
            $result = array_replace([
                'evidence' => $entry->evidence, 'outcome_score' => $entry->outcome_score,
            ], $data);
            Validator::make($result, [
                'evidence' => [$target !== WeeklyEntryStatus::Planned ? 'required' : 'nullable', 'string', 'max:2000'],
                'outcome_score' => [$target === WeeklyEntryStatus::Closed ? 'required' : 'nullable', 'numeric', 'between:0,100'],
            ])->validate();
            $entry->update($data + ['closed_at' => $target === WeeklyEntryStatus::Closed ? now() : null]);

            return $entry;
        });
    }

    public function updateSkill(Skill $skill, User $actor, array $data): Skill
    {
        return DB::transaction(function () use ($skill, $actor, $data) {
            $skill = Skill::lockForUpdate()->findOrFail($skill->id);
            Gate::forUser($actor)->authorize('update', $skill);
            if (array_key_exists('is_active', $data) && ! $data['is_active']
                && WeeklyEntry::where('skill_id', $skill->id)->open()->lockForUpdate()->first()) {
                throw new ProgressionConflict('A skill that is the focus of an open week cannot be deactivated.');
            }
            $skill->update($data);

            return $skill;
        });
    }

    public function comparison(DevelopmentPlan $plan, User $actor): array
    {
        Gate::forUser($actor)->authorize('viewComparison', $plan);
        // One assessment query gives a consistent score set; no active-skill filter.
        $rows = $plan->assessments()->with('skill')->orderBy('skill_id')->get();
        $baselines = $rows->where('type', AssessmentType::Baseline)->keyBy('skill_id');
        $finals = $rows->where('type', AssessmentType::Final)->keyBy('skill_id');
        if ($finals->keys()->diff($baselines->keys())->isNotEmpty()) {
            throw new ProgressionConflict('Recorded finals contain a skill without a baseline.');
        }

        return $baselines->map(function ($baseline) use ($finals) {
            $final = $finals->get($baseline->skill_id);

            return [
                'skill_id' => $baseline->skill_id,
                'skill_name' => $baseline->skill->name,
                'is_active' => $baseline->skill->is_active,
                'baseline_score' => $baseline->score,
                'final_score' => $final?->score,
                'delta' => $final ? $this->delta($baseline->score, $final->score) : null,
            ];
        })->values()->all();
    }

    /** Stored DECIMAL casts are fixed two-place strings; subtract integer hundredths. */
    private function delta(string $baseline, string $final): string
    {
        $difference = (int) str_replace('.', '', $final) - (int) str_replace('.', '', $baseline);
        $absolute = abs($difference);

        return ($difference < 0 ? '-' : '').intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    private function requireState(DevelopmentPlan $plan, PlanStatus $expected): void
    {
        if ($plan->status !== $expected) {
            throw new ProgressionConflict("This operation requires a {$expected->value} plan.");
        }
    }
}
