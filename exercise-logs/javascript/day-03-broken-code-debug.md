# Exercise Log: JavaScript Broken Code Debug I
**Date:** Tuesday, 26 August 2026  
**Exercise:** Identify and fix 5+ bugs in a user service module  
**Duration:** 35 minutes (10–45 min work block)  
**Difficulty:** Intermediate (combines Promises, null handling, array methods, async/await)

---

## Exercise Objective

Given a broken `userService.js` module, identify all bugs, explain root causes, and write test cases to catch them.

---

## Bugs Identified

### Bug 1: Missing `await` on Promise
**Location:** Line 23 in `fetchUserFromAPI()`
```javascript
// BROKEN:
const user = response.json();  // Returns Promise, not data

// FIXED:
const user = await response.json();  // Now unwraps Promise to get data
```
**Root Cause:** `response.json()` returns a Promise; without `await`, the variable holds the Promise object itself  
**Error Type:** Would cause TypeError when trying to access properties on a Promise object  
**Learning:** Promises must be awaited to access their resolved value

---

### Bug 2: Null Dereference in `updateUser()`
**Location:** Line 31 in `updateUser()`
```javascript
// BROKEN:
function updateUser(id, updates) {
  const user = users.find(u => u.id === id);
  Object.assign(user, updates);  // Crashes if user is undefined
  return user;
}

// FIXED:
function updateUser(id, updates) {
  const user = users.find(u => u.id === id);
  if (!user) {
    return false;  // Fail early instead of crashing
  }
  Object.assign(user, updates);
  return user;
}
```
**Root Cause:** `Array.find()` returns `undefined` if no match; `Object.assign(undefined, ...)` throws TypeError  
**Error Type:** `TypeError: Cannot convert undefined or null to object`  
**Learning:** Always validate before operating on potentially undefined values

---

### Bug 3: Silent Data Loss in `deleteUser()`
**Location:** Line 38 in `deleteUser()`
```javascript
// BROKEN:
function deleteUser(userId) {
  const index = users.findIndex(u => u.id === userId);
  users.splice(index, 1);  // If index is -1, deletes LAST item!
}

// FIXED:
function deleteUser(userId) {
  const index = users.findIndex(u => u.id === userId);
  if (index === -1) {
    return false;  // Guard against invalid index
  }
  users.splice(index, 1);
  return true;
}
```
**Root Cause:** `Array.findIndex()` returns `-1` when not found; `splice(-1, 1)` uses negative indexing and deletes the LAST element  
**Error Type:** No error thrown; silently deletes wrong user (dangerous!)  
**Learning:** Off-by-one logic in array methods can cause silent data corruption

---

### Bug 4: Missing Required Field in Usage
**Location:** Line 47 in usage example
```javascript
// BROKEN:
addUser({ name: 'Alice' });  // Missing email; validation fails silently

// FIXED:
addUser({ name: 'Alice', email: 'alice@example.com' });  // Passes validation
```
**Root Cause:** Function validates `email` field, but caller doesn't provide it  
**Error Type:** Function returns `false` but caller doesn't check the return value  
**Learning:** Input validation only works if callers comply; document required fields clearly

---

### Bug 5: Invalid Async/Await Context
**Location:** Line 48 in usage example
```javascript
// BROKEN:
await fetchUserFromAPI(1);  // SyntaxError: await only in async function

// FIXED (Option 1 - Async IIFE):
(async () => {
  const user = await fetchUserFromAPI(1);
  console.log(user);
})();

// FIXED (Option 2 - Promise chaining):
fetchUserFromAPI(1).then(user => console.log(user));
```
**Root Cause:** `await` keyword only works inside `async` functions; top-level code is not async  
**Error Type:** `SyntaxError: await is only valid in async functions and the top level bodies of modules` (in some environments)  
**Learning:** Understand async context; alternatives include async IIFE, `.then()`, or wrapping in an async function

---

## Test Cases Written

### Test 1: `updateUser()` fails gracefully for non-existent user
```javascript
function testUpdateNonExistent() {
  // Setup
  addUser({ name: 'Alice', email: 'alice@example.com' });
  
  // Execute
  const result = updateUser(999, { name: 'Bob' });
  
  // Assert
  console.assert(result === false, 'Expected false for non-existent user');
}
```
**Purpose:** Verifies null check works; function returns `false` instead of crashing

---

### Test 2: `deleteUser()` returns `false` for non-existent user
```javascript
function testDeleteNonExistent() {
  // Setup
  const initialLength = users.length;
  
  // Execute
  const result = deleteUser(999);
  
  // Assert
  console.assert(result === false, 'Expected false for non-existent user');
  console.assert(users.length === initialLength, 'Array length should not change');
}
```
**Purpose:** Verifies `-1` guard works; prevents silent deletion of last user

---

### Test 3: `fetchUserFromAPI()` correctly awaits JSON parsing
```javascript
async function testFetchUserFromAPI() {
  // Execute
  const user = await fetchUserFromAPI(1);
  
  // Assert
  console.assert(typeof user === 'object', 'Expected object, not Promise');
  console.assert(user.id !== undefined, 'User should have an id');
  console.assert(user.email !== undefined, 'User should have an email');
}
```
**Purpose:** Verifies `await response.json()` works; returns actual data, not Promise

---

## Code Quality Observations

✓ **Good:** Guard clauses for null checks  
✓ **Good:** Early returns prevent cascading errors  
✓ **Good:** Consistent return values (false for failure, data or true for success)  
✗ **Weakness:** No error messages explaining why operations failed  
✗ **Weakness:** No logging for debugging failed operations  
✗ **Weakness:** Tests use `console.assert()` instead of formal testing framework  

---

## Time Breakdown

| Phase | Time | Completed |
|-------|------|-----------|
| Concept review (debugging methodology) | 10 min | ✅ |
| Exercise (identify & fix bugs) | 35 min | ✅ |
| AI review (validate fixes) | 10 min | ✅ |
| Test case writing | 5 min | ✅ |

**Total:** 60 minutes

---

## Reflection

This exercise reinforced that **systematic debugging catches all bugs**, not just the obvious ones. The most valuable lessons:

1. **Promises are containers** — Must understand when to await
2. **Null checks save data** — Defensive programming prevents silent failures
3. **Off-by-one is subtle** — Edge cases in array methods are dangerous
4. **Testing clarifies intent** — Writing assertions makes you think about expected behavior

**Confidence after:** High. Can identify and fix these bug types independently.

