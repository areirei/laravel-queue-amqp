<?php

namespace Areirei\LaravelQueueAMQP\Tests;

use Illuminate\Support\Facades\Queue;
use areirei\LaravelQueueAMQP\LaravelAMQPQueue;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Areirei\LaravelQueueAMQP\LaravelQueueAMQPServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelQueueAMQPServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('queue.default', 'amqp');
        $app['config']->set('queue.connections.amqp', [
            'driver' => 'amqp',
            'queue' => 'default',
            
            'hosts' => [
                [
                    'host' => getenv('HOST'),
                    'port' => getenv('PORT'),
                    'vhost' => '/',
                    'user' => 'guest',
                    'password' => 'guest',
                ],
            ],
        ]);
    }

    /**
     * Return a queue connection instance.
     *
     * @param string|null $name
     * @return LaravelAMQPQueue
     */
    protected function connection(string $name = null): LaravelAMQPQueue
    {
        return Queue::connection($name);
    }
}
