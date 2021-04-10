<?php

namespace Andyabih\LaravelFakeApi;

use Andyabih\LaravelFakeApi\LaravelFakeApi;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LaravelFakeApiServiceProvider extends ServiceProvider {
    public function register() {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-fake-api.php', 'laravel-fake-api');

        $this->app->bind('laravel-fake-api', function($app) {
            return new LaravelFakeApi();
        });

        if (!Collection::hasMacro('paginate')) {
            Collection::macro('paginate', 
                function ($perPage = 15, $page = null, $options = []) {
                $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
                return (new LengthAwarePaginator(
                    $this->forPage($page, $perPage)->values()->all(), $this->count(), $perPage, $page, $options))
                    ->withPath('');
            });
        }
        
    }

    public function boot() {
        Route::group(['middleware' => 'api', 'prefix' => config('laravel-fake-api.base_endpoint')], function() {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        });
        
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laravel-fake-api.php' => config_path('laravel-fake-api.php'),
            ], 'config');
        }
    }
}