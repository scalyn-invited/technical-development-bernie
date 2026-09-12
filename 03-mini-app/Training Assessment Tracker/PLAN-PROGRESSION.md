# Plan activation and completion

Plan membership is defined by baseline assessments, not the entire skill catalogue.
Use baselineAssessments() for membership; skills() includes both assessment types.

## API

All calls require a Sanctum bearer token. Only an administrator acting on another
user's plan can activate or complete it.

- POST /api/plans/{plan}/activate — no body; 200 with a Plan Resource.
- POST /api/plans/{plan}/complete — body below; 200 with a Plan Resource.

```json
{
  "finals": [
    {"skill_id": 1, "score": 85, "note": "Final practical assessment"},
    {"skill_id": 2, "score": 90}
  ]
}
```

Replace the example IDs with exactly the skill IDs from that plan's baseline
assessments. A missing, extra, or repeated skill cannot complete a plan.
All existing weeks must be closed. No minimum number of weeks is imposed here.

Activation rejects empty baselines, out-of-range scores, non-draft plans and
legacy draft plans containing finals. It freezes membership through the API:
baseline creation and correction are draft-only; there is no assessment delete endpoint.
Direct SQL and Eloquent writes outside this service are not protected by these
application rules.

Individual final writes through POST /assessments now return 409. Submit the
entire final set through /complete. Existing partial final records block completion
and require explicit data reconciliation; the service does not silently overwrite them.

State conflicts return 409 with code progression_conflict and the existing
four-field envelope. Malformed payloads return 422; permission denials return 403.

Assessment writes, activation, completion and weekly writes use a transaction and
the same parent-plan lock. MySQL uses row locking; SQLite does not implement
SELECT FOR UPDATE and may return a database-busy error under contention. Real
parallel-request behavior still needs verification on the deployment engine.

## Verification

Run php artisan test. Regression tests use an in-memory SQLite database and cover
activation, membership freezing, permissions, exact final sets, closed-week
prerequisites, repeat transitions and rollback after a simulated second-insert failure.

## Remaining rules implemented

- PATCH /api/plans/{plan}/assessments/{assessment}: correct score/note on a draft
  baseline. Skill and type cannot change. Nested IDs are scoped to the plan.
- POST /api/plans/{plan}/weeks: active plans only, starting at week 1 and then the
  next contiguous number after every earlier week is closed. The focus skill must
  be active and have a baseline on the plan.
- PATCH /api/plans/{plan}/weeks/{weekly_entry}: planned → evidenced → closed.
  No skips or reversals. Same-state edits are allowed before closure. Evidenced
  requires nonblank evidence; closing also requires an outcome score (zero is valid).
  Closed weeks are read-only. Skill and week number cannot change.
- PATCH /api/skills/{skill}: deactivation is refused while ANY plan has an open
  week focused on the skill. New baselines also require an active skill.
- GET /api/plans/{plan}/comparison: administrator or owning member; data is an
  array of skill_id, skill_name, is_active, baseline_score, final_score, delta.
  Missing finals return null for final_score/delta. Delta is final minus baseline
  in percentage points, formatted as a two-place string, calculated using integer
  hundredths. No stored delta and no percentage-growth division.

Deactivated skills remain in historical assessment/comparison responses. An
orphan final in legacy data returns a conflict instead of being silently omitted.

Evidence/outcome validation merges only supplied fields with the row reloaded
inside the transaction. This replaces the previous Form Request required_if merge,
which could carry stale data from route binding into a later locked write.

Skill deactivation and new week/baseline creation share a skill lock; plan writes
share the parent-plan lock. Concurrent engine-specific checks remain a deployment
task; SQLite feature tests do not prove MySQL locking behavior.

## Fresh manual fixture

Avoid seeded plans previously used for invalid-write exercises. In local Tinker,
create a fresh fixture and print its IDs (this does not reset existing data):

```php
$p = App\Models\DevelopmentPlan::factory()->create();
$a = App\Models\Assessment::factory()->create(['development_plan_id' => $p->id, 'score' => 50]);
dump(['plan_id' => $p->id, 'assessment_id' => $a->id, 'skill_id' => $a->skill_id]);
```

Use an administrator token belonging to someone other than the fixture's member.
Set those three IDs in the Postman collection. Run manually in numbered order.
The collection captures week_id on creation. Completion is irreversible through
the API; create another fixture to repeat the happy path. No fixture was created
in your database while implementing this change.
