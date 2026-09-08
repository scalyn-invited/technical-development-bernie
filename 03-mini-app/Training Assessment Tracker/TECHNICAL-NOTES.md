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
| `evidence` | text, nullable | `required_if:status,closed` (Day 12) |
| `outcome_score` | decimal(5,2), nullable | `required_if:status,closed` (Day 12) |
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
7. **Score range at the database level.** `decimal(5,2)` accepts up to 999.99. The 0–100 rule lives in validation only (Day 12), so a direct database write or a seeder could store a score the API would reject.
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

*Sections for the permission matrix (Day 11), the endpoint table (Day 12) and the business rules (Day 13) follow as they are built.*
