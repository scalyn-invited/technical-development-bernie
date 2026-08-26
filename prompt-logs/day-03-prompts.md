# Prompt Log: Day 3 — Broken Code Debug I
**Date:** Tuesday, 26 August 2026  
**Exercise:** JavaScript Broken Code Debugging  
**Total Prompts:** 5 (concept, exercise, AI review, teaching back, dev log)

---

## Prompt 1: Concept Review (0–10 min)

**AI Prompt:**
```
Provide a debugging framework and common JS bugs for the Day 3 exercise.
- Explain the debugging methodology (5 steps)
- List common JS bugs to watch for
- Context: Day 3 of a 30-day upskilling program, baseline 45.3%, target 85%+
```

**Response Given:**
```
Debugging Framework:
1. Read the error message (line number, stack trace, error type)
2. Trace backward (what code ran before? what values entered?)
3. Isolate the symptom (where does execution fail?)
4. Find root cause (undefined variable? async out of order?)
5. Fix and validate (does it solve the root cause or hide the symptom?)

Common JS Bugs:
- Scope/hoisting: Variables undefined in their scope
- Async timing: Promises/callbacks executing in wrong order
- Type mismatches: Passing wrong data type to function
- Mutation side effects: Accidentally modifying shared objects
- Null/undefined: Missing null checks before property access
```

**Purpose:** Frame the debugging methodology before diving into exercises. Set expectations for systematic problem-solving.

---

## Prompt 2: Exercise Code (10–45 min)

**AI Prompt:**
```
Provide a broken JavaScript module with at least 4 bugs for debugging practice.
- Module: userService with CRUD operations
- Bugs should cover: async/await, null handling, array methods, input validation
- Include marked bugs (// BUG 1, etc.) and at least one unmarked bug
- Provide usage examples that trigger the bugs
- Context: Practice identifying and fixing root causes, not just symptoms
```

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
```

**Purpose:** Provide realistic buggy code that requires systematic debugging. Bugs span multiple concepts (async, null handling, array methods, async context).

---

## Prompt 3: AI Review & Validation (45–55 min)

**AI Prompt:**
```
Review the user's bug fixes for correctness.
- Check if all 5 bugs were identified and fixed
- Validate root cause explanations
- Challenge areas: Bug 5 (async context), Promise vs value understanding
- Require: Complete test cases with assertions
- Context: User should understand why each fix works, not just apply it
```

**Review Conducted:**
- ✅ Validated all 4 primary bugs (await, null check, off-by-one, input validation)
- ⚠️ Noted Bug 5 solution was incomplete (just removing await isn't a real fix)
- 📝 Requested proper test cases with assertions (not just usage examples)
- 🧠 Asked teaching back questions to verify understanding

**Purpose:** Ensure user understands root causes, not just applying patches. Prepare for teaching-back reflection.

---

## Prompt 4: Teaching Back (AI Review Continuation)

**AI Prompts (as questions):**

1. **Promise Understanding:**
   ```
   What's the difference between a Promise and the resolved value?
   (Relates to Bug 1 — why await is necessary)
   ```
   **Expected Answer:** Promise is a container; await unwraps it to get the actual value.

2. **Defensive Programming:**
   ```
   Why is the null check in updateUser more important than just catching the error later?
   (Relates to Bug 2 — why fail-fast is better)
   ```
   **Expected Answer:** Null checks prevent cascading failures; catching errors later masks the root cause.

3. **Silent Failures:**
   ```
   What would happen if deleteUser silently failed on invalid ID—why is that dangerous?
   (Relates to Bug 3 — why validation is critical)
   ```
   **Expected Answer:** Silent deletion of wrong user causes data corruption with no error signal.

**Purpose:** Move from code-level fixes to conceptual understanding. Force articulation of *why* each fix matters.

---

## Prompt 5: Dev Log Reflection (55–60 min)

**AI Prompt:**
```
Write a comprehensive dev log reflecting on Day 3 exercise.
Structure:
1. What clicked? (concepts now clear)
2. What needs rebuild? (shaky areas)
3. Key takeaways (applicable to future work)
4. Skill progression (baseline 45.3% → today's learning)
5. Debugging methodology used (systematic vs. guessing)

Context: Use this reflection to guide tomorrow's Laravel fundamentals work.
```

**Output Structure Provided:**
- Types of bugs encountered
- Debugging techniques that worked
- What clicked vs. what needs rebuild
- Reflection on skill progression
- Key takeaways for future debugging

**Purpose:** Consolidate learning before moving to Day 4. Identify gaps for targeted follow-up.

---

## Prompt Timeline

| Time | Prompt Type | Duration | Status |
|------|-------------|----------|--------|
| 0–10 min | Concept Review | 10 min | ✅ Delivered |
| 10–45 min | Exercise Code | 35 min | ✅ Completed |
| 45–55 min | AI Review & Validation | 10 min | ✅ Done |
| 45–55 min | Teaching Back Questions | 5 min | ✅ Answered |
| 55–60 min | Dev Log Reflection | 5 min | ✅ Written |

---

## Prompt Quality Notes

✅ **What Worked:**
- Marked bugs (BUG 1, etc.) gave clear targets but still required diagnosis
- Root cause focus prevented "just add try/catch" fixes
- Teaching back forced articulation of concepts
- Multiple prompts (concept → exercise → review → reflect) built understanding progressively

⚠️ **Areas for Improvement:**
- Could provide initial error messages (what does the broken code output?)
- Could scaffold test case structure more explicitly (Jest vs console.assert)
- Could ask user to predict bugs before seeing solutions

---

## Follow-Up Prompts for Day 4

**Day 4 Context (Laravel Fundamentals I):**
- "How does Laravel's request/response cycle compare to the async patterns in JavaScript?"
- "Where would you validate user input in Laravel? (middleware? controller? model?)"
- "Test a Laravel route that accepts user data. What checks parallel the null checks we wrote today?"

**Reason:** Connect async/validation lessons from Day 3 to Day 4 Laravel work.

---

## Summary

**Total Prompts Used:** 5 structured prompts  
**Exercise Coverage:** Async/await, null handling, array edge cases, async context, input validation  
**Learning Path:** Concept → Practice → Validation → Reflection → Bridge to next day  
**Prompt Effectiveness:** High — User identified all bugs, wrote test cases, articulated root causes  

