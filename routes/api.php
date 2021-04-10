<?php

use Andyabih\LaravelFakeApi\Http\Controllers\LaravelFakeApiController;
use Illuminate\Support\Facades\Route;

Route::get("/{endpoint}/{key?}", [LaravelFakeApiController::class, 'get']);