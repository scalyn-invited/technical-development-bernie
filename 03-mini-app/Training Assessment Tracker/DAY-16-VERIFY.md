# Day 16 verification

Run npm run build if needed, keep Laravel running on port 8000, and open http://127.0.0.1:8000/plans.
Use your existing local administrator. Do not run migrate:fresh: it removes saved work.

1. In Development plans, check member, status, baseline skill count and current open week. Filter draft/active/completed. Pagination appears only above 10 matching plans.
2. Open a draft or completed plan. Weekly editing must be unavailable.
3. Open an active plan for another member. Closed weeks have no editing controls; open weeks show objective, evidence and outcome fields.
4. On a disposable planned week, click Log outcome without evidence: expect an error under evidence. Enter evidence and score 101: expect an outcome-score error. Typed entries must remain.
5. Enter evidence and a valid score from 0–100, then Log outcome. Status becomes evidenced. Close week makes it read-only. Closure is irreversible; use test records.
6. After all existing weeks are closed, select an active baseline skill and create the next week. It appears once. Creation is unavailable while another week remains open.
7. In Open weeks, filter Awaiting evidence / Awaiting closure. Closed records disappear from the queue on refresh.
8. Sign in as a member: only their own plans/weeks are visible, with read-only progress.
9. Check narrow/mobile navigation; wide tables scroll inside their cards.

## Loading, empty and error states

In browser developer tools, throttle Network to observe loading and disabled submission fieldsets. Double-click must not create two requests.
Set Offline, then use an in-app refresh action or navigate between screens. Expect an error and Try again. Restore Online and retry.
A filter with no matching data shows explanatory empty text.
After a timed-out write, refresh before retrying: the server may have completed it.

Deterministic component tests also cover these states without resetting live data.

## Automated commands

- C:\\xampp\\php\\php.exe artisan test
- npm test
- npm run test:ui
- npm run build

No new Postman import is needed for this frontend exercise. Optional authenticated read:
GET {{base_url}}/weeks/open?status=evidenced&per_page=10&page=1
Use the existing bearer token and base_url http://127.0.0.1:8000/api without a conflicting environment override.
