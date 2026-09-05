# Postman Setup Guide - Authorization Tests

## Step 1: Import the Postman Collection

1. **Open Postman** (Desktop or Web)
2. Click **Import** (top-left)
3. Select **Upload Files**
4. Choose: `Postman_Authorization_Tests.json`
5. Click **Import**

A new collection called **"Laravel Sanctum Authorization Tests"** will appear in your left sidebar.

---

## Step 2: Set Environment Variable

Before running tests, set the base URL:

### Option A: Quick Setup (Per Request)
- The collection already has `{{base_url}}` set to `http://localhost:8000`
- If your server runs on a different port, just update the variable

### Option B: Create Environment (Recommended)
1. Click **Environments** (left sidebar)
2. Click **+** to create new environment
3. Name it: `Laravel Local`
4. Add variable:
   - Key: `base_url`
   - Value: `http://localhost:8000`
5. Save
6. Select this environment in the top-right dropdown

---

## Step 3: Run the Tests

### Run Setup First
Navigate to **Setup - Get Tokens** folder and run these IN ORDER:
1. **1. User A Login** — Gets token for employee@example.com
2. **2. Create Order - User A** — Creates an order owned by User A
3. **3. User B Register** — Creates second user and gets token

These save tokens and order IDs as variables for the attack tests.

**How to run:**
- Click the **Send** button on each request
- Watch the Test Results at the bottom
- Check the Console (View → Show Postman Console) for success messages

---

## Step 4: Run Authorization Breaking Tests

### Test 1: Cross-User Access (THE MAIN ATTACK)
Navigate to **Test 1 - Cross-User Access**

**Request:** `PUT /api/orders/{order_id_a}` with User B's token trying to update User A's order

**Expected Result:**
- Status: **403 Forbidden** ✅
- Tests should PASS:
  - ✓ Should return 403 Forbidden
  - ✓ Should NOT return order data
  - ✓ Should have generic error message

**What to look for:**
- Response body shows `"error": true` and `"code": "forbidden"`
- No order details leaked
- Generic message: "This action is unauthorized."

---

### Test 2: Token Revocation (LOGOUT & REUSE)
Navigate to **Test 2 - Token Revocation**

**Part A: Logout**
1. Click **User A Logout**
2. Should return 200 OK with success message
3. Token A is now deleted from database

**Part B: Use Revoked Token**
1. Click **Attack: Use revoked token after logout**
2. Try to use the deleted token
3. Should return **401 Unauthorized** ✅

**Expected Tests:**
- ✓ Revoked token should return 401
- ✓ Should return unauthenticated error
- ✓ Should NOT return user data

---

### Test 3: Malformed Tokens
Navigate to **Test 3 - Malformed Tokens**

Run these three attacks in order:

**3A: Garbage Token**
- Authorization: `Bearer xyz-invalid-garbage-string`
- Expected: **401 Unauthorized** ✅

**3B: Valid-Format Wrong-App Token**
- Authorization: `Bearer 1|ValidFormatButWrongApp`
- Expected: **401 Unauthorized** ✅
- (Token from different app, doesn't exist in this database)

**3C: No Token**
- No Authorization header at all
- Expected: **401 Unauthorized** ✅

---

### Test 4: Rate Limiting
Navigate to **Test 4 - Rate Limiting**

Run the 6 login attempts in sequence (wrong password):
- Attempts 1-5: Should return **401 Unauthorized** (bad password)
- Attempt 6: Should return **429 Too Many Requests** ✅

**Test assertion on Attempt 6:**
- ✓ 6th attempt should return 429 Too Many Requests
- ✓ Should include retry-after header

**Rate Limit Details:**
- Limit: 5 attempts per minute per IP
- After 1 minute, counter resets
- Postman shows exact retry time in `Retry-After` header

---

### Test 5: Valid Access
Navigate to **Test 5 - Valid Access**

**User B Can Access Orders**
- Use User B's token (from setup)
- Should return **200 OK** ✅
- Shows all orders (both users' orders visible to authenticated users)

---

## Viewing Test Results

### In Postman:

**Option 1: Test Tab**
- Click any request
- Send it
- Scroll down to **Tests** tab
- See green ✓ (PASS) or red ✗ (FAIL)

**Example PASS:**
```
✓ Should return 403 Forbidden
✓ Should NOT return order data
✓ Should have generic error message
```

**Option 2: Console Output**
- View → Show Postman Console (bottom panel)
- Shows colored output:
  - 🟢 Green = Test passed
  - 🔴 Red = Test failed
  - 🟡 Yellow = Informational

**Example Console:**
```
✅ Token A saved: 25|EmxK5OdNBCeG...
✅ Order A created: 100
✅ Token B saved: 26|P3pODBoZmv3A...
```

---

## Run All Tests at Once (Collection Runner)

1. Click the **collection name** "Laravel Sanctum Authorization Tests"
2. Click the **Run** icon (▶️ play button)
3. In **Runner** window:
   - Folder order shows test sequence
   - Delay between requests: 100ms (prevents rate limit issues)
   - Click **Run**

Postman will:
- Run all requests in order
- Execute all test scripts
- Show summary report with pass/fail counts

---

## Variables: How They Work

The collection uses these environment variables (auto-filled):

| Variable | Set By | Used For |
|----------|--------|----------|
| `base_url` | Manual | All requests point here |
| `token_a` | User A Login | Requests by User A |
| `token_b` | User B Register | Requests by User B |
| `user_a_id` | User A Login | Reference (not used in tests) |
| `user_b_id` | User B Register | Reference (not used in tests) |
| `order_id_a` | Create Order - User A | Cross-user attack target |

**How to see variables:**
- Top-right corner: Environment dropdown
- Click the eye icon (👁️) next to environment name
- All current variables shown

---

## Troubleshooting

### "Authorization header not found" on Setup
- Make sure you ran **Setup - Get Tokens** requests FIRST
- Run them in order (login → create order → register)
- Check that base_url is correct

### "403 on setup but 401 on test"
- 403 = Forbidden (policy denied after auth)
- 401 = Unauthenticated (no valid token)
- Both are correct in different contexts

### Tests show "FAIL" instead of "PASS"
- Check response status code (should match expected)
- Click response body to see error details
- Verify token wasn't deleted by logout

### "Cannot resolve {{variable}}"
- Variable not set yet
- Run Setup section first
- Or manually set environment variables

### Rate limiting shows 401 instead of 429 on attempt 6
- You may have hit actual rate limit
- Wait 60 seconds
- Or restart the test sequence

---

## Expected Test Results Summary

| Test | Attack | Expected | Status |
|------|--------|----------|--------|
| **Test 1** | User B token on User A order | 403 | ✅ PASS |
| **Test 2A** | Logout | 200 | ✅ PASS |
| **Test 2B** | Use revoked token | 401 | ✅ PASS |
| **Test 3A** | Garbage token | 401 | ✅ PASS |
| **Test 3B** | Wrong-app token | 401 | ✅ PASS |
| **Test 3C** | No token | 401 | ✅ PASS |
| **Test 4** | 6th login attempt | 429 | ✅ PASS |
| **Test 5** | Valid access | 200 | ✅ PASS |

---

## Exporting Results

### Save Test Run Report:
1. After Collection Runner finishes
2. Click **Export Results** (top-right of runner)
3. Choose format: JSON or HTML
4. Save to your project

### Screenshot Test Results:
1. Click **Tests** tab on any request
2. Right-click → Screenshot
3. Add to documentation

---

## Quick Start (TL;DR)

```
1. Import Postman_Authorization_Tests.json
2. Set base_url to http://localhost:8000 (or your server)
3. Run Setup folder (in order):
   - User A Login
   - Create Order - User A
   - User B Register
4. Run each test folder:
   - Test 1: Cross-user attack (expect 403)
   - Test 2: Token revocation (expect 401)
   - Test 3: Malformed tokens (expect 401)
   - Test 4: Rate limiting (expect 429 on attempt 6)
   - Test 5: Valid access (expect 200)
5. Check Tests tab for pass/fail
6. View Console for detailed output
```

All tests should PASS ✅ (no authorization vulnerabilities found)
