# Advanced Postman Tests — Three Edge Cases

These tests verify gaps not covered in the standard suite.

---

## 1. Authorization Exception Handler — Envelope Format

**Verify that 403 denials return your JSON envelope, not a raw exception.**

### Test: Employee tries to view another user's order

**Setup** (run these in Setup folder first):
- `1. User A Login` 
- `2. Create Order - User A` (save the order ID)
- `3. User B Register`

**Request:**
```
GET http://localhost:8000/api/orders/{order_id_from_user_a}
Authorization: Bearer {{token_b}}
```

**Expected Response (403 Forbidden):**
```json
{
  "error": true,
  "code": "forbidden",
  "message": "This action is unauthorized.",
  "details": []
}
```

**What to verify:**
- Status: 403 (not 404, not 500)
- Response is valid JSON (not HTML)
- `error` field is `true`
- `code` is exactly `"forbidden"`
- `message` is generic (no order ID or user ID leaked)
- `details` is an empty array (no backtrace)

**Manual test in Postman:**
1. After Setup, go to `Test 1 - Cross-User Access`
2. Click `Attack: User B tries to update User A's order`
3. Click **Send**
4. Look at **Body** tab → should be JSON, not HTML
5. Check each field matches above

---

## 2. Multiple-Login Behavior — Token Validity After Second Login

**Verify that: (a) multiple logins create multiple tokens, (b) logging out revokes only the current token.**

### Scenario

A user logs in twice without logging out first. This tests whether you support multi-device sessions and whether logout is selective or global.

### Test Sequence in Postman

**Step 1: User A logs in (gets token A)**
```
POST http://localhost:8000/api/login
Content-Type: application/json

{
  "email": "employee@example.com",
  "password": "password"
}
```

Save response token as `token_a_login1`

**Step 2: User A logs in again (gets token B)**
```
POST http://localhost:8000/api/login
Content-Type: application/json

{
  "email": "employee@example.com",
  "password": "password"
}
```

Save response token as `token_a_login2`

**Step 3: Both tokens can access protected routes**
```
GET http://localhost:8000/api/orders
Authorization: Bearer {{token_a_login1}}
```
Expected: **200 OK** (token A still works)

```
GET http://localhost:8000/api/orders
Authorization: Bearer {{token_a_login2}}
```
Expected: **200 OK** (token B works)

**Step 4: Logout with token A**
```
POST http://localhost:8000/api/logout
Authorization: Bearer {{token_a_login1}}
```
Expected: **200 OK**

**Step 5: Token A should now be revoked**
```
GET http://localhost:8000/api/orders
Authorization: Bearer {{token_a_login1}}
```
Expected: **401 Unauthorized** (revoked)

**Step 6: Token B should STILL be valid**
```
GET http://localhost:8000/api/orders
Authorization: Bearer {{token_a_login2}}
```
Expected: **200 OK** (still works — only token A was revoked, not all tokens)

### What this tells you

| Result | Meaning |
|---|---|
| All steps pass | Multi-device support enabled. Users can stay logged in on multiple devices simultaneously. Logout is selective (revokes only current token). |
| Step 6 fails (401) | Logout is global. Logging out on one device logs you out everywhere. Less flexible, more secure for shared devices. |

### Create as Postman Tests

In your collection, create a new folder **Test 6 - Multiple Login Behavior**:

```
Test 6A: User A Login #1 (save token_a1)
Test 6B: User A Login #2 (save token_a2)
Test 6C: Token A1 access (expect 200)
Test 6D: Token A2 access (expect 200)
Test 6E: Logout with A1
Test 6F: Token A1 revoked? (expect 401)
Test 6G: Token A2 still valid? (expect 200 or 401, document whichever)
```

Add test assertions:

```javascript
// Test 6C
pm.test('Both tokens work before logout', function () {
    pm.expect(pm.response.code).to.equal(200);
});

// Test 6F
pm.test('Token A1 revoked after logout', function () {
    pm.expect(pm.response.code).to.equal(401);
});

// Test 6G
pm.test('Token A2 behavior after A1 logout', function () {
    // Document the actual behavior
    pm.expect(pm.response.code).to.be.oneOf([200, 401]);
    console.log('Token A2 after A1 logout: ' + pm.response.code);
});
```

---

## 3. Deleted User + Old Token — Cascade Delete

**Verify that deleting a user revokes all their tokens immediately.**

This requires **Tinker** (Laravel REPL) to delete the user mid-test, since there's no DELETE endpoint for users in this API.

### Test Sequence

**Step 1: Create a test user (or use existing)**
```
POST http://localhost:8000/api/register
Content-Type: application/json

{
  "name": "Temp User",
  "email": "temp@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

Save the token as `temp_token`. Note the user ID from response.

**Step 2: Verify token works**
```
GET http://localhost:8000/api/orders
Authorization: Bearer {{temp_token}}
```
Expected: **200 OK**

**Step 3: Delete the user via Tinker**

Open terminal in your project directory and run:

```bash
php artisan tinker
```

Then in Tinker:

```php
App\Models\User::where('email', 'temp@example.com')->delete();
exit
```

This deletes the user and cascades to delete all their tokens.

**Step 4: Token should now be invalid**
```
GET http://localhost:8000/api/orders
Authorization: Bearer {{temp_token}}
```
Expected: **401 Unauthorized**

The user is gone → their token row is gone → Sanctum can't find it → 401.

### Create as Postman Test

Create folder **Test 7 - Deleted User Edge Case**:

```
Test 7A: Register temp user (save token_temp, save user_id)
Test 7B: Token works (expect 200)
Test 7C: [MANUAL STEP] Delete user via: php artisan tinker → App\Models\User::find({user_id})->delete() → exit
Test 7D: Token invalid after delete (expect 401)
```

Test assertion for 7D:

```javascript
pm.test('Deleted user token is revoked', function () {
    pm.expect(pm.response.code).to.equal(401);
    var body = pm.response.json();
    pm.expect(body.code).to.equal('unauthenticated');
});
```

### What this verifies

- ✅ Foreign key cascade delete is configured correctly
- ✅ Token cannot be "orphaned" (exist without a user)
- ✅ Deleted users cannot be impersonated via old tokens
- ✅ App security survives a user deletion edge case

---

## Postman Collection Additions

Add these three test folders to your collection in this order:

```
Laravel Sanctum Authorization Tests
├── Setup - Get Tokens
├── Test 1 - Cross-User Access
├── Test 2 - Token Revocation
├── Test 3 - Malformed Tokens
├── Test 4 - Rate Limiting
├── Test 5 - Valid Access
├── Test 6 - Multiple Login Behavior          [NEW]
├── Test 7 - Deleted User Edge Case           [NEW]
└── Test 8 - Authorization Envelope Format    [NEW]
```

Each new test folder has explicit pass/fail assertions, so Postman's **Runner** will show 🟢 or 🔴 for each.

---

## Quick Checklist

Run these tests:

- [ ] Test 8: Verify 403 response is JSON with correct envelope
- [ ] Test 6: All 7 steps — document whether logout is selective or global
- [ ] Test 7: Manual Tinker delete, then verify token becomes 401

All three close real security gaps and should be part of your acceptance criteria.
