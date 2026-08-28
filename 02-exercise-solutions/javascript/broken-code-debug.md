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


Root Cause: response.json() returns a Promise; without await, the variable holds the Promise object itself
Error Type: Would cause TypeError when trying to access properties on a Promise object
Learning: Promises must be awaited to access their resolved value