<?php

use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlanController;
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
    | Day 11 authorisation surface. These two routes exist today so the Policies
    | are provable over real HTTP rather than only through the Gate:
    |   - the read path, which a member may follow only to their own plan;
    |   - the write path, which carries the integrity rule.
    | Day 12 completes them with Form Requests, API Resources and the remaining
    | six endpoints.
    */
    Route::get('/plans/{plan}', [PlanController::class, 'show']);
    Route::post('/plans/{plan}/assessments', [AssessmentController::class, 'store']);
});
