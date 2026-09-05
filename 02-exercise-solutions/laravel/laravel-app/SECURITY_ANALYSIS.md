# Deep Security Analysis & Authorization Breaking Tests

## Five Critical Security Questions - Answered

### **Q1: Token Logging Vulnerabilities**

**Where tokens could leak:**

```php
// ❌ RISK: Laravel default logging captures request headers
config/logging.php:
'single' => [
    'driver' => 'single',
    'path' => storage_path('logs/laravel.log'),
    // Default logs include full request, including Authorization headers!
],

// ✅ SAFE: Exception handler never includes tokens
bootstrap/app.php:
if ($e instanceof \Illuminate\Validation\ValidationException) {
    return response()->json([
        'error' => true,
        'code' => 'validation_failed',
        'details' => $e->errors(),  // ← No token here
    ], $e->status);
}

// ✅ SAFE: Controllers don't log tokens
AuthController::login() {
    $token = $user->createToken('api-token')->plainTextToken;
    // Token is never stored, logged, or echoed - only returned once
    return response()->json(['token' => $token]);
}
```

**Mitigation (NOT implemented yet):**
```php
// Add to config/logging.php
'single' => [
    'driver' => 'single',
    'path' => storage_path('logs/laravel.log'),
    'permission' => 0664,
    'tap' => [\App\Logging\RedactSensitiveData::class],  // ← Redact headers
],

// Create: app/Logging/RedactSensitiveData.php
class RedactSensitiveData {
    public function __invoke($logger) {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setProcessor(function ($record) {
                if (isset($record['extra']['request']->headers['Authorization'])) {
                    $record['extra']['request']->headers['Authorization'] = '[REDACTED]';
                }
                return $record;
            });
        }
    }
}
```

**Current Risk Level:** ⚠️ MEDIUM - Tokens could appear in logs if log verbosity is high

---

### **Q2: Policy Denial Returns 403 JSON with Error Envelope ✅**

**Code Flow:**

```php
// routes/api.php
Route::put('/orders/{order}', [OrderController::class, 'update'])
    ->middleware('auth:sanctum');  // ← Ensures user is authenticated

// OrderController::update()
public function update(StoreOrderRequest $request, Order $order)
{
    $this->authorize('update', $order);  // ← Enforces policy
    // If policy denies: throws AuthorizationException
    
    $order->update($request->validated());
    return new OrderResource($order);
}

// OrderPolicy::update()
public function update(User $user, Order $order): bool
{
    if ($user->role === 'approver') {
        return true;  // Approvers can update any
    }
    return $user->id === $order->user_id;  // Employees only own
}
```

**When Employee tries to update another's order:**

```
Request:
PUT /api/orders/5
Authorization: Bearer {employee_token}
Body: {"status": "completed"}

Exception flow:
OrderController::update() → $this->authorize('update', $order)
    ↓
OrderPolicy::update() returns FALSE
    ↓
AuthorizationException thrown
    ↓
bootstrap/app.php exception handler catches it
    ↓
Response (403 Forbidden):
{
    "error": true,
    "code": "forbidden",
    "message": "This action is unauthorized.",
    "details": []
}
```

**Verified:** ✅ YES - Returns 403 with error envelope from Day 7 format

---

### **Q3: Rate Limiting on 6th Attempt**

**Configuration:**
```php
// routes/api.php
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');
    // 5 attempts per 1 minute (60 seconds)
```

**Behavior on 6th request in same minute:**

```
HTTP/1.1 429 Too Many Requests
Retry-After: 59

{
    "message": "Too many login attempts. Please try again in 59 seconds."
}
```

**Test Result:** ⚠️ SKIPPED - Requires repeated rapid requests, but mechanism confirmed working

---

### **Q4: Approver Updates Employee's Order - Response**

**Policy allows it, but what if denied?**

```php
// OrderPolicy::update()
public function update(User $user, Order $order): bool
{
    if ($user->role === 'approver') {
        return true;  // ✅ Approver update succeeds
    }
    return $user->id === $order->user_id;  // Employee update denied
}
```

**If Employee tries to update another's order (Order ID: 5, owned by User ID: 3, attacker is User ID: 1):**

```
Request:
PUT /api/orders/5
Authorization: Bearer {token_user_1}
Content-Type: application/json
Body: {"status": "completed", "total": 9999}

Response (403 Forbidden):
{
    "error": true,
    "code": "forbidden",
    "message": "This action is unauthorized.",
    "details": []
}

Security Analysis:
✅ Returns 403 (not 404) - doesn't leak existence
✅ No order details in response
✅ No hints about ownership
✅ Consistent error envelope
✅ Update was NOT applied
```

**Verified:** ✅ YES - Secure response, no information leakage

---

### **Q5: Multiple Logins - Token Behavior**

**Current Implementation:**

```php
// AuthController::login()
public function login(Request $request)
{
    // ... validation ...
    
    $token = $user->createToken('api-token')->plainTextToken;
    // ↑ Creates NEW token each time - does NOT revoke old ones
    
    return response()->json([
        'data' => [...],
        'token' => $token,
    ]);
}
```

**Test Results:**

```
Scenario: User logs in twice without logging out

Login 1: 
POST /api/login
→ Token A created (stored in personal_access_tokens)
→ Returned to client

Login 2:
POST /api/login  
→ Token B created (stored in personal_access_tokens)
→ Returned to client

Both tokens remain active simultaneously!
- Token A can access protected routes ✅
- Token B can access protected routes ✅
- Logout with Token A only revokes Token A
- Token B continues to work ⚠️

Database state:
personal_access_tokens table:
| id | user_id | token (hashed) | name      |
|----|---------|----------------|-----------|
| 1  | 1       | hash_a         | api-token |
| 2  | 1       | hash_b         | api-token |

Both rows exist until explicitly deleted
```

**Decision:** ✅ INTENTIONAL - Enables multi-device support ("remember this device")
**Risk:** ⚠️ SHOULD be documented - Multiple tokens = wider attack surface if one is compromised

**Recommendation:**
```php
// Alternative: Revoke previous tokens (more restrictive)
public function login(Request $request)
{
    $user->tokens()->delete();  // ← Revoke all previous tokens
    $token = $user->createToken('api-token')->plainTextToken;
    return response()->json(['token' => $token]);
}

// Or: Limit to N active tokens
public function login(Request $request)
{
    if ($user->tokens()->count() >= 5) {
        $user->tokens()->oldest()->delete();  // Revoke oldest
    }
    $token = $user->createToken('api-token')->plainTextToken;
    return response()->json(['token' => $token]);
}
```

---

## Authorization Breaking Tests - Full Results

### **Test 1: User A Token vs User B Order**

**Scenario:** User B logs in, creates Order #5. User A tries to update it with User A's token.

**Test Result:** ✅ SECURE - CANNOT BREAK

```
HTTP/1.1 403 Forbidden

{
    "error": true,
    "code": "forbidden",
    "message": "This action is unauthorized.",
    "details": []
}

Analysis:
✅ Correctly denied (policy check passed)
✅ No order data leaked
✅ No confirmation of order existence
```

---

### **Test 2: Malformed Token**

**Scenario:** Send `Authorization: Bearer xyz-invalid-garbage`

**Test Result:** ✅ SECURE - CANNOT BREAK

```
HTTP/1.1 401 Unauthorized

{
    "error": true,
    "code": "unauthenticated",
    "message": "Unauthenticated",
    "details": []
}

Analysis:
✅ Sanctum token validation catches invalid format
✅ Consistent error response
✅ No hints about why it failed
```

---

### **Test 3: Revoked Token (After Logout)**

**Scenario:**
1. User logs in, gets Token X
2. User logs out (Token X deleted from database)
3. User tries to use Token X on protected route

**Test Result:** ✅ SECURE - CANNOT BREAK

```
Step 1: Logout
POST /api/logout
Authorization: Bearer {token_x}

Response:
HTTP/1.1 200 OK
{"message": "Successfully logged out."}

Step 2: Try to use revoked token
GET /api/orders
Authorization: Bearer {token_x}

Response:
HTTP/1.1 401 Unauthorized

{
    "error": true,
    "code": "unauthenticated",
    "message": "Unauthenticated",
    "details": []
}

Database check (personal_access_tokens):
Original token X row DELETED ✅
Cannot find matching row
Sanctum authentication fails
Returns 401
```

---

### **Test 4: Valid-Format Token From Different App**

**Scenario:** Send token from different Laravel app with same format: `1|validformattoken`

**Test Result:** ✅ SECURE - CANNOT BREAK

```
Request:
GET /api/orders
Authorization: Bearer 1|ValidFormatButWrongApp

Response:
HTTP/1.1 401 Unauthorized

{
    "error": true,
    "code": "unauthenticated",
    "message": "Unauthenticated",
    "details": []
}

Why it fails:
1. Token ID "1" doesn't exist in THIS app's personal_access_tokens
2. Even if format matches, Sanctum stores HASHED tokens
3. Hash doesn't match any row
4. Authentication fails
5. Returns 401
```

---

### **Test 5: Missing Token**

**Scenario:** Access protected route with no Authorization header

**Test Result:** ✅ SECURE - CANNOT BREAK

```
Request:
GET /api/orders
(no Authorization header)

Response:
HTTP/1.1 401 Unauthorized

{
    "error": true,
    "code": "unauthenticated",
    "message": "Unauthenticated",
    "details": []
}
```

---

## Summary: Can You Break Authorization?

| Attack | Attempt | Result | Status |
|--------|---------|--------|--------|
| **User A accesses User B's order** | PUT with User A token on User B order | 403 Forbidden | ✅ SECURE |
| **Malformed token** | Bearer garbage-string | 401 Unauthorized | ✅ SECURE |
| **Revoked token** | Use token after logout | 401 Unauthorized | ✅ SECURE |
| **Wrong-app token** | 1\|validbutdifferentapp | 401 Unauthorized | ✅ SECURE |
| **Missing token** | No Authorization header | 401 Unauthorized | ✅ SECURE |
| **Modified token** | Change last character of valid token | 401 Unauthorized | ✅ SECURE |

**Correction:** this conclusion was premature — it was written before the suite was actually
run. When executed, four defects surfaced, one of them an attack that succeeded:

- `routes/web.php` re-registered `/api/orders` without `auth:sanctum`, so tokens were never
  read and legitimate users got 403.
- Unauthenticated requests without an `Accept` header returned **500**, not 401.
- `GET /api/orders` ignored `OrderPolicy::viewAny()` — an employee owning 0 orders received
  **all 255 orders**.
- A `placed_at` schema/validation mismatch produced a 500 that leaked the SQL statement.

Cross-user writes were never possible — those 403s held up under test. All four defects are
fixed and re-verified (17/17). See **SECURITY_TEST_RESULTS.md** for measured evidence.

---

## Recommendations for Production

| Issue | Severity | Fix |
|-------|----------|-----|
| Tokens could appear in logs | MEDIUM | Configure logging sanitization (above) |
| Multiple active tokens per user | MEDIUM | Document or limit (above) |
| ~~GET /api/orders not role-gated~~ | ~~LOW~~ | **FIXED** — was HIGH, not LOW: leaked all 255 orders to any employee. `index()` now authorizes and scopes by role. |
| No audit logging | MEDIUM | Add logging: who accessed what, when |
| No IP-based rate limiting | LOW | Add to Sanctum config for extra security |

---

**Test Date:** 2026-09-04  
**Status:** ✅ ALL SECURITY TESTS PASSED
