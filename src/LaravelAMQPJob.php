<?php
namespace Areirei\LaravelQueueAMQP;

use AMQPEnvelope;
use AMQPExchange;
use AMQPQueue;
use Illuminate\Support\Arr;
use Illuminate\Container\Container;
use Illuminate\Queue\Jobs\Job as BaseJob;
use Illuminate\Contracts\Queue\Job as JobContract;

class LaravelAMQPJob extends BaseJob implements JobContract
{
    /**
     * @var AMQPExchange
     */
    protected $amqpExchange;

    /**
     * @var AMQPQueue
     */
    protected $amqpQueue;

    /**
     * @var AMQPEnvelope
     */
    protected $amqpMessage;

    /**
     * @var LaravelAMQPQueue
     */
    protected $laravelQueue;

    /**
     * The JSON decoded version of "$amqpMessage"
     *
     * @var array
     */
    protected $decoded;

    /**
     * @param Container $container
     * @param LaravelAMQPQueue $laravelQueue
     * @param AMQPExchange $amqpExchange
     * @param string $connectionName
     * @param AMQPQueue $amqpQueue
     * @param AMQPEnvelope $amqpMessage
     */
    public function __construct(
        Container $container,
        LaravelAMQPQueue $laravelQueue,
        AMQPExchange $amqpExchange,
        string $connectionName,
        AMQPQueue $amqpQueue,
        AMQPEnvelope $amqpMessage
    ) {
        $this->container = $container;
        $this->laravelQueue = $laravelQueue;

        $this->amqpExchange = $amqpExchange;
        $this->connectionName = $connectionName;
        $this->amqpQueue = $amqpQueue;
        $this->amqpMessage = $amqpMessage;
        $this->decoded = $this->payload();
    }

    /**
     * {@inheritDoc}
     */
    public function getJobId()
    {
        return $this->decoded['id'] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function attempts(): int
    {
        $laravelAttempts = Arr::get($this->amqpMessage->getHeaders(), 'laravel.attempts', 0);
        
        return $laravelAttempts + 1;
    }
    /**
     *  {@inheritDoc}
     */
    public function getRawBody(): string
    {
        return $this->amqpMessage->getBody();
    }

    /**
     * @return string
     */
    public function getQueue(): string
    {
        return $this->amqpQueue->getName();
    }

    /**
     * {@inheritDoc}
     */
    public function delete(): void
    {
        parent::delete();

        if (! $this->failed) {
            $this->amqpQueue->ack($this->amqpMessage->getDeliveryTag());
        }
    }

    /**
     * Release the job back into the queue
     *
     * @param int $delay
     * @return void
     */
    public function release($delay = 0): void
    {
        parent::release();
        
        $this->laravelQueue->laterRaw($delay, $this->amqpMessage->getBody(), $this->amqpQueue->getName(), $this->attempts());
        
        $this->amqpQueue->ack($this->amqpMessage->getDeliveryTag());
    }
    
    /**
     * Reject the message.
     *
     * @return void
     */
    public function reject(): void
    {
        $this->amqpQueue->reject($this->amqpMessage->getDeliveryTag());
    }

    /**
     * {@inheritDoc}
     */
    public function markAsFailed(): void
    {
        parent::markAsFailed();

        //If the job has failed, we will reject the message.
        $this->reject();
    }
}
