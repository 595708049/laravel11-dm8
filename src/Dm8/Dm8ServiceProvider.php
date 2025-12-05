<?php

namespace LaravelDm8\Dm8;

use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;
use LaravelDm8\Dm8\Connectors\DmConnector as Connector;

class Dm8ServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(__DIR__.'/../config/dm.php', 'database.connections');

        // Register database connection resolver
        Connection::resolverFor('dm', function ($connection, $database, $prefix, $config) {
            if (isset($config['dynamic']) && ! empty($config['dynamic'])) {
                call_user_func_array($config['dynamic'], [&$config]);
            }

            $connector = new Connector();
            $connection = $connector->connect($config);
            $db = new Dm8Connection($connection, $database, $prefix, $config);

            return $db;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/dm.php' => config_path('dm.php'),
        ], 'dm');
    }
}
