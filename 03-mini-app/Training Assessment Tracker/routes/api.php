<?php

use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\WeeklyEntryController;
use Illuminate\Support\Facades\Route;

/*
| Public — no token required.
|
| Login uses the named `login` limiter (AppServiceProvider), keyed on email and
| IP together so one account's lockout cannot take the office offline.
| Registration is limited by IP: without it, the one endpoint that creates rows
| without a token is also the one endpoint nothing throttles.
*/
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

/*
| Authenticated — a valid Sanctum token required. Everything past this point
| returns the 401 envelope without one.
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    /*
    | Skills. No DELETE route exists: the catalogue is retired by deactivation,
    | so PATCH with is_active=false is the retirement path and DELETE returns
    | the 405 envelope rather than a 403 for a route that should not be there.
    */
    Route::get('/skills', [SkillController::class, 'index']);
    Route::post('/skills', [SkillController::class, 'store']);
    Route::patch('/skills/{skill}', [SkillController::class, 'update']);

    /*
    | Plans, and the two child collections written against them.
    |
    | scopeBindings() is the important part. Without it, PATCH on
    | /plans/1/weeks/9 resolves week 9 even when week 9 belongs to plan 2 — the
    | authorisation check would run against plan 1 while the write lands on
    | plan 2's row, which is the integrity rule defeated by a URL. Scoped, the
    | week must belong to the plan in the path or the binding fails and the
    | request is a 404.
    |
    | The child parameter is named {weekly_entry} rather than {week} on purpose:
    | Laravel resolves the parent relationship as Str::plural(Str::camel($name)),
    | so {week} would look for a weeks() relation that does not exist while
    | {weekly_entry} finds weeklyEntries(). The URL segment is /weeks either way.
    */
    Route::get('/plans', [PlanController::class, 'index']);
    Route::get('/plans/{plan}', [PlanController::class, 'show']);

    Route::post('/plans/{plan}/assessments', [AssessmentController::class, 'store']);

    Route::post('/plans/{plan}/weeks', [WeeklyEntryController::class, 'store']);
    Route::patch('/plans/{plan}/weeks/{weekly_entry}', [WeeklyEntryController::class, 'update'])
        ->scopeBindings();
});
