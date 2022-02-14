<?php

namespace Areirei\LaravelQueueAMQP\Tests\Feature;

use areirei\LaravelQueueAMQP\LaravelAMQPQueue;
use AMQPConnection;

class ConnectorTest extends Areirei\LaravelQueueAMQP\Tests\TestCase
{
    public function testConnection(): void
    {
        $queue = $this->app['queue'];

        /** @var LaravelAMQPQueue */
        $connection = $queue->connection('amqp');

        $this->assertInstanceOf(LaravelAMQPQueue::class, $connection);
        $this->assertInstanceOf(AMQPConnection::class, $connection->getConnection());
        $this->assertTrue($connection->getConnection()->isConnected());
        $this->assertTrue($connection->getChannel()->isConnected());
    }
}
