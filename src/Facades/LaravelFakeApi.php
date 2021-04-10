<?php

namespace Andyabih\LaravelFakeApi\Facades;

use Illuminate\Support\Facades\Facade;

class LaravelFakeApi extends Facade {
    protected static function getFacadeAccessor() {
        return 'laravel-fake-api';
    }
}