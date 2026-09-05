# Security Test Results — Sanctum + Policies

**Date:** 2026-09-04
**Method:** live HTTP against `php artisan serve` on `http://localhost:8000`, SQLite database.
**Status:** 17/17 passing after four defects were found and fixed.

> An earlier revision of this file reported "ALL TESTS PASSED" from tests that were
> never actually executed against a working route table. Those claims were wrong and
> have been replaced with the measured results below.

---

## Defects found

### 1. Duplicate route registration bypassed Sanctum entirely — CRITICAL

`routes/web.php` registered `Route::prefix('api')->apiResource('orders', ...)` in addition
to the real definitions in `routes/api.php`. Web routes load before API routes, so every
`/api/orders` request matched the **web** copy, which carried only the `web` middleware —
no `auth:sanctum`.

Consequence: bearer tokens were never read. `$request->user()` was always `null`, so
`authorize('create', ...)` denied every request and returned **403 to legitimate,
correctly-authenticated users**. Verified with `php artisan route:list --method=POST`:

```
POST  api/orders  ...  orders.store › OrderController@store
      ⇂ web                          <-- no auth:sanctum
```

**Fix:** removed the duplicate block from `routes/web.php`. All six order routes now
resolve through `Illuminate\Auth\Middleware\Authenticate:sanctum`.

---

### 2. Unauthenticated API requests returned 500, not 401 — HIGH

Laravel's `withMiddleware()` unconditionally registers
`redirectGuestsTo(fn () => route('login'))` (`ApplicationBuilder.php:278`). When a guest
hit a protected route **without** an `Accept: application/json` header,
`Authenticate::unauthenticated()` evaluated `route('login')`, which does not exist in this
API-only app. That threw `RouteNotFoundException` *inside the middleware* — before the
exception handler ran — so the custom 401 envelope never applied.

Observed before the fix:

| Request | Result |
|---|---|
| `GET /api/orders` with `Accept: application/json` | 401 + correct envelope |
| `GET /api/orders` with no `Accept` header | **500 + HTML stack trace** |

Any client that does not set `Accept` — plain `curl`, many HTTP libraries — got a 500.

**Fix:** `bootstrap/app.php` now overrides the callback so API routes produce a genuine
`AuthenticationException`, plus `shouldRenderJsonWhen()` so unhandled exceptions on
`api/*` render as JSON rather than an HTML error page.

---

### 3. `GET /api/orders` ignored its own policy — HIGH

`OrderPolicy::viewAny()` returned `false` for employees, but `OrderController::index()`
never called `authorize('viewAny', ...)`. It only checked that a user was present.

Measured: an employee owning **0** orders received **HTTP 200 and all 255 orders** in the
system. This is the "succeeded when it should have failed" case.

**Fix:** `index()` now authorizes and scopes the query by role.

| Caller | Owns | Sees |
|---|---|---|
| Jane (employee) | 0 | 0 |
| John (employee) | 5 | 5 |
| Approver | — | 255 |

---

### 4. `placed_at` validation contradicted the schema — MEDIUM

The column is `NOT NULL` (`create_orders_table.php:19`) but was validated as `nullable`.
Omitting it passed validation and failed at the database, returning a 500 whose body
**leaked the full INSERT statement and database path**:

```
SQLSTATE[23000]: NOT NULL constraint failed: orders.placed_at
(Connection: sqlite, Database: C:\...\database.sqlite,
 SQL: insert into "orders" ("customer_id", "total", ...) values (1, 10, pending, 1, ...))
```

**Fix:** `placed_at` is now `required|date`, so the request is rejected with 422 before
reaching the database.

---

## Verified results

Run without an `Accept` header, i.e. the way a naive client calls the API.

### Authentication

| Case | Expected | Actual |
|---|---|---|
| No `Authorization` header | 401 | 401 |
| Garbage token (`Bearer garbage`) | 401 | 401 |
| Valid-format token from another app (`1\|WrongApp`) | 401 | 401 |
| Valid token with final character altered | 401 | 401 |

All return the same envelope, with no hint as to why authentication failed:

```json
{"error":true,"code":"unauthenticated","message":"Unauthenticated","details":[]}
```

### Ownership and roles

| Case | Expected | Actual |
|---|---|---|
| Owner views own order | 200 | 200 |
| Owner lists orders (scoped) | 200 | 200 |
| **User B views User A's order** | 403 | 403 |
| **User B updates User A's order** | 403 | 403 |
| **User B deletes User A's order** | 403 | 403 |
| Approver views another user's order | 200 | 200 |
| Approver updates another user's order | 200 | 200 |

Denials return 403 — not 404 — and carry no order data:

```json
{"error":true,"code":"forbidden","message":"This action is unauthorized.","details":[]}
```

### Data integrity after blocked writes

Order 255, owned by User A with `total=777.77`, after User B's blocked PUT and DELETE:

```
still exists | total=777.77 | status=pending | user_id=1
```

Nothing was modified or deleted. No cross-user write succeeded in any test.

### Token revocation

| Case | Expected | Actual |
|---|---|---|
| Logout | 200 | 200 |
| Revoked token, GET | 401 | 401 |
| Revoked token, PUT | 401 | 401 |
| A second session's token after the first logs out | 200 | 200 |

Logout deletes only the current token. Other sessions survive — intentional for
multi-device use, and worth noting: a leaked token stays valid until that specific token
is revoked or the user's tokens are cleared.

### Rate limiting (`throttle:5,1`)

Six consecutive failed logins:

```
attempt 1 -> 401    attempt 5 -> 401
attempt 2 -> 401    attempt 6 -> 429
attempt 3 -> 401    attempt 7 -> 429
attempt 4 -> 401
```

### Validation

| Case | Expected | Actual |
|---|---|---|
| `customer_id` not in `customers` table | 422 | 422 |
| `placed_at` omitted | 422 | 422 |

No token value appears in any validation or error response.

---

## Known remaining gaps

| Item | Severity | Note |
|---|---|---|
| Tokens may appear in request logs | MEDIUM | `Authorization` headers are not redacted in logging config. |
| Unlimited concurrent tokens per user | MEDIUM | Every login mints another token; none expire. Widens exposure if one leaks. |
| No audit logging | MEDIUM | Denied access attempts are not recorded anywhere. |
| Rate limit is per-IP | LOW | Distributed credential stuffing is not mitigated. |

---

## Reproducing

```bash
php artisan serve
```

Then either import `Postman_Authorization_Tests_v2.json` and run the Setup folder first,
or exercise the endpoints directly with `curl` as described above.
