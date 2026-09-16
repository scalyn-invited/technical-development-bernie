<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'app');
Route::view('/login', 'app')->name('login');
Route::view('/workspace', 'app');
Route::view('/plans', 'app');
Route::view('/plans/new', 'app');
Route::view('/register', 'app');
Route::view('/plans/{plan}', 'app')->whereNumber('plan');
Route::view('/open-weeks', 'app');
