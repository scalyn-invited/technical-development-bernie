<?php
/**
 * BUGGY ROUTES FOR EXERCISE 3B
 * These routes have 4 deliberately introduced bugs.
 * Paste these into routes/web.php and diagnose using:
 * - php artisan route:list
 * - dd() in middleware/controller
 * - storage/logs/laravel.log
 *
 * DO NOT read this file's comments to find bugs.
 * DO diagnose by running and testing.
 */

use App\Http\Controllers\BuggyPostController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Specific routes first, then dynamic routes
Route::middleware('check-custom-header')->group(function () {
    Route::get('/posts', [BuggyPostController::class, 'index']);
    Route::get('/posts/create', [BuggyPostController::class, 'create']);
    Route::post('/posts', [BuggyPostController::class, 'store']); 
    Route::get('/posts/{post}', [BuggyPostController::class, 'show']); 
    Route::get('/posts/{post}/edit', [BuggyPostController::class, 'edit']);
    Route::put('/posts/{post}', [BuggyPostController::class, 'update']);
    Route::delete('/posts/{post}', [BuggyPostController::class, 'destroy']);
});
