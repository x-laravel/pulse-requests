<?php

namespace XLaravel\PulseRequests\Tests;

use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\PulseServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use XLaravel\PulseRequests\PulseRequestsServiceProvider;
use XLaravel\PulseRequests\Recorders\Requests;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../vendor/laravel/pulse/database/migrations');

        Pulse::startRecording();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            PulseServiceProvider::class,
            PulseRequestsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('pulse.recorders.'.Requests::class, []);

        $app['config']->set('pulse.ingest.driver', 'storage');
        $app['config']->set('pulse.storage.driver', 'database');
        $app['config']->set('pulse.storage.database.connection', 'sqlite');
    }
}
