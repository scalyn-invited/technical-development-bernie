# Evidence Logs

Documentation of learning outcomes, validation, challenges, and breakthroughs during the upskilling program.

## Purpose

This log captures evidence that shows:
- How you validated exercise solutions
- Specific challenges and how you resolved them
- Key insights that led to progress
- Patterns in your learning
- Progress toward the 85% target across the six domains

## What to Document

1. **Learning Outcome** - A specific skill or concept you mastered
2. **Evidence** - Code, tests, or examples that prove understanding
3. **Validation Method** - How you confirmed you understood it
4. **Challenge Encountered** - What was initially confusing
5. **Resolution** - How you worked through it
6. **Domain** - Which of the six domains this relates to

## Naming Convention

Use topic/domain-based names: `evidence-js-promises.md`, `evidence-laravel-middleware.md`, etc.

## Example

```markdown
# Evidence Log - JavaScript Promises

**Domain:** JavaScript  
**Date:** 2026-08-24  

## Learning Outcome
Understand how to chain promises and handle errors with .catch()

## Evidence
Created a multi-step API call sequence using promise chains:
```javascript
fetch('/api/user')
  .then(res => res.json())
  .then(user => fetch(`/api/posts/${user.id}`))
  .then(res => res.json())
  .then(posts => console.log(posts))
  .catch(err => console.error('Chain failed:', err))
```

## Validation Method
- Tested in browser console with real API
- Deliberately triggered an error to verify .catch() works
- Refactored using async/await to compare approaches

## Challenge Encountered
Didn't understand why errors in one .then() propagate to .catch()

## Resolution
Realized the promise chain is a sequence—any rejection short-circuits to the nearest .catch().
Visualized it as a flowchart to solidify understanding.

---

**Why This Matters:** This evidence demonstrates working knowledge for the reassessment.
```

---

**Aggregation:** Each week, review your evidence logs to track domain progress toward 75%+ in each area.
