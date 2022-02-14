<?php
namespace Areirei\LaravelQueueAMQP;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Arr;
use AMQPConnection;

class LaravelAMQPConnector implements ConnectorInterface
{
    /**
     * @var Dispatcher
     */
    private $dispatcher;

    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Create a queue connection.
     *
     * @param array $config
     * @return LaravelAMQPQueue
     */
    public function connect(array $config): QueueContract
    {
        $connection = $this->createConnection($config);
        
        $queue = $this->createQueue($connection, $config['queue'], Arr::get($config, 'options.queue', []));

        //@todo Illuminate\Queue\Events\JobFailed
        
        $this->dispatcher->listen(WorkerStopping::class, static function () use ($queue): void {
            $queue->close();
        });

        return $queue;
    }
    
    /**
     * @param array $config
     * @return AMQPConnection
     */
    protected function createConnection(array $config): AMQPConnection
    {
        $connection = new AMQPConnection(
            Arr::shuffle(Arr::get($config, 'hosts', []))
        );
        $connection->connect();

        return $connection;
    }

    /**
     * @param AMQPConnection $connection
     * @param string $queue
     * @return LaravelAMQPQueue
     */
    protected function createQueue(AMQPConnection $connection, string $queue, array $options = []): QueueContract
    {
        return new LaravelAMQPQueue($connection, $queue, $options);
    }
}
