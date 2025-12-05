<?php

namespace LaravelDm8\Dm8;

use Illuminate\Validation\ValidationServiceProvider;
use LaravelDm8\Dm8\Validation\Dm8DatabasePresenceVerifier;

class Dm8ValidationServiceProvider extends ValidationServiceProvider
{
    /**
     * Register the presence verifier implementation.
     *
     * @return void
     */
    protected function registerPresenceVerifier()
    {
        $this->app->singleton('validation.presence', function ($app) {
            return new Dm8DatabasePresenceVerifier($app['db']);
        });
    }
}
