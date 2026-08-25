## Initial Prompt
"Explain promises, async/await, and fetch to a working developer in under 400 words. Cover what problem each solves, the correct usage, and the two mistakes developers most commonly make with them. No exercises, no code beyond short illustrative fragments. Specifically, clarify why fetch does NOT reject on a 404 or 500 HTTP response."

## AI Response
Promises, Async/Await, and Fetch
What each solves

Promises solve the callback-hell problem. Before promises, asynchronous JavaScript nested callbacks deeply, making code hard to follow. A promise represents a value that will exist in the future — either resolved (success) or rejected (failure).

Async/await is syntax sugar over promises. It lets you write asynchronous code that reads like synchronous code. async function returns a promise; await pauses execution until that promise settles.

Fetch is the modern way to make HTTP requests from the browser or Node. It returns a promise that resolves when the HTTP response arrives — regardless of whether the response was a success (200) or an error (404, 500).

Correct usage
// Promise chain
fetch('/api/users')
  .then(response => response.json())
  .then(data => console.log(data))
  .catch(err => console.error('Network failed:', err));

// Async/await (cleaner)
async function getUsers() {
  try {
    const response = await fetch('/api/users');
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.json();
    console.log(data);
  } catch (err) {
    console.error('Failed:', err);
  }
}
The critical catch: fetch doesn't reject on 404 or 500

This is the biggest mistake. Fetch only rejects on network failure — when the request never reaches the server. A 404, 500, or any HTTP error status? Fetch resolves successfully with that status code in response.status. You must check response.ok or the status code manually.

// WRONG — this "succeeds" on a 404
const response = await fetch('/api/users/999');
const data = await response.json(); // Works even if 404!

// RIGHT — check response.ok
const response = await fetch('/api/users/999');
if (!response.ok) throw new Error(`HTTP ${response.status}`);
const data = await response.json();
Two common mistakes
Treating fetch rejection as an HTTP error. Developers expect catch to fire on a 404. It won't — only network failures (DNS, connection lost, timeout) trigger catch. You must check response.ok.
Forgetting to return from .then() in promise chains. If you don't return a value from one .then(), the next one receives undefined. This breaks error propagation and makes bugs invisible.

## Validation
Acceptance criteria:

Handles the loading state
Handles a network failure
Handles a non-2xx response distinctly from a network failure
Handles a valid-but-empty result set
Fetches three resources concurrently with Promise.all and still reports which one failed
Nothing is left as an unhandled rejection
Includes AbortController with a 5-second timeout



## Outcome
Built a Single-file Node script fetching three JSONPlaceholder resources concurrently with 5-sec timeout and structured error handling

Output sample in 02-exercise solutions folder, week1-day2-promise.js

## Learnings

fetch resolves on any HTTP status; only network errors cause rejection. Promise.allSettled lets me catch and report individual failures.

## AI Output Validation

**Prompt A (Concept Review):** Explanations validated against MDN fetch documentation and Promise spec. Claims about fetch not rejecting on 404/500 confirmed in live testing.

**Prompt C (Review & Challenge):** Senior review identified three real bugs (string comparison typo, object response handling, malformed JSON). All findings verified as legitimate issues in the code.

**Overall quality:** High. AI explanations were accurate; review findings were actionable and correct.

---

*Session complete. Code built unaided. Ready for Day 3.*

