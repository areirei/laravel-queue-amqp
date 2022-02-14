<?php

namespace Areirei\LaravelQueueAMQP\Tests\Feature;

use AMQPConnection;

class QueueTest extends TestCase
{
    public function testConnection(): void
    {
        $this->assertInstanceOf(AMQPConnection::class, $this->connection()->getChannel()->getConnection());
    }
}
