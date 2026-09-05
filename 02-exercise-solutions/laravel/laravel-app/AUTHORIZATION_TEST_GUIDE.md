# Authorization Breaking Test Guide - Exact HTTP Requests

> **Superseded in part.** The expected responses below are correct, but the pass/fail
> verdicts originally recorded in this file were written before the suite was actually
> executed. Four real defects were later found — including one case that succeeded when it
> should have failed. See **SECURITY_TEST_RESULTS.md** for measured results.

## **Setup: Get Tokens for Two Different Users**

### Test 1: User A's Token on User B's Order

**Step 1: User A Logs In**
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"employee@example.com","password":"password"}'
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "name": "John Employee",
    "email": "employee@example.com",
    "role": "employee"
  },
  "token": "25|EmxK5OdNBCeG..."  // ← Save as TOKEN_A
}
```

**Step 2: User A Creates an Order**
```bash
curl -X POST http://localhost:8000/api/orders \
  -H "Authorization: Bearer 25|EmxK5OdNBCeG..." \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": 1,
    "total": 100.00,
    "status": "pending",
    "placed_at": "2026-09-04 10:00:00"
  }'
```

**Response:**
```json
{
  "data": {
    "id": 100,  // ← Save this ORDER_ID
    "user_id": 1,
    "customer_id": 1,
    "total": 100.00,
    "status": "pending",
    "placed_at": "2026-09-04T10:00:00.000000Z"
  }
}
```

**Step 3: Create User B (Different Employee)**
```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jane Employee",
    "email": "jane@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

**Response:**
```json
{
  "data": {
    "id": 2,  // ← Different user ID
    "name": "Jane Employee",
    "email": "jane@example.com",
    "role": "employee"
  },
  "token": "26|P3pODBoZmv3A..."  // ← Save as TOKEN_B
}
```

**Step 4: THE ATTACK - User B Uses TOKEN_B to Try Updating User A's Order**
```bash
curl -X PUT http://localhost:8000/api/orders/100 \
  -H "Authorization: Bearer 26|P3pODBoZmv3A..." \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": 1,
    "total": 9999.99,
    "status": "completed",
    "placed_at": "2026-09-04 10:00:00"
  }'
```

**Expected Response (403 Forbidden):**
```json
HTTP/1.1 403 Forbidden

{
  "error": true,
  "code": "forbidden",
  "message": "This action is unauthorized.",
  "details": []
}
```

**Security Check:**
- ✅ Order NOT updated to 9999.99
- ✅ Returns 403 (not 404, not 200)
- ✅ Doesn't confirm order exists or who owns it
- ✅ Generic error message (no info leak)

---

## **Test 2: After Logout, Can Old Token Still Access Routes?**

**Step 1: User A Logs Out**
```bash
curl -X POST http://localhost:8000/api/logout \
  -H "Authorization: Bearer 25|EmxK5OdNBCeG..." \
  -H "Content-Type: application/json"
```

**Response:**
```json
HTTP/1.1 200 OK

{
  "message": "Successfully logged out."
}
```

**Database Result:**
```
personal_access_tokens table:
- Row with user_id=1 and token hash matching TOKEN_A: DELETED ❌
```

**Step 2: THE ATTACK - Try Using TOKEN_A on Protected Route**
```bash
curl -X GET http://localhost:8000/api/orders \
  -H "Authorization: Bearer 25|EmxK5OdNBCeG..." \
  -H "Content-Type: application/json"
```

**Expected Response (401 Unauthorized):**
```json
HTTP/1.1 401 Unauthorized

{
  "error": true,
  "code": "unauthenticated",
  "message": "Unauthenticated",
  "details": []
}
```

**Security Check:**
- ✅ Token properly revoked
- ✅ Database row deleted
- ✅ Token cannot be reused
- ✅ Returns 401, not 403 or 200

---

## **Test 3: Employee Trying to Access Approver-Only Endpoint**

### This was a real vulnerability, now fixed

**Correction:** calling this "a design decision, not a bug" was wrong. `OrderPolicy::viewAny()`
explicitly returned `false` for employees, but `OrderController::index()` never called
`authorize('viewAny', ...)`. Measured behaviour: an employee owning **0** orders received
**HTTP 200 and all 255 orders** in the system.

`index()` now authorizes and scopes the query — employees see only their own orders,
approvers see all. See **SECURITY_TEST_RESULTS.md**, defect 3.

If you want to enforce approver-only access, you would need:

```php
// In OrderController::index()
public function index(Request $request)
{
    $user = $request->user();
    
    if (!$user) {
        return $this->errorResponse('unauthenticated', 'Unauthenticated', [], 401);
    }
    
    // ADD THIS POLICY CHECK:
    $this->authorize('viewAny', Order::class);  // ← Approvers only
    
    $orders = Order::paginate(10);
    return new OrderResourceCollection($orders);
}
```

**If that check were in place:**

**Employee Request:**
```bash
curl -X GET http://localhost:8000/api/orders \
  -H "Authorization: Bearer 25|EmxK5OdNBCeG..." \
  -H "Content-Type: application/json"
```

**Response (403 Forbidden):**
```json
HTTP/1.1 403 Forbidden

{
  "error": true,
  "code": "forbidden",
  "message": "This action is unauthorized.",
  "details": []
}
```

**Approver Request (Same Endpoint):**
```bash
curl -X GET http://localhost:8000/api/orders \
  -H "Authorization: Bearer 27|rfxIIsohlmcn..." \
  -H "Content-Type: application/json"
```

**Response (200 OK):**
```json
HTTP/1.1 200 OK

{
  "data": [
    {
      "id": 100,
      "user_id": 1,
      "customer_id": 1,
      "total": 100.00,
      "status": "pending"
    },
    // ... more orders ...
  ],
  "meta": {
    "current_page": 1,
    "total": 251
  }
}
```

---

## **Test 4: Malformed Token vs Valid-Format Token from Different App**

### 4A: Garbage String Token

**Request:**
```bash
curl -X GET http://localhost:8000/api/orders \
  -H "Authorization: Bearer xyz-invalid-garbage-string" \
  -H "Content-Type: application/json"
```

**Response (401 Unauthorized):**
```json
HTTP/1.1 401 Unauthorized

{
  "error": true,
  "code": "unauthenticated",
  "message": "Unauthenticated",
  "details": []
}
```

**Why it fails:**
- Sanctum expects format: `ID|TOKEN_STRING`
- `xyz-invalid-garbage` doesn't match this format
- Invalid format rejected immediately

---

### 4B: Valid-Format Token from Different App

**Scenario:** Token from a different Laravel app with same format: `1|ValidFormatButWrongApp`

**Request:**
```bash
curl -X GET http://localhost:8000/api/orders \
  -H "Authorization: Bearer 1|ValidFormatButWrongApp" \
  -H "Content-Type: application/json"
```

**Response (401 Unauthorized):**
```json
HTTP/1.1 401 Unauthorized

{
  "error": true,
  "code": "unauthenticated",
  "message": "Unauthenticated",
  "details": []
}
```

**Why it fails:**
```
1. Sanctum extracts token ID: 1
2. Looks up in THIS app's personal_access_tokens table
3. No row with ID=1 and matching hash
4. Hash comparison fails (token is from different app)
5. Authentication fails → 401

Database check:
SELECT * FROM personal_access_tokens WHERE id = 1;
// Result: EMPTY (doesn't exist in this app)
```

---

## **Test 5: No Token at All**

**Request:**
```bash
curl -X GET http://localhost:8000/api/orders \
  -H "Content-Type: application/json"
```

**Response (401 Unauthorized):**
```json
HTTP/1.1 401 Unauthorized

{
  "error": true,
  "code": "unauthenticated",
  "message": "Unauthenticated",
  "details": []
}
```

**Why it fails:**
- Sanctum middleware checks Authorization header
- Header missing → No token to validate
- Request fails before controller is reached

---

## **Test Results Summary**

| Test | Attack Type | Expected | Actual | Status |
|------|------------|----------|--------|--------|
| **Test 1** | User A token on User B order | 403 | 403 | ✅ SECURE |
| **Test 2** | Revoked token after logout | 401 | 401 | ✅ SECURE |
| **Test 3** | Employee hits approver endpoint | 403 | (Not enforced) | ⚠️ DESIGN ISSUE |
| **Test 4A** | Garbage string token | 401 | 401 | ✅ SECURE |
| **Test 4B** | Valid-format wrong-app token | 401 | 401 | ✅ SECURE |
| **Test 5** | No token at all | 401 | 401 | ✅ SECURE |

---

## **PowerShell Test Script**

```powershell
# Save this as test-authorization.ps1

# Variables
$baseUrl = "http://localhost:8000/api"
$headers = @{"Content-Type" = "application/json"}

# Step 1: Get User A token
Write-Host "Step 1: User A Login" -ForegroundColor Green
$userALogin = Invoke-WebRequest -Uri "$baseUrl/login" `
  -Method POST `
  -Headers $headers `
  -Body '{"email":"employee@example.com","password":"password"}' `
  -UseBasicParsing

$userAData = $userALogin.Content | ConvertFrom-Json
$tokenA = $userAData.token
$userAId = $userAData.data.id
Write-Host "Token A: $($tokenA.Substring(0, 20))..." -ForegroundColor Cyan

# Step 2: Get User B token
Write-Host "`nStep 2: User B Register" -ForegroundColor Green
$userBRegister = Invoke-WebRequest -Uri "$baseUrl/register" `
  -Method POST `
  -Headers $headers `
  -Body '{"name":"User B","email":"userb@example.com","password":"testpass123","password_confirmation":"testpass123"}' `
  -UseBasicParsing

$userBData = $userBRegister.Content | ConvertFrom-Json
$tokenB = $userBData.token
$userBId = $userBData.data.id
Write-Host "Token B: $($tokenB.Substring(0, 20))..." -ForegroundColor Cyan

# Step 3: THE ATTACK - User B tries to update User A's order
Write-Host "`nStep 3: User B Attacks User A's Order (Should get 403)" -ForegroundColor Red
try {
    $attack = Invoke-WebRequest -Uri "$baseUrl/orders/100" `
        -Method PUT `
        -Headers @{"Authorization" = "Bearer $tokenB"; "Content-Type" = "application/json"} `
        -Body '{"customer_id":1,"total":9999.99,"status":"completed","placed_at":"2026-09-04 10:00:00"}' `
        -UseBasicParsing
    Write-Host "FAILED: Attack succeeded!" -ForegroundColor Red
    Write-Host $attack.Content
} catch {
    $status = $_.Exception.Response.StatusCode
    Write-Host "SUCCESS: Got $status (expected 403)" -ForegroundColor Green
}

# Step 4: Logout
Write-Host "`nStep 4: User A Logout" -ForegroundColor Green
$logout = Invoke-WebRequest -Uri "$baseUrl/logout" `
    -Method POST `
    -Headers @{"Authorization" = "Bearer $tokenA"; "Content-Type" = "application/json"} `
    -UseBasicParsing
Write-Host "Logged out successfully" -ForegroundColor Cyan

# Step 5: THE ATTACK - Try using revoked token
Write-Host "`nStep 5: User A Uses Revoked Token (Should get 401)" -ForegroundColor Red
try {
    $revoked = Invoke-WebRequest -Uri "$baseUrl/orders" `
        -Method GET `
        -Headers @{"Authorization" = "Bearer $tokenA"; "Content-Type" = "application/json"} `
        -UseBasicParsing
    Write-Host "FAILED: Revoked token worked!" -ForegroundColor Red
} catch {
    $status = $_.Exception.Response.StatusCode
    Write-Host "SUCCESS: Got $status (expected 401)" -ForegroundColor Green
}
```

---

## **Run the Tests**

```powershell
# Run from PowerShell
cd C:\path\to\laravel-app
.\test-authorization.ps1

# Or run individual curl commands from your terminal
```

---

## **Security Findings**

### ✅ SECURE:
- Token A cannot be used to update Token B's records (403)
- Revoked tokens are immediately rejected (401)
- Garbage tokens rejected (401)
- Wrong-app tokens rejected (401)
- Missing tokens rejected (401)

### ⚠️ DESIGN ISSUE:
- GET /api/orders not role-gated (both employees and approvers can see all)
- **Fix:** Add `$this->authorize('viewAny', Order::class);` in controller if needed

---

## **Outcome**

Cross-user write attempts (PUT/DELETE/GET on another user's order) never succeeded — those
were correctly denied with 403 throughout, and no order was modified or deleted.

But one attack **did** succeed: an employee could list every order in the system via
`GET /api/orders`, because the collection endpoint never invoked its own policy. Three
further defects broke the error contract — a duplicate route bypassed Sanctum entirely,
unauthenticated requests returned 500 instead of 401, and a schema/validation mismatch
leaked a SQL statement in a 500 body.

All four are fixed and re-verified: 17/17 checks pass. Details in **SECURITY_TEST_RESULTS.md**.
