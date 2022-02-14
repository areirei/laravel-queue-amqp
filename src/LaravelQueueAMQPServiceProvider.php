<?php

namespace Areirei\LaravelQueueAMQP;

use Illuminate\Support\ServiceProvider;

class LaravelQueueAMQPServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/amqp.php',
            'queue.connections.amqp'
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        /** @var QueueManager $queue */
        $queue = $this->app['queue'];

        $queue->addConnector('amqp', function () {
            return new LaravelAMQPConnector($this->app['events']);
        });
    }
}
