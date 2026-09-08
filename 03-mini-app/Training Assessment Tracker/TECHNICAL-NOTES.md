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
| `evidence` | text, nullable | `required_if:status,closed` — see §8 |
| `outcome_score` | decimal(5,2), nullable | `required_if:status,closed` — see §8 |
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

## 7. Authentication and permissions (Day 11)

### Authentication

Laravel Sanctum, API tokens. Four endpoints:

| Method | Path | Auth | Success |
|---|---|---|---|
| `POST` | `/api/register` | public, throttled 5/min by IP | `201` + token |
| `POST` | `/api/login` | public, throttled 5/min by **email + IP** | `200` + token |
| `POST` | `/api/logout` | token | `200` |
| `GET` | `/api/me` | token | `200` |

Three decisions worth stating:

- **`register` does not accept a `role`.** The Day 8 version took an optional `role` field, which put the entire authorisation model one payload field away from being bypassed. Roles are seeded; registration always produces a `member`.
- **Login failure is identical for an unknown email and a wrong password**, so the endpoint cannot be used to enumerate accounts.
- **Login throttling is keyed on email *and* IP, not IP alone.** A plain `throttle:5,1` counts every attempt from an address, successes included. This is an internal tool whose users share one office IP, so IP-only keying means one person guessing at their own password locks out the team. The named `login` limiter lives in `AppServiceProvider`.

**Logout revokes only the token that made the request.** Day 8 left open why Sanctum keeps earlier tokens alive on re-login; the decision taken here is that signing out on one device must not sign the user out everywhere.

### The permission matrix

Two roles, fixed for the cycle. Evaluated through the real `Gate` against seeded users — see `04-logs/evidence-logs/2026-09-08-day-11.md`.

| Action | administrator | member |
|---|---|---|
| View the skill catalogue | allow | allow |
| Create a skill | allow | **deny** |
| Rename or deactivate a skill | allow | **deny** |
| **Delete a skill** | **deny** | **deny** |
| List plans | allow | allow *(scoped to their own — see below)* |
| Read own plan | allow | allow |
| Read another member's plan | allow | **deny** |
| Create a plan | allow | **deny** |
| Update another member's plan | allow | **deny** |
| View own comparison | allow | allow |
| Record a score | allow | **deny** |
| Amend a score | allow | **deny** |
| Log a week | allow | **deny** |
| Close a week | allow | **deny** |

Two rows carry reasoning that is not obvious from the table:

- **Nobody deletes a skill, including an administrator.** `restrictOnDelete` already refuses this at the database level; the Policy states the same rule at the authorisation level so the refusal arrives as a 403 rather than a database error surfacing as a 500.
- **`viewAny` on plans is `allow` for both roles.** The member's *list* is narrowed by the query (§8), not by the Policy. Returning 403 to "show me what I am allowed to see" is the wrong answer; an empty or single-row list is the right one.

### The integrity rule

> **Nobody may record, amend or close any assessment or weekly entry on their own development plan — administrator included.**

Self-scoring does not merely look bad; it makes the improvement delta self-reported, which is the one number the tool exists to produce. It is therefore a Policy denial returning 403, not an omission from the UI, because the UI is not the boundary.

Implemented as a second condition on every write policy: `$user->isAdministrator() && $user->id !== $plan->user_id`. Verified against a full administrator holding a plan of their own — all five own-plan actions denied, while the same administrator acting on another member's plan is allowed, so the denial is the rule rather than a broken policy.

`WeeklyEntry` writes are governed by the same rule as scores. A weekly entry carries an `outcome_score`, so it is a scoring action even though it reads as progress tracking; treating it as anything softer would leave a hole straight through the rule.

### What is authorisation and what is not

The Policies answer **"is this your call?"**. They deliberately do not answer **"is this the right moment?"** — baseline immutability after activation, contiguous week numbers, a final requiring a matching baseline. Those are state-machine rules and belong to `ProgrammeProgressionService` on Day 13. Keeping them apart means a 403 always means *not your call* and never *wrong moment*, which is the difference between an error a user can act on and one they cannot.

### The error envelope

The Day 7 shape, carried forward unchanged and now used by every failure path:

```json
{ "error": true, "code": "...", "message": "...", "details": [] }
```

| Code | Status | Raised by |
|---|---|---|
| `unauthenticated` | 401 | no token, or a revoked or malformed one |
| `invalid_credentials` | 401 | login rejected |
| `forbidden` | 403 | any Policy denial |
| `validation_failed` | 422 | Form Request or inline validation |
| `not_found` | 404 | unresolved route-model binding |
| `method_not_allowed` | 405 | wrong verb |
| `too_many_requests` | 429 | throttled |
| `conflict` | 409 | a unique constraint reached at the database — see §8 |

`invalid_credentials` is separate from `unauthenticated` on purpose. Day 8 signalled a rejected login with `ValidationException::withMessages()->status(401)`, which produced `code: "validation_failed"` alongside HTTP 401 — the shape was right and the meaning was wrong. The frontend needs to tell an expired session from a mistyped password (Day 15), and `code` is the field it will key on.

**An unauthorised read returns 403, not 404.** Hiding the existence of another member's plan is not a threat this tool defends against, and a 404 there would make a real bug indistinguishable from a permission denial.

---

## 8. The API (Day 12)

### The endpoint table

Twelve routes in total: the four authentication endpoints in §7, and the eight below. Every response is shaped by an API Resource; every input is validated by a Form Request; every failure returns the §7 envelope.

| Method | Path | Auth | Success | Notes |
|---|---|---|---|---|
| `GET` | `/api/skills` | token | `200` | `?active=` filter, paginated |
| `POST` | `/api/skills` | token · administrator | `201` | |
| `PATCH` | `/api/skills/{skill}` | token · administrator | `200` | rename or deactivate |
| `GET` | `/api/plans` | token | `200` | `?status=` filter, paginated; **a member sees only their own** |
| `GET` | `/api/plans/{plan}` | token · policy | `200` | plan with assessments and weekly entries |
| `POST` | `/api/plans/{plan}/assessments` | token · **integrity rule** | `201` | baseline or final |
| `POST` | `/api/plans/{plan}/weeks` | token · **integrity rule** | `201` | always created `planned` |
| `PATCH` | `/api/plans/{plan}/weeks/{week}` | token · **integrity rule** | `200` | evidence, outcome, close |

There is **no `DELETE` anywhere**. Skills retire by deactivation, and plans, assessments and weekly entries are not deletable through the API at all — so `DELETE` returns `405` from an unmatched route rather than `403` from a route that should not exist.

There is also **no `POST /plans`**. Plans are created by the administrator outside these eight endpoints for this cycle; adding the endpoint would be four lines and no new concepts, and it was left out to keep the day's scope to the card.

### Where authorisation is decided, and why it is not in the controller

Every write endpoint decides authorisation in `FormRequest::authorize()`, not in the controller body. This is an ordering decision, not a tidiness one.

Laravel's `validateResolved()` runs `prepareForValidation()`, then `authorize()`, then the rules. A `Gate::authorize()` call left in the controller body therefore runs *after* validation, so a member posting a malformed score is told `422` — and a `422` describes the shape of a payload the caller may never send. Moving the check into `authorize()` makes it `403`, which is the only thing that caller is entitled to learn.

The two reads with no input to validate (`plans.show`) keep `Gate::authorize()` in the controller, because there is no Form Request to put it in.

### Validation decisions

| Decision | Reasoning |
|---|---|
| **Query strings get Form Requests too** | `?status=archived` is `422` naming the parameter. A filter that fails quietly is worse than one that fails loudly, because the caller believes the answer. |
| **`?active=true` is normalised before validation** | Laravel's `boolean` rule accepts `true, false, 1, 0, "1", "0"` and **rejects the strings `"true"` and `"false"`** — it is written for form posts, where a browser sends 1 or 0. A query string is not a form post. `prepareForValidation()` normalises with `filter_var`, so all four spellings work and `?active=maybe` is still a `422`. JSON bodies need none of this: `{"is_active": false}` carries a real boolean. |
| **`per_page` is bounded to 1–100** | Unbounded, one request asking for 100000 rows loads the table into memory and hands pagination back to nobody. |
| **`score` and `outcome_score` are `between:0,100`** | This is the **only** place the range exists. The column is `decimal(5,2)`, which accepts 999.99 — see §5.7. |
| **`week_number` is `between:1,255`** | Matched to the `unsignedTinyInteger` column exactly. Left at `integer`, week 300 passes validation and the database is asked to hold a value the column cannot. |
| **`POST /weeks` does not accept `status`** | A week is always created `planned`. Accepting it would let a caller POST an already-closed week, skipping the transition and every rule attached to it — the same bug class as Day 8's `register()` accepting a `role`. |
| **`created_by`, `recorded_by`, `recorded_at`, `closed_at` are never accepted from the client** | They come from the token and the clock. A score whose author the client can nominate is not audit metadata. |
| **The composite `unique(plan, skill, type)` is restated as a validation rule** | The Day 10 constraint exists at the database. Restating it in the Form Request is what turns the second identical baseline into a `422` the caller can act on instead of a `500`. |
| **Enums validate with `Rule::enum`** | Against the same backed enum the column casts to, so the set of valid inputs cannot drift from the set of valid states. |

### The conditional rule, and the correction it needed

The card prescribes `evidence` and `outcome_score` as `required_if:status,closed` — a week may be saved as a draft objective, but may not be closed empty.

Implemented literally, that rule refuses a correct request. `required_if` inspects the **payload**; the rule it implements is about the **row**. Those coincide only when the caller closes a week in one request. The natural two-step — PATCH the evidence and outcome, then PATCH `{"status":"closed"}` — was rejected `422` for missing fields the row already held.

`UpdateWeeklyEntryRequest::prepareForValidation()` therefore merges the persisted `evidence` and `outcome_score` into the input when the request omits them, so `required_if` is applied to the state the row will actually be in after the write. `rules()` keeps the prescribed rule verbatim, and a week with neither field recorded is still refused — naming both fields.

`required_if` is an **implicit** rule, so it still fires on a field marked `nullable`: sending `"evidence": null` with `"status": "closed"` is refused. That is the case that matters and the one a plain `nullable` would let through.

### Nested routes are scoped

`PATCH /plans/{plan}/weeks/{week}` sits behind `->scopeBindings()`.

Unscoped, `PATCH /plans/1/weeks/10` where week 10 belongs to plan 2 returns **200 and writes to plan 2's row**, having authorised the request against plan 1. That is the integrity rule defeated by a URL: an administrator barred from touching their own plan could edit it by addressing the request through somebody else's. Scoped, the binding fails and the request is a `404`. Measured both ways — evidence log §6.

The child parameter is named `{weekly_entry}` rather than `{week}` because Laravel resolves the parent relationship as `Str::plural(Str::camel($childType))`, so `{week}` would look for a `weeks()` relation that does not exist. The URL segment is `/weeks/{id}` either way — a route parameter's name never appears in the URL.

### `409` and `422` are not the same answer

Both mean "that record already exists". They differ in what the caller should do next.

A `unique` validation rule is check-then-write: a `SELECT`, then later an `INSERT`, with a window in between. Two administrators working the open-weeks queue at the same moment both pass validation, and the loser reaches the database constraint. Before this was handled, that arrived as a **`500` carrying the SQL, the database file path and a full stack trace**.

- **`422 validation_failed`** — the ordinary duplicate. The payload is wrong and the caller must change it.
- **`409 conflict`** — the race. The payload was not wrong; it lost. The caller should retry, not edit.

`Illuminate\Database\UniqueConstraintViolationException` is the arm that catches the second.

**Known limit.** It is the *only* constraint violation Laravel narrows into its own class. A foreign-key violation — the `restrictOnDelete` on `assessments.skill_id`, for instance — is still a bare `QueryException` and would leave the envelope as a `500`. No endpoint can reach it today because nothing deletes, but it is an open hole in the claim that every failure path uses the envelope.

### What the API does **not** enforce

Day 12 answers three questions and no more: *is this payload well-formed*, *is this your call*, and *what does the response look like*. Every rule about **when** something may happen belongs to `ProgrammeProgressionService` on Day 13, and none of it is implemented here:

- plan transitions, and a completed plan never reopening
- activation requiring a baseline for every skill on the plan
- baseline immutability once the plan is active
- weekly entry transitions, and a closed week being read-only
- contiguous week numbers
- a `final` requiring a matching `baseline`
- writing the final set and completing the plan in one transaction
- refusing to deactivate a skill that is the focus of an open week

Two of these are visible in today's evidence behaving in ways Day 13 must stop: a `final` was accepted on a plan with no matching baseline, and a closed week is still editable. They are recorded rather than hidden.

Keeping the two apart is what makes a `403` mean *not your call* and a `422` mean *not this payload* — never *not yet*. An error a user cannot act on is worse than no error at all.

### Measured cost

Queries per request, measured through the real kernel with authentication warmed up:

| Endpoint | Queries |
|---|---|
| `GET /api/skills` | 2 |
| `GET /api/plans` | 3, whether it returns one plan or four |
| `GET /api/plans/{plan}` | 5–6, whether the plan holds 3 assessments or 12 |

The plan read traverses the same rows lazily in **22 queries**. Eager loading in the controller and `whenLoaded()` in the Resources is what holds it at 6, and the Resource guard is the half that matters: a relation the controller forgets to load comes back as an absent key rather than as a query repeated once per row.

---

*Section for the business rules (Day 13) follows as it is built.*
