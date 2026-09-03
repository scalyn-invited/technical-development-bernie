<?php

use App\Providers\AppServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;

return [
    SanctumServiceProvider::class,
    AppServiceProvider::class,
];
