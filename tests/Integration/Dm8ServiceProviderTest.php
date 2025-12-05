<?php

namespace LaravelDm8\Tests\Integration;

use Illuminate\Database\Connection;
use LaravelDm8\Dm8\Dm8Connection;
use LaravelDm8\Dm8\Dm8ServiceProvider;
use Orchestra\Testbench\TestCase;

class Dm8ServiceProviderTest extends TestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [Dm8ServiceProvider::class];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'dm');
        $app['config']->set('database.connections.dm', [
            'driver' => 'dm',
            'host' => 'localhost',
            'port' => '5236',
            'database' => 'TEST',
            'username' => 'SYSDBA',
            'password' => 'SYSDBA',
            'charset' => '',
            'prefix' => '',
            'prefix_schema' => '',
        ]);
    }

    /**
     * Test if service provider is registered correctly.
     */
    public function testServiceProviderRegistered()
    {
        $this->assertTrue($this->app->getProvider(Dm8ServiceProvider::class) instanceof Dm8ServiceProvider);
    }

    /**
     * Test if dm connection is registered correctly.
     */
    public function testDmConnectionRegistered()
    {
        $connection = $this->app['db']->connection('dm');
        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertInstanceOf(Dm8Connection::class, $connection);
    }

    /**
     * Test if query builder is instance of DmBuilder.
     */
    public function testQueryBuilderInstance()
    {
        $connection = $this->app['db']->connection('dm');
        $query = $connection->query();
        $this->assertInstanceOf('LaravelDm8\Dm8\Query\DmBuilder', $query);
    }
}
