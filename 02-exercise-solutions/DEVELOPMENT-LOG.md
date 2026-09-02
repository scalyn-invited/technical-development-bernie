# Development Log — 30-Day Technical Development Programme

## Day 7 — Wednesday 2 September — REST API design: resources, status codes, validation
**Built:**            REST API for Orders entity with OrderResource, OrderResourceCollection, Form Request validation, and consistent error envelope

**Learned:**          Eloquent Resources shape model output consistently; API routes require explicit CSRF exemption in Laravel 11; FormRequest authorize() method blocks requests at 403 (not validation stage); mass-assignment requires fillable properties

**Got wrong:**        Initial CSRF middleware configuration attempts (tried middleware aliases, withoutMiddleware syntax, custom middleware skipping); FormRequest authorize() was returning false, blocking all POST requests; NOT NULL constraint on placed_at column required inclusion in POST payload

**Attempted self-fix:**  Tried multiple approaches to exempt API routes from CSRF before seeking help: modified routes/api.php middleware groups, checked bootstrap/app.php for proper api routing configuration, attempted custom middleware to skip CSRF for api/* paths, investigated CheckCustomHeader middleware interaction. Discovered issues systematically (CSRF → 403 → 500 errors) rather than guessing.

**AI output rejected:**   Initial middleware configuration suggestions were incomplete; had to iterate on bootstrap/app.php structure to find working solution (validateCsrfTokens except array)

**Still cannot explain:**  Why the initial statefulApi() + middleware setup didn't work as expected; whether Laravel 11 has changed how api routes should be registered compared to Laravel 10

**Evidence:**         
- OrderController with store/show/update/destroy returning correct status codes
- OrderResource with pagination metadata in ResourceCollection  
- Consistent error envelope used by all failure paths (404 test screenshot)
- Test results: GET 200, POST 201, GET 404 with envelope
- Commit: [pending after review]

**Applied to client work:** Not yet — this was foundational API design, but the error envelope pattern will apply to the internal leave-request API next week
