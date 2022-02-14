<?php

namespace Areirei\LaravelQueueAMQP\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Areirei\LaravelQueueAMQP\Tests\Mocks\TestJob;
use Areirei\LaravelQueueAMQP\LaravelAMQPJob;
use Areirei\LaravelQueueAMQP\Tests\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function testPushRaw(): void
    {
        Queue::pushRaw($payload = Str::random());
        
        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());
        $this->assertSame(1, $job->attempts());
        $this->assertInstanceOf(LaravelAMQPJob::class, $job);
        $this->assertSame($payload, $job->getRawBody());

        $this->assertNull($job->getJobId());

        $job->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testPush(): void
    {
        Queue::push(new TestJob());

        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());
        $this->assertSame(1, $job->attempts());
        $this->assertInstanceOf(LaravelAMQPJob::class, $job);
        $this->assertSame(TestJob::class, $job->resolveName());
        $this->assertNotNull($job->getJobId());

        $payload = $job->payload();

        $this->assertSame(TestJob::class, $payload['displayName']);
        $this->assertSame($job->getJobId(), $payload['id']);

        $job->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testLaterRaw(): void
    {
        $payload = Str::random();
        $data = [Str::random() => Str::random()];

        Queue::later(3, $payload, $data);

        sleep(1);

        $this->assertSame(0, Queue::size());
        $this->assertNull(Queue::pop());

        sleep(3);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());

        $this->assertInstanceOf(LaravelAMQPJob::class, $job);
        $this->assertSame($payload, $job->getName());

        $body = json_decode($job->getRawBody(), true);
        
        $this->assertSame($payload, $body['displayName']);
        $this->assertSame($payload, $body['job']);
        $this->assertSame($data, $body['data']);

        $job->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testLater(): void
    {
        Queue::later(3, new TestJob());

        sleep(1);

        $this->assertSame(0, Queue::size());
        $this->assertNull(Queue::pop());

        sleep(3);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());

        $this->assertInstanceOf(LaravelAMQPJob::class, $job);

        $body = json_decode($job->getRawBody(), true);
        
        $this->assertSame(TestJob::class, $body['displayName']);
        $this->assertSame(TestJob::class, $body['data']['commandName']);
        $this->assertNotNull($job->getJobId());

        $job->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testReleaseRaw(): void
    {
        Queue::pushRaw(Str::random());

        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());
        $this->assertSame(1, $job->attempts());

        for ($attempt = 2; $attempt <= 4; $attempt++) {
            $job->release();

            sleep(1);

            $this->assertSame(1, Queue::size());

            $job = Queue::pop();
            
            $this->assertSame($attempt, $job->attempts());
        }
        $job->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testRelease(): void
    {
        Queue::push(new TestJob());

        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());
        $this->assertSame(1, $job->attempts());

        for ($attempt = 2; $attempt <= 4; $attempt++) {
            $job->release();

            sleep(1);

            $this->assertSame(1, Queue::size());

            $job = Queue::pop();

            $this->assertSame($attempt, $job->attempts());
        }
        $job->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testReleaseWithDelayRaw(): void
    {
        Queue::pushRaw($payload = Str::random());

        sleep(1);

        $this->assertSame(1, Queue::size());
        $this->assertNotNull($job = Queue::pop());
        $this->assertSame(1, $job->attempts());

        for ($attempt = 2; $attempt <= 3; $attempt++) {
            $job->release(4);

            sleep(1);

            $this->assertSame(0, Queue::size());
            $this->assertNull(Queue::pop());

            sleep(4);

            $this->assertSame(1, Queue::size());

            $job = Queue::pop();

            $this->assertSame($attempt, $job->attempts());
        }
        $job->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testReleaseInThePast(): void
    {
        Queue::push(new TestJob());

        $job = Queue::pop();
        $job->release(-3);

        sleep(1);

        $this->assertInstanceOf(LaravelAMQPJob::class, $job = Queue::pop());

        $job->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testReleaseAndReleaseWithDelayAttempts(): void
    {
        Queue::push(new TestJob());

        $job = Queue::pop();
        $job->release();

        sleep(1);
        $job = Queue::pop();
        $this->assertSame(2, $job->attempts());
        $job->release(3);

        sleep(4);
        $job = Queue::pop();
        $this->assertSame(3, $job->attempts());

        $job->delete();
        $this->assertSame(0, Queue::size());
    }

    public function testFailed(): void
    {
        Queue::push(new TestJob());

        $job = Queue::pop();
        $job->fail(new RuntimeException($job->resolveName().' has an exception.'));

        sleep(1);

        $this->assertSame(true, $job->hasFailed());
        $this->assertSame(true, $job->isDeleted());
        $this->assertSame(0, Queue::size());
        $this->assertNull(Queue::pop());
    }
}
