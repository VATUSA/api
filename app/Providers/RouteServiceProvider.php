<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';
    protected $namespaceapi = "App\Http\Controllers\API";
    protected $namespacelogin = "App\Http\Controllers\Login";

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        //

        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();
        $this->mapLoginRoutes();
        //$this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
             ->namespace($this->namespace)
             ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        if (env('APP_ENV') == "dev") {
            Route::domain("api.vatusa.devel")
                ->middleware(["web","api"])
                ->namespace($this->namespaceapi)
                ->group(base_path("routes/api.php"));
        } elseif (env('APP_ENV') == "livedev") {
            Route::domain("api.dev.vatusa.net")
                ->middleware(["web","api"])
                ->namespace($this->namespaceapi)
                ->group(base_path("routes/api.php"));
        } elseif (env('APP_ENV') == "staging") {
            Route::domain("api.staging.vatusa.net")
                ->middleware(["web","api"])
                ->namespace($this->namespaceapi)
                ->group(base_path("routes/api.php"));
        } else {
            Route::domain("api.vatusa.net")
                ->middleware(["web","api"])
                ->namespace($this->namespaceapi)
                ->group(base_path("routes/api.php"));
        }
    }

    protected function mapLoginRoutes()
    {
        if (env('APP_ENV') == "dev") {
            Route::domain("login.vatusa.devel")
                ->namespace($this->namespacelogin)
                ->middleware("web")
                ->group(base_path("routes/login.php"));
        } elseif (env('APP_ENV') == "livedev") {
            Route::domain("login.dev.vatusa.net")
                ->namespace($this->namespacelogin)
                ->middleware("web")
                ->group(base_path("routes/login.php"));
        } elseif (env('APP_ENV') == "staging") {
            Route::domain("login.staging.vatusa.net")
                ->namespace($this->namespacelogin)
                ->middleware("web")
                ->group(base_path("routes/login.php"));
        } else {
            Route::domain("login.vatusa.net")
                ->namespace($this->namespacelogin)
                ->middleware("web")
                ->group(base_path("routes/login.php"));
        }
    }
}
