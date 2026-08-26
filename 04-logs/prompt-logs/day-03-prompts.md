
---

## **File 4: prompt-logs/day-03-prompts.md**

```markdown
# Prompt Log: Day 3 — Broken Code Debug I
**Date:** Tuesday, 26 August 2026  
**Exercise:** JavaScript Broken Code Debugging  
**Total Prompts:** 5 (concept, exercise, AI review, teaching back, dev log)

---

## Prompt 1: Concept Review (0–10 min)

**AI Prompt:**

with the plan fed from the beginning, I ask Claude to start the day 3 of the plan and Claude provides a debugging framework and common JS bugs for the Day 3 exercise.

Explain the debugging methodology (5 steps)
List common JS bugs to watch for
Context: Day 3 of a 30-day upskilling program, baseline 45.3%, target 85%+


**Response Given:**

Debugging Framework:

Read the error message (line number, stack trace, error type)
Trace backward (what code ran before? what values entered?)
Isolate the symptom (where does execution fail?)
Find root cause (undefined variable? async out of order?)
Fix and validate (does it solve the root cause or hide the symptom?)

Common JS Bugs:

Scope/hoisting: Variables undefined in their scope
Async timing: Promises/callbacks executing in wrong order
Type mismatches: Passing wrong data type to function
Mutation side effects: Accidentally modifying shared objects
Null/undefined: Missing null checks before property access


---

## Prompt 2: Exercise Code (10–45 min)

**AI Prompt:**

"Provide a broken JavaScript module with at least 4 bugs for debugging practice."

Module: userService with CRUD operations
Bugs should cover: async/await, null handling, array methods, input validation
Include marked bugs (// BUG 1, etc.) and at least one unmarked bug
Provide usage examples that trigger the bugs
Context: Practice identifying and fixing root causes, not just symptoms


**Code Delivered:**

```javascript
// userService.js — BROKEN VERSION WITH 5 BUGS
const users = [];

// BUG 1: Missing await on Promise
async function fetchUserFromAPI(userId) {
  const response = await fetch(`/api/users/${userId}`);
  const user = response.json();  // Missing await
  return user;
}

// BUG 2: Potential null dereference
function updateUser(id, updates) {
  const user = users.find(u => u.id === id);
  Object.assign(user, updates);  // No null check
  return user;
}

// BUG 3: What if userId doesn't exist?
function deleteUser(userId) {
  const index = users.findIndex(u => u.id === userId);
  users.splice(index, 1);  // splice(-1, 1) deletes LAST user
}

// Usage example:
addUser({ name: 'Alice' });  // BUG 4: Missing email
await fetchUserFromAPI(1);    // BUG 5: await outside async

Purpose: Provide realistic buggy code that requires systematic debugging. Bugs span multiple concepts (async, null handling, array methods, async context).

Prompt 3: AI Review & Validation (45–55 min)

AI Prompt:
Review the user's bug fixes for correctness.
- Check if all 5 bugs were identified and fixed
- Validate root cause explanations
- Challenge areas: Bug 5 (async context), Promise vs value understanding
- Require: Complete test cases with assertions
- Context: User should understand why each fix works, not just apply it

Review Conducted:

✅ Validated all 4 primary bugs (await, null check, off-by-one, input validation)
⚠️ Noted Bug 5 solution was incomplete (just removing await isn't a real fix)
📝 Requested proper test cases with assertions (not just usage examples)
🧠 Asked teaching back questions to verify understanding