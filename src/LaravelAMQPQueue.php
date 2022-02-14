<?php

namespace Areirei\LaravelQueueAMQP;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue as BaseQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

use AMQPEnvelope;
use AMQPChannel;
use AMQPConnection;
use AMQPExchange;
use AMQPExchangeException;
use AMQPQueue;

class LaravelAMQPQueue extends BaseQueue implements QueueContract
{
    /**
     * @var AMQPConnection
     */
    protected $connection;

    /**
     * @var AMQPChannel
     */
    protected $channel;

    /**
     * The name of the queue.
     *
     * @var string
     */
    protected $default;

    /**
     * List of declared queues.
     *
     * @var array
     */
    protected $queues = [];

    /**
     * List of bound queues to exchanges.
     *
     * @var array
     */
    protected $boundQueues = [];

    /**
     * Current job being proccessed.
     *
     * @var LaravelAMQPJob
     */
    protected $currentJob;

    /**
     * @var array
     */
    protected $options;

    /**
     * @param AMQPConnection $connection
     * @param string $default
     */
    public function __construct(AMQPConnection $connection, string $default, array $options)
    {
        $this->connection = $connection;
        $this->channel = $this->createChannel($connection);
        $this->default = $default;
        $this->options = $options;
    }
    /**
     * {@inheritDoc}
     */
    public function size($queue = null): int
    {
        $queue = $this->getQueue($queue);

        return $queue->declareQueue();
    }

    /**
     * {@inheritDoc}
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $queue, $data), $queue);
    }

    /**
     * {@inheritDoc}
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        [$routeKey, $exchange, $exchangeType, $exchangeFlag, $attempts] = $this->publishProperties($queue, $options);
        
        //declare exchange
        $exchange = $this->getExchange($exchange, $exchangeType, $exchangeFlag);
        if ($exchange->getName()) {
            if (! $exchange->declareExchange()) {
                return false;
            }
        }
        
        //declare queue
        $queue = $this->getQueue($queue, $exchangeFlag);
        $queue->declareQueue();
        
        //publish
        $exchange->publish($payload, $routeKey, $exchangeFlag, $this->publishAttributes($payload, $attempts, $exchangeFlag));

        return $publishAttributes['correlation_id'] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->laterRaw($delay, $this->createPayload($job, $queue, $data), $queue);
    }

    /**
     * @param $delay
     * @param $payload
     * @param null $queue
     * @param integer $attempts
     * @return mixed
     */
    public function laterRaw($delay, $payload, $queue = null, $attempts = 0)
    {
        $ttl = $this->secondsUntil($delay) * 1000;
        //When no ttl just publish a new message
        if ($ttl <= 0) {
            return $this->pushRaw($payload, $queue, ['delay' => $delay, 'attempts' => $attempts]);
        }

        $exchange = $this->getExchange($this->getExchangeName(), $this->getExchangeType(), $this->getExchangeFlags());

        $destination = $this->getQueueName($queue).'.delay.'.$ttl;
        $destinationQueue = $this->getQueue($destination, 'durable');
        $destinationQueue->setArguments($this->getDelayQueueArguments($this->getQueueName($queue), $ttl));
        $destinationQueue->declareQueue();

        return $exchange->publish($payload, $destination, AMQP_NOPARAM, $this->getDelayPublishAttributes($ttl, $attempts));
    }

    /**
     * {@inheritDoc}
     */
    public function pop($queue = null)
    {
        $queue = $this->getQueue($queue);
        $queue->declareQueue();

        $exchange = $this->getExchange($this->getExchangeName(), $this->getExchangeType(), $this->getExchangeFlags());

        $message = $queue->get(AMQP_NOPARAM);
        if ($message instanceof AMQPEnvelope) {
            return $this->currentJob = new LaravelAMQPJob(
                $this->container,
                $this,
                $exchange,
                $this->connectionName,
                $queue,
                $message
            );
        }

        return null;
    }
    
    /**
     * @param AMQPConnection $connection
     * @return AMQPChannel
     */
    public function createChannel(AMQPConnection $connection) : AMQPChannel
    {
        $channel = new AMQPChannel($connection);
        $channel->setPrefetchCount(1);

        return $channel;
    }

    /**
     * @return AMQPConnection
     */
    public function getConnection(): AMQPConnection
    {
        return $this->connection;
    }

    /**
     * @return AMQPChannel
     */
    public function getChannel(): AMQPChannel
    {
        return $this->channel;
    }

    /**
     * @param string $queueName
     * @param int $flag
     * @return AMQPQueue
     */
    public function getQueue($queueName, $flag = null) : AMQPQueue
    {
        $queue = new AMQPQueue($this->channel);
        $queue->setName($this->getQueueName(($queueName)));
        $queue->setFlags($this->getFlag($flag));

        return $queue;
    }

    /**
     * Get the flag of the queue.
     *
     * @return integer
     */
    public function getFlag($flag) : int
    {
        $const = 'AMQP_'.strtoupper($flag ?: Arr::get($this->options, 'queue_flag', 'noparam'));

        return constant($const);
    }

    /**
     * Get the routing-key
     * The default routing-key is the given queue name.
     *
     * @param $queue
     * @return string
     */
    public function getRoutingKey($queue): string
    {
        return ltrim(sprintf(Arr::get($this->options, 'exchange_routeing_key') ?: '%s', $queue), '.');
    }

    /**
     * @param string|null $name
     * @return string
     */
    public function getQueueName(?string $name = null): string
    {
        return $name ?: $this->default;
    }

    /**
     * @param string|null $name
     * @return string
     */
    public function getExchangeName(?string $name = null): ?string
    {
        return $name ?: Arr::get($this->options, 'exchange') ?: null;
    }

    /**
     * Get the exchangeType, or AMQP_EX_TYPE_DIRECT as default.
     *
     * @param string|null $type
     * @return string
     */
    public function getExchangeType(?string $type = null) : string
    {
        $const = 'AMQP_EX_TYPE_'.strtoupper($type ?: Arr::get($this->options, 'exchange_type', 'direct'));

        return @constant($const) ?: AMQP_EX_TYPE_DIRECT;
    }

    /**
     * Get the exchange flag, or AMQP_NOPARAM as default.
     *
     * @param string|null $flag
     * @return integer
     */
    public function getExchangeFlags(?string $flag = null): int
    {
        $const = 'AMQP_'.strtoupper($flag ?: Arr::get($this->options, 'exchange_flag', 'noparam'));

        return @constant($const) ?: AMQP_NOPARAM;
    }

    /**
     * Returns true, if delayed messages should be prioritized.
     *
     * @return boolean
     */
    protected function isPrioritizeDelayed(): bool
    {
        return (bool) (Arr::get($this->options, 'prioritize_delayed') ?: false);
    }

    /**
     * Determine publish attributes.
     *
     * @param $payload
     * @param integer $attempts
     * @param integer $exchangeFlag
     * @return array
     */
    protected function publishAttributes($payload, int $attempts = 0, int $exchangeFlag = AMQP_AUTODELETE): array
    {
        $attributes = [ 'content_type' => 'application/json' ];
        
        if ($exchangeFlag == AMQP_DURABLE) {
            $attributes['delivery_mode'] = 2;
        }

        $currentPayload = json_decode($payload, true, 512);
        if (isset($currentPayload['id']) && $currentPayload['id']) {
            $attributes['correlation_id'] = $currentPayload['id'];
        }

        if ($this->isPrioritizeDelayed()) {
            $attributes['priority'] = $attempts;
        }

        if ($attempts) {
            $attributes['headers'] = [
                'laravel' => [
                    'attempts' => $attempts,
                ],
            ];
        }

        return $attributes;
    }
    /**
     * Determine all publish properties.
     *
     * @param $queue
     * @param array $options
     * @return array
     */
    protected function publishProperties($queue, array $options = []): array
    {
        $queue = $this->getQueueName($queue);
        $attempts = Arr::get($options, 'attempts') ?: 0;

        $routeKey = $this->getRoutingKey($queue);
        $exchange = $this->getExchangeName(Arr::get($options, 'exchange'));
        $exchangeFlag = $this->getExchangeFlags(Arr::get($options, 'exchange_flag'));
        $exchangeType = $this->getExchangeType(Arr::get($options, 'exchange_type'));

        return [$routeKey, $exchange, $exchangeType, $exchangeFlag, $attempts];
    }

    /**
     * Get the Delay queue arguments.
     *
     * @param string $name
     * @param integer $ttl
     * @return array
     */
    protected function getDelayQueueArguments(string $name, int $ttl): array
    {
        return [
            'x-dead-letter-exchange' => $this->getExchangeName() ?? '',
            'x-dead-letter-routing-key' => $name,
            'x-message-ttl' => $ttl,
            'x-expires' => $ttl * 2,
        ];
    }

    /**
     * Get the Delay publish attributes.
     *
     * @param integer $ttl
     * @param integer $attempts
     * @return array
     */
    protected function getDelayPublishAttributes(int $ttl, int $attempts): array
    {
        return  [
            'delivery_mode' => 2,
            'content_type' => 'application/json',
            'expiration' => $ttl * 2,
            'headers' => [
                'laravel' => [
                    'attempts' => $attempts,
                ],
            ],
        ];
    }
    /**
     * Checks if the exchange was already declared.
     *
     * @param string $name
     * @return boolean
     */
    protected function isExchangeDeclared(string $name): bool
    {
        return in_array($name, $this->exchanges, true);
    }
    /**
     * @param string $name
     * @param sgring $type
     * @param int $flag
     * @param array $argument
     * @return AMQPExchange
     */
    protected function getExchange(?string $name, string $type = AMQP_EX_TYPE_DIRECT, int $flag = AMQP_NOPARAM, array $argument = []): AMQPExchange
    {
        $exchange = new AMQPExchange($this->channel);

        if ($name) {
            $exchange->setName($name);
        }
        $exchange->setType($type);
        $exchange->setFlags($flag);
        if (count($argument)) {
            $exchange->setArguments($argument);
        }

        return $exchange;
    }
    /**
     * @param AMQPExchange $exchange
     * @return boolean
     */
    protected function declareExchange(AMQPExchange &$exchange): bool
    {
        //return the result about declare exchange
        try {
            return $exchange->declareExchange();
        } catch (AMQPExchangeException $e) {
            info($e->getMessage(), func_get_args());

            return false;
        }
    }

    /**
     * Create a payload array from the given job and data
     *
     * @param string|object $job
     * @param string $queue
     * @param mixed $data
     * @return array
     */
    protected function createPayloadArray($job, $queue, $data = ''): array
    {
        return array_merge(parent::createPayloadArray($job, $queue, $data), [
            'id' => $this->getRandomid(),
        ]);
    }

    /**
     * Get a random ID string.
     *
     * @return string
     */
    protected function getRandomid(): string
    {
        return Str::uuid();
    }

    /**
     * Close the connection
     *
     * @return void
     */
    public function close(): void
    {
        if ($this->currentJob && ! $this->currentJob->isDeletedOrReleased()) {
            $this->currentJob->reject();
        }

        $this->connection->disconnect();
    }
}
