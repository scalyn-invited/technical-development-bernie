# Day 15 — Vue authentication

Vue 3 Composition API + Vue Router, compiled through the existing Laravel Vite
pipeline. The app is served on the same origin as /api, with /login and /workspace
Blade entry routes. Run npm install, npm run build, then php artisan serve.
For active development, run npm run dev in a second terminal.

The interface uses warm ivory, forest green, a CSS-drawn programme journey,
responsive layouts, labelled fields, visible focus states, password visibility,
busy states, inline validation errors and reduced-motion support. The overview
loads the actual authenticated profile; it is not a fabricated analytics dashboard.
Plan/skill management screens remain Day 16 work.

## Authentication decisions

- Central auth-client attaches a bearer token only to relative /api calls.
- sessionStorage persists across refreshes in the current tab. User details are
  revalidated through /me after a reload before a protected route opens.
- Storage failure falls back to memory with a warning; refreshing then signs out.
- Any protected request returning 401 clears authentication and navigates to login.
  Invalid login credentials remain a form error instead of causing an expiry loop.
- Requests time out after 12 seconds. Network errors show actionable messages.
- Logout calls the server and always clears local state. Failed server revocation
  is stated honestly; the user is not told that every token was invalidated.
- Only identity is stored in reactive memory; the password is cleared after login.
  No credentials are included in URLs or application logs.

sessionStorage remains readable by injected JavaScript: it is not XSS protection.
This is the deliberate token-based demo trade-off required by the day card.
For a first-party production SPA, prefer Sanctum's HttpOnly cookie/session flow,
with CSRF protection, HTTPS and a reviewed CSP. Closing the tab does not revoke
the server token; the backend's current token lifetime still applies.

References:
- https://vuejs.org/guide/quick-start
- https://router.vuejs.org/guide/advanced/navigation-guards.html
- https://cheatsheetseries.owasp.org/cheatsheets/HTML5_Security_Cheat_Sheet.html

## Verification

npm test runs isolated authentication-client checks. npm run build verifies Vue
compilation. php artisan test runs the existing backend regression suite.
Browser checks cover real login and refresh, visible wrong-password feedback,
logout and unauthenticated navigation; see Day 15 evidence for observed outcomes.

If a stopped Vite server leaves public/hot behind, Laravel may request unavailable
development assets even after a production build. Restart npm run dev or remove
that generated marker to serve the compiled assets. The stale marker found during
this build was removed; starting Vite recreates it.
## Day 16 screens

### Full-cycle follow-up

/register exposes the existing member-only registration endpoint and establishes
the normal session. /plans/new is an administrator-only form backed by POST /plans
and GET /members/eligible (paginated, name search, only id/name returned).
Creation saves the draft plus its nonempty scored baseline set transactionally.
PlanLifecycle uses existing assessment/activation/completion endpoints and returns
authoritative mutation results to the parent immediately. Completion updates the
read-only state before the separate comparison fetch, so a failed comparison
request cannot leave a completed plan editable. PlanComparison displays server
arithmetic, including retired skills and pending values.
See FULL-CYCLE-VERIFY.md. Existing saved data is not reset.

Protected routes: /plans, /plans/:plan and /open-weeks. Shared WorkspaceShell
provides desktop/mobile navigation. Resource state handling discards stale
responses after a filter change or unmount. Mutation forms retain failed input,
surface field-specific 422 errors and guard duplicate in-flight submissions.
Role-based UI is convenience only: API policies and progression rules enforce access.
The open-weeks API is authenticated and paginated; it exposes only permitted plans.
Use npm run test:ui for component checks and DAY-16-VERIFY.md for manual checks.
