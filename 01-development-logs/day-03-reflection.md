# Day 3 Development Log — Reflection & Learning
**Date:** Tuesday, 26 August 2026  
**Focus:** Broken Code Debug I — JavaScript Fundamentals  
**Duration:** 60 minutes (0–10 concept, 10–45 exercise, 45–55 AI review, 55–60 reflection)

---

## What Clicked ✅

1. **Promise vs. Resolved Value**
   - Clear understanding: Promises are containers; `await` unwraps them to get the actual data
   - Directly applicable: Fixed Bug 1 in `fetchUserFromAPI()`

2. **Defensive Null Checks**
   - Understand the value: Null checks prevent silent failures and data corruption
   - Applied in: `updateUser()` and `deleteUser()` to return `false` instead of crashing

3. **Off-by-One Logic Hazards**
   - Insight: `findIndex()` returns `-1` when not found; `splice(-1, 1)` deletes the LAST item (dangerous!)
   - Learning: Edge case validation is critical; boundaries are where bugs hide

4. **Test-Driven Thinking**
   - Practice: Writing assertions forces you to specify expected behavior
   - Benefit: Tests catch edge cases that code review misses

---

## What Needs Rebuild ⚠️

1. **Async/Await Scoping**
   - Current: Can use `await` inside async functions, but still shaky on when it's valid at top level
   - Next step: Practice async IIFE and `.then()` chaining as alternatives

2. **Promise Chaining vs Await**
   - Current: Both work, but mixing them causes confusion
   - Next step: Choose one style consistently; practice refactoring `.then()` chains to async/await

3. **Formal Test Structure**
   - Current: Using `console.assert()` for validation
   - Next step: Learn Jest or testing framework for proper unit test structure

---

## Bugs Found & Root Causes

| Bug | Location | Root Cause | Fix | Impact |
|-----|----------|-----------|-----|--------|
| 1 | `fetchUserFromAPI()` | Missing `await` on Promise | `await response.json()` | Returned Promise instead of data |
| 2 | `updateUser()` | No null check before `Object.assign()` | Added `if (!user) return false` | Would throw TypeError |
| 3 | `deleteUser()` | `splice(-1, 1)` deletes wrong item | Added `if (index === -1) return false` | Silent data loss |
| 4 | Usage example | Missing required email field | Added `email: 'alice@example.com'` | Validation check bypassed |
| 5 | Top-level code | `await` outside async context | Wrap in async IIFE or use `.then()` | Syntax error |

---

## Debugging Methodology Applied

✓ **Read the error stack** — Understood what each error type means  
✓ **Trace backward** — Followed code flow to identify where things go wrong  
✓ **Isolate the symptom** — Separated "what fails" from "why it fails"  
✓ **Find root cause** — Didn't just add try/catch; fixed the underlying issue  
✓ **Validate the fix** — Wrote test cases to ensure fix solves the problem  

---

## Skill Progression

**Baseline (45.3%)** → **After Day 3**

- **Async/await understanding:** Solidified (was shaky, now clear)
- **Error handling:** Improved (defensive null checks)
- **Debugging confidence:** Strong (can identify root causes)
- **Testing mindset:** Growing (thinking in assertions)
- **Type safety awareness:** Developing (catching type mismatches)

---

## Key Takeaways for Future Work

1. **Promises are containers** — `await` unwraps them; treat Promise and value as different types
2. **Fail fast** — Guard clauses (early returns) prevent cascading failures
3. **Edge cases hide bugs** — Boundaries (-1, null, undefined) are common failure points
4. **Test the sad path** — Tests for non-existent data catch silent failures
5. **Syntax ≠ Correctness** — Code may run but not do what you intended (fire-and-forget Promises)

---

## Reflection

Day 3 reinforced that **debugging is a systematic skill**, not guessing. By following the methodology (read error → trace path → find root cause → test the fix), I caught all 5 bugs and understood *why* they mattered.

The most valuable insight: **Silent failures are worse than crashes.** A TypeError tells you something's wrong; losing data silently ruins your app.

Ready for Day 4: Laravel Fundamentals I.