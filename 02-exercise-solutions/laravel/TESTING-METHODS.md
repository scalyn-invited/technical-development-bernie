# Laravel Testing Methods — Quick Reference

Save time by knowing how to test before you code. These are the practical methods you'll use in exercises.

---

## 1. Verify Routes Exist — `php artisan route:list`

**Purpose:** Confirm all routes are registered and have correct names/methods.

```bash
cd laravel-app
php artisan route:list
```

**Output shows:**
```
GET    | items               | items.index    | App\Http\Controllers\ItemController@index
GET    | items/create        | items.create   | App\Http\Controllers\ItemController@create
POST   | items               | items.store    | App\Http\Controllers\ItemController@store
GET    | items/{item}        | items.show     | App\Http\Controllers\ItemController@show
GET    | items/{item}/edit   | items.edit     | App\Http\Controllers\ItemController@edit
PUT    | items/{item}        | items.update   | App\Http\Controllers\ItemController@update
DELETE | items/{item}        | items.destroy  | App\Http\Controllers\ItemController@destroy
```

**Use this for:** Proof that all 7 resource routes exist. Screenshot this.

---

## 2. Start the Dev Server

**Purpose:** Run the app locally so you can test routes in a browser or with HTTP tools.

```bash
php artisan serve
```

Server runs at `http://localhost:8000`

Press `Ctrl+C` to stop.

---

## 3. Test Routes in Browser

**Purpose:** Quick visual check that routes respond.

After `php artisan serve`, visit:

| Route | URL | What to expect |
|---|---|---|
| List | `http://localhost:8000/items` | List view or JSON |
| Create form | `http://localhost:8000/items/create` | Form or page |
| Show one | `http://localhost:8000/items/1` | Single item display |
| Edit form | `http://localhost:8000/items/1/edit` | Edit form |

**Note:** If a route returns a 404 or 500, check `php artisan route:list` first.

---

## 4. Test HTTP Methods with cURL (PowerShell)

**Purpose:** Test POST, PUT, DELETE and custom headers without a GUI.

### Test with missing header (should fail)
```powershell
curl -X GET http://localhost:8000/items
```

### Test with custom header (should pass)
```powershell
curl -X GET http://localhost:8000/items -H "X-Custom-Header: allowed"
```

### Test POST (create)
```powershell
curl -X POST http://localhost:8000/items `
  -H "Content-Type: application/json" `
  -H "X-Custom-Header: allowed" `
  -d '{"name":"Test Item"}'
```

### Test PUT (update)
```powershell
curl -X PUT http://localhost:8000/items/1 `
  -H "Content-Type: application/json" `
  -H "X-Custom-Header: allowed" `
  -d '{"name":"Updated"}'
```

### Test DELETE
```powershell
curl -X DELETE http://localhost:8000/items/1 `
  -H "X-Custom-Header: allowed"
```

---

## 5. Test with Thunder Client (VS Code Extension) — GUI Alternative

**Install:** Thunder Client extension in VS Code

**Advantages:**
- Visual request builder
- Save requests for reuse
- See headers and responses side-by-side
- No PowerShell syntax headaches

**Quick start:**
1. Open Thunder Client (sidebar icon)
2. Click "New Request"
3. Method: GET, URL: `http://localhost:8000/items`
4. Headers tab → Add `X-Custom-Header: allowed`
5. Send

---

## 6. Test Model Binding with Tinker — Interactive Shell

**Purpose:** Test that implicit route model binding works before hitting the route.

```bash
php artisan tinker
```

**Inside tinker shell:**

```php
// Create a test item (needs Item model and factory)
$item = App\Models\Item::factory()->create();

// See its ID
$item->id  // example: 5

// Now test the route: http://localhost:8000/items/5
// Laravel should automatically resolve {item} to this $item instance
```

Exit tinker with `exit`.

---

## 7. Test Route Ordering — Specific Routes First

**Purpose:** Verify that specific routes are matched before dynamic segments.

**Correct order (works):**
```php
Route::get('/items/create', [ItemController::class, 'create']);  // specific first
Route::get('/items/{item}', [ItemController::class, 'show']);    // dynamic last
```

**Wrong order (breaks):**
```php
Route::get('/items/{item}', [ItemController::class, 'show']);    // dynamic first
Route::get('/items/create', [ItemController::class, 'create']);  // never reached
```

**Test it:** Visit `http://localhost:8000/items/create`
- Correct order → shows create form
- Wrong order → tries to find an item with id='create' → 404

**Verification:** Check `php artisan route:list` to see the order.

---

## 8. Test Middleware with dd() and Log

**Purpose:** Debug whether middleware is being called and what's happening.

**In your middleware:**
```php
public function handle(Request $request, Closure $next)
{
    dd($request->headers->all());  // stop and dump all headers
    
    if (!$request->hasHeader('X-Custom-Header')) {
        return response()->json(['error' => 'Missing header'], 400);
    }
    
    return $next($request);
}
```

**In your controller:**
```php
public function store(Request $request)
{
    \Log::info('Store method reached', ['data' => $request->all()]);
    // ... rest of logic
}
```

**View logs:**
```bash
php artisan tail
```

---

## 9. Complete Testing Workflow for a Resource Controller

Follow this sequence for Exercise 3A:

### Phase 1: Setup (5 min)
1. Create ItemController: `php artisan make:controller ItemController --resource`
2. Create Item model: `php artisan make:model Item -m`
3. Register routes in `routes/web.php`: `Route::resource('items', ItemController::class);`

### Phase 2: Verify Routes (2 min)
```bash
php artisan route:list
# Take screenshot
```

### Phase 3: Test Middleware (5 min)
1. Create middleware: `php artisan make:middleware CheckCustomHeader`
2. Register on routes: `.middleware('check-custom-header')`
3. Test with cURL:
   ```powershell
   curl http://localhost:8000/items              # should fail
   curl http://localhost:8000/items -H "X-Custom-Header: ok"  # should work
   ```

### Phase 4: Test Model Binding (3 min)
1. Create a migration: Already done with model
2. Run migration: `php artisan migrate`
3. Test in tinker:
   ```php
   $item = App\Models\Item::factory()->create();
   # Visit http://localhost:8000/items/{id}
   ```

### Phase 5: Browser Test (2 min)
- `php artisan serve`
- Visit each route manually
- Verify middleware rejects without header

---

## 10. Quick Troubleshooting

| Problem | Check |
|---|---|
| Route not found (404) | `php artisan route:list` — is it registered? |
| Middleware not running | Is it registered in route or kernel? Check order. |
| Model binding fails | Does Item model exist? Is type hint correct? |
| Syntax error | `php artisan route:list` catches errors early |
| Header test fails | Use `curl -v` to see full request/response |
| Port 8000 in use | Use `php artisan serve --port=8001` |

---

## Storage Tips

- **Middleware:** `app/Http/Middleware/`
- **Controllers:** `app/Http/Controllers/`
- **Models:** `app/Models/`
- **Routes:** `routes/web.php` or `routes/api.php`
- **Migrations:** `database/migrations/`
- **Logs:** `storage/logs/laravel.log`
- **Database:** `database/database.sqlite` (SQLite)

---

**Last updated:** 2026-08-27  
**Framework:** Laravel 12.68.0
