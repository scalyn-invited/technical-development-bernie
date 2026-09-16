# Training Assessment Tracker — Technical Notes

**Programme:** 30-Day Technical Development & Team Leadership Program (Scalyn OPC)
**Author:** Bernie Santos A. Cabase
**Concept approved by management:** 7 September 2026, after two rounds of proposal feedback.
**Scope:** a single programme cycle. Not a long-term HR system.

---

## 1. The approved workflow

```
Team Member → Skills → Baseline Assessment → Development Plan
            → Weekly Progress / Evidence → Final Assessment Comparison
```

Nothing outside this line is in the MVP. Explicitly excluded by management and deliberately absent from the schema: performance ratings, promotion workflows, course catalogues, competency frameworks, career-path mapping, team-wide analytics, role-based skill requirements, automated notifications, and integration with other HR systems.

---

## 2. Schema (Day 10)

Four tables beyond the shipped `users`.

### `users` *(extended)*

| Column | Type | Notes |
|---|---|---|
| `role` | enum(`administrator`,`member`), default `member`, **indexed** | Two fixed roles for one cycle. |

**Why no `roles` table.** A roles table earns its place when roles are data — added, renamed, or granted permissions at runtime. Here they are two branches in a Policy, fixed for the length of one cycle. A join table would add a query to every authorisation check and buy nothing.

### `skills`

| Column | Type | Notes |
|---|---|---|
| `name` | string, **unique** | Prevents two administrators creating "Testing" twice. |
| `description` | text, nullable | |
| `is_active` | boolean, default true, **indexed** | Every skill picker filters on it. |
| `created_by` | FK → `users`, **nullOnDelete** | |

**Skills are retired by deactivation, never deletion.** A deleted skill would orphan every score recorded against it, which is the one thing the tool exists to preserve. `is_active = false` hides it from new selections and changes nothing already recorded.

### `development_plans`

| Column | Type | Notes |
|---|---|---|
| `user_id` | FK → `users`, **cascadeOnDelete**, **unique** | One plan per member per cycle. |
| `key_gaps`, `weekly_focus` | text | |
| `status` | enum(`draft`,`active`,`completed`), default `draft`, **indexed** | |
| `activated_at`, `completed_at` | timestamp, nullable | |
| `created_by` | FK → `users`, **nullOnDelete** | |

### `assessments`

| Column | Type | Notes |
|---|---|---|
| `development_plan_id` | FK, **cascadeOnDelete** | |
| `skill_id` | FK, **restrictOnDelete** | |
| `type` | enum(`baseline`,`final`) | |
| `score` | decimal(5,2) | Range 0–100 enforced in validation, not the database — see §5. |
| `note` | text, nullable | |
| `recorded_by` | FK → `users`, **nullOnDelete** | |
| `recorded_at` | timestamp | |

- **unique(`development_plan_id`, `skill_id`, `type`)** — the constraint the improvement delta rests on. One baseline and one final per skill per plan, enforced by the database and not only by the service layer.
- **index(`development_plan_id`, `type`)** — the comparison read fetches all baselines, then all finals, for one plan.

### `weekly_entries`

| Column | Type | Notes |
|---|---|---|
| `development_plan_id` | FK, **cascadeOnDelete** | |
| `week_number` | unsignedTinyInteger | |
| `skill_id` | FK, **restrictOnDelete** | |
| `objective` | text | |
| `evidence` | text, nullable | Required on closed weeks; validated against locked state |
| `outcome_score` | decimal(5,2), nullable | Required on closed weeks; validated against locked state |
| `status` | enum(`planned`,`evidenced`,`closed`), default `planned` | |
| `recorded_by` | FK → `users`, **nullOnDelete** | |
| `closed_at` | timestamp, nullable | |

- **unique(`development_plan_id`, `week_number`)** — one entry per week per plan.
- **index(`status`, `week_number`)** — the open-weeks queue filters on both together, so a composite index serves it in one pass.

---

## 3. Foreign-key behaviour, and why each was chosen

| Foreign key | Behaviour | Reasoning |
|---|---|---|
| `development_plans.user_id` → users | `cascadeOnDelete` | A plan describes one member. Without the member it is an orphan record about nobody. |
| `assessments.development_plan_id` → plans | `cascadeOnDelete` | A score is a fact *about a plan*. It has no standalone meaning. |
| `weekly_entries.development_plan_id` → plans | `cascadeOnDelete` | Same reasoning. |
| `assessments.skill_id` → skills | **`restrictOnDelete`** | The database must refuse to delete a skill carrying recorded scores. This is what makes "deactivate, never delete" an enforced rule rather than a convention. |
| `weekly_entries.skill_id` → skills | **`restrictOnDelete`** | Same reasoning. |
| `skills.created_by` → users | `nullOnDelete` | Authorship is provenance, not ownership. Losing the author must not lose the skill. |
| `development_plans.created_by` → users | `nullOnDelete` | Same. |
| `assessments.recorded_by` → users | `nullOnDelete` | Who recorded a score is audit metadata. The score survives the assessor. |
| `weekly_entries.recorded_by` → users | `nullOnDelete` | Same. |

The distinction throughout: **`cascade` where the child cannot exist without the parent, `restrict` where deleting the parent would destroy evidence, `set null` where the parent is provenance rather than a component.**

---

## 4. Relationships

| Model | Relationship | Target |
|---|---|---|
| `User` | `hasOne` | `DevelopmentPlan` |
| `User` | `hasMany` | `createdSkills`, `recordedAssessments`, `recordedWeeklyEntries` |
| `Skill` | `belongsTo` | `creator` (User) |
| `Skill` | `hasMany` | `Assessment`, `WeeklyEntry` |
| `DevelopmentPlan` | `belongsTo` | `member` (User), `creator` (User) |
| `DevelopmentPlan` | `hasMany` | `Assessment`, `WeeklyEntry` |
| `DevelopmentPlan` | **`belongsToMany`** | `Skill` **through `assessments`**, `withPivot('type','score','note','recorded_at')` |
| `Assessment` | `belongsTo` | `DevelopmentPlan`, `Skill`, `recorder` (User) |
| `WeeklyEntry` | `belongsTo` | `DevelopmentPlan`, `Skill`, `recorder` (User) |

`assessments` is a real entity, not a join table — it carries a score, a note, a recorder and a timestamp of its own, and it is addressed directly by the API. The `belongsToMany` is a convenience over the same rows for the comparison read, not the primary way the table is written.

**Scopes and casts:** `Skill::active()`, `DevelopmentPlan::status()`, `Assessment::ofType()`, `WeeklyEntry::open()`. All four status/type columns cast to PHP backed enums (`app/Enums`), so an invalid state cannot be assigned silently. Scores cast `decimal:2` — they stay strings in PHP, so no float rounding can move a recorded percentage before it reaches the delta.

---

## 5. Known limits — what cannot be represented without a migration

Written down now rather than discovered in Week 4.

1. **A second programme cycle.** `development_plans.user_id` is unique, so a member can never hold two plans. A second cycle needs that index dropped and a `cycle_id` added. Deliberate: management scoped storage to one cycle.
2. **A skill on a plan that has not been baselined yet.** Plan membership is *inferred* from the existence of a baseline row; there is no `plan_skills` table declaring the intended skill set. So "planned but not yet scored" cannot be expressed, and removing a skill from a plan means deleting its baseline. **This is the one thing deliberately left out to keep scope small** — it removes a whole table, an endpoint and a state, and the workflow as approved never needs it.
3. **A midpoint assessment.** `type` is enum(`baseline`,`final`). A third value is an enum migration, which on SQLite means a table rebuild.
4. **Two focus skills in one week.** `unique(plan, week_number)` permits exactly one entry per week.
5. **Score history.** Baseline corrections in draft overwrite in place. There is no audit row, so "what did this baseline say before it was corrected?" is unanswerable. Acceptable while baselines are immutable after activation.
6. **A departed member's record.** `cascadeOnDelete` on `user_id` means deleting a user erases their plan, scores and weekly entries outright. There are no soft deletes anywhere in the schema. For one cycle this is correct; for anything retained it would be data loss.
7. **Score range at the database level.** `decimal(5,2)` accepts up to 999.99. The 0–100 rule lives in validation only (§8), so a direct database write or a seeder could store a score the API would reject.
8. **Structured gap analysis.** `key_gaps` and `weekly_focus` are free text, so "how many plans name testing as a gap" is not a query.

---

## 6. Verification performed (Day 10)

- `php artisan migrate:fresh --seed` — clean.
- `php artisan migrate:rollback --step=5` then `migrate` — clean in both directions, including dropping the added `users.role` enum column on SQLite.
- Every relationship listed in §4 traversed and printed against seeded data.
- `unique(plan, skill, type)` — proven by a rejected duplicate baseline.
- `restrictOnDelete` — proven by a refused deletion of an assessed skill.
- `cascadeOnDelete` — proven by deleting a member and confirming the plan and its three assessments went with them.

**Seeded dataset:** 1 administrator, 3 members, 6 active skills + 1 deactivated, 3 plans at the three stages (draft with an incomplete baseline set, active with weeks 1–2 closed and week 3 open, completed with baselines, finals and all weeks closed), 21 assessments, 9 weekly entries.

---

## 7. Current authentication and permission matrix

Sanctum bearer tokens authenticate API calls. Registration always creates a member.
Logout revokes the calling token. Login is limited by email + IP; registration by IP.
Policies enforce role and ownership; ProgrammeProgressionService enforces state and
transaction rules. Administrators cannot score or progress their own plans.

| Action | Administrator | Member |
|---|---|---|
| Read skills | Yes | Yes |
| Create/update/deactivate skills | Yes, subject to open-week guard | No |
| List/read plans and comparison | Any plan | Own plan only |
| Activate/complete plan | Other member's plan only | No |
| Record/correct baseline | Other member's plan only; draft | No |
| Create/update/close week | Other member's plan only; active | No |
| Delete skills/assessments/plans/weeks | No exposed endpoint | No exposed endpoint |

A create-plan policy exists, but no create-plan HTTP endpoint is implemented;
seeders or a controlled local fixture currently create plans.

## 8. Current endpoint table

All paths below start with /api. Protected requests use Authorization: Bearer TOKEN
and Accept: application/json. Errors for recognized paths use
{error, code, message, details}. Authorization precedes input validation through
Form Requests or explicit Gate calls in the service/controller.

| Method | Path | Access | Success |
|---|---|---|---|
| POST | /register | Public, throttled | 201 + token |
| POST | /login | Public, throttled | 200 + token |
| POST | /logout | Authenticated | 200 |
| GET | /me | Authenticated | 200 |
| GET | /skills | Authenticated; active filter, pagination | 200 |
| POST | /skills | Administrator | 201 |
| PATCH | /skills/{skill} | Administrator | 200 |
| GET | /plans | Authenticated; scoped list, status filter | 200 |
| GET | /plans/{plan} | Read policy | 200 |
| POST | /plans/{plan}/assessments | Integrity rule; draft baseline only | 201 |
| PATCH | /plans/{plan}/assessments/{assessment} | Integrity rule; draft baseline correction | 200 |
| POST | /plans/{plan}/activate | Integrity rule | 200 |
| POST | /plans/{plan}/complete | Integrity rule; entire final set | 200 |
| GET | /plans/{plan}/comparison | Read policy | 200 |
| POST | /plans/{plan}/weeks | Integrity rule | 201 |
| PATCH | /plans/{plan}/weeks/{weekly_entry} | Integrity rule | 200 |

Nested assessment/week routes use scopeBindings: a child belonging to another plan
is 404. Scores and outcome scores are 0–100. Query filters and pagination are
validated; per_page is limited to 100. The response for comparison is plain JSON
with data and summary; other domain responses use Resources.

| Error code | HTTP status |
|---|---|
| unauthenticated / invalid_credentials | 401 |
| forbidden | 403 |
| not_found | 404 |
| method_not_allowed | 405 |
| validation_failed | 422 |
| too_many_requests | 429 |
| conflict (database uniqueness) | 409 |
| progression_conflict (domain rule) | 409 |

On conflict, re-read current state and reconcile intent before retrying. A blind
retry does not repair duplicate records or an invalid transition.

Unexpected API/JSON exceptions now return HTTP 500 with code internal_error,
message "An unexpected error occurred." and empty details. This holds with debug
enabled or disabled, and SQL, bindings and exception messages are not returned.
Laravel's server-side exception reporting remains enabled. HTTP exceptions retain
their status and protocol headers (including Allow and Retry-After); unlisted
statuses use a safe standard status message. Normal HTML requests retain Laravel's
HTML renderer. Do not expose APP_DEBUG=true outside private development.

## 9. Business rules and architecture (Days 13–14)

ProgrammeProgressionService owns transitions and writes. Controllers accept requests
and shape responses; policies decide whether the caller may act. The service
reloads and locks the plan inside a transaction so decisions use current state.

- Plans only move draft → active → completed, and never reopen.
- Baseline rows define membership. Activation needs a nonempty valid baseline set
  and no existing finals. There is no independent assignment list against which
  an omitted intended skill can be detected. This is the deliberate interpretation
  of the Day 14 “missing baseline” case: empty sets fail, and activation confirms
  the chosen baseline set.
- New baselines require active catalogue skills. Corrections change score/note
  only and are permitted in draft. Active/completed baselines are frozen.
- Weeks may be created on active plans only. Numbers start at 1, are contiguous,
  and all preceding weeks must be closed. Focus skills must be active and baselined
  on the plan. Week number and focus cannot be edited afterward.
- Weeks move planned → evidenced → closed. Same-state edits are allowed before
  closure. Evidenced requires nonblank evidence; closed also requires an outcome
  score (zero is valid). Closed weeks reject every edit.
- Evidence/outcome checks merge submitted fields with the freshly locked row.
  The previous pre-validation merge was removed to avoid overwriting newer values
  with stale route-binding data.
- Deactivation is rejected while any plan has an open week using the skill.
  Deactivated skills remain in assessment and comparison responses.
- Completion requires exactly the baseline skill IDs, no duplicates, valid finals,
  no pre-existing finals, and all existing weeks closed. It inserts the final batch
  and sets completed/completed_at in one transaction. A failed insert rolls it all back.
- There is currently no minimum week count; zero existing weeks passes the
  all-weeks-closed condition. Direct SQL bypasses these application guards.

Plan writes share a parent-plan lock; skill deactivation/new baseline/new week
creation share a skill lock. SQLite does not implement SELECT FOR UPDATE. Tests on
SQLite do not establish MySQL concurrency behavior; verify on the deployment engine.

## 10. Comparison calculator and average movement

ComparisonCalculator is a pure PHP class, tested with PHPUnit Framework TestCase:
no Laravel application bootstrap, models, factories or database. The service loads
the scores and labels, then delegates arithmetic to this class.

Scores arrive as fixed two-place decimal strings from Eloquent. Calculations use
integer hundredths and results are strings. A skill's delta is final minus baseline,
in percentage points; negative and zero movements are retained. An unrecorded final
produces null, not zero. An orphan final is rejected by the service.

Average movement is the arithmetic mean of deltas for **matched baseline/final
pairs only**, rounded once to two places, half away from zero. It is a within-plan
summary, not team analytics. Compared/pending counts expose the denominator so a
partial comparison is not mistaken for a completed assessment. With no finals the
average is null. No delta or average is stored in the database.

Example: 60.25 → 85.10 gives +24.85; 80.00 → 70.00 gives -10.00. A third
baseline without a final is pending. Average = (24.85 - 10.00) / 2 = 7.43.

GET /plans/{plan}/comparison keeps its existing data array and adds:

```json
{
  "summary": {
    "compared_skills": 2,
    "pending_skills": 1,
    "average_movement": "7.43"
  }
}
```

## 11. Run locally from a fresh checkout

Requirements: PHP 8.2+ with the Laravel-required extensions including PDO SQLite,
Composer, and Git. Node/npm are only needed for the optional Vite asset build;
the API and PHPUnit suite do not require a frontend build.

From the repository root:

```powershell
Set-Location '03-mini-app/Training Assessment Tracker'
composer install
```

For a **new checkout only**, copy .env.example to .env if .env does not already
exist, then run php artisan key:generate. Keep DB_CONNECTION=sqlite. Create an
empty database/database.sqlite file if absent. Do not overwrite an existing
environment or database. Then:

```powershell
php artisan migrate
php artisan db:seed
php artisan serve --host=127.0.0.1 --port=8000
```

Run db:seed only against a fresh disposable database; the seeder is not idempotent.
Never run migrate:fresh on data you intend to keep. The seed administrator is
admin@example.test with password password (local fixtures only). Login returns
the bearer token. Use http://127.0.0.1:8000/api as the Postman collection base_url,
and avoid an environment overriding collection variables.

For optional assets: npm ci then npm run build. No completed browser UI exists yet.

## 12. Tests, evidence, and Git history

```powershell
php artisan test
php artisan test --testsuite=Unit
php vendor/bin/pint --test
```

phpunit.xml uses in-memory SQLite. Day 9 patterns retained: explicit authenticated
requests, RefreshDatabase for feature tests, assertions on persisted state after
denials, and database-free PHPUnit tests for isolated logic.

Coverage map:
- PlanProgressionTest: activation and exact finals, role checks, rollback after
  second insert failure, forbidden repeat transitions and standalone finals.
- RemainingProgressionTest: full state cycle, immutable records, week ordering,
  focus/deactivation, scoped corrections, and per-skill comparisons.
- Day14CoverageTest: draft→completed rejection, score-write integrity, an HTTP
  cycle starting with baseline creation, and API average/denominator integration.
- ComparisonCalculatorTest: deltas, matched-pair averages, missing finals, empty
  input, 0/100 bounds, positive/negative/zero movement and rounding.

Day 13 evidence was 15 tests / 97 assertions and learner-reported 13 Postman
requests passed. Day 14 adds automated coverage; see its evidence log for the final
run rather than treating older counts as current.

Git review: recent feature/fix/docs/chore commits are meaningful and conventional.
Historical bootstrap commits f1097e3 and 658da88 use “Initialize ...” subjects.
They are already published; they were not rebased or force-pushed solely to rename
them. Record these two exceptions rather than claiming every historical commit
follows Conventional Commits.

## 13. Deliberate exclusions and remaining work

Schema limitations remain in §5. No independent skill assignment, cycle history,
score audit trail, deletion API, plan-creation API or completed frontend was added.
Policies do not prevent privileged database access. Deployment-engine concurrency
still requires verification/follow-up. ApiErrorEnvelopeTest verifies the generic
500 response, retained reporting, SQL redaction, HTTP headers and HTML behavior.
## Full-cycle UI/API completion — 15 September 2026

- POST /api/plans: administrator only, 201. Fields: user_id, key_gaps,
  weekly_focus, baselines[{skill_id,score,note?}]. Nonempty distinct active
  skills; valid scores; existing member without a plan. Status and creator
  cannot be supplied by the caller.
- GET /api/members/eligible: administrator only, paginated 20, optional name
  search/page. Returns only id/name for unassigned members.
- Creation locks the member and writes plan/baselines in one transaction.
  The existing unique user_id constraint remains the final duplicate guard.
- Baseline rows continue to define membership; no new schema or migration.
- Vue now exposes draft creation, baseline additions/corrections, activation,
  sequential weeks, atomic final completion and member comparison.
- Public registration continues to force the member role; no administrator
  registration or privilege-changing UI was added.
