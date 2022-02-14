<?php

/**
 * This is an example of queue connection configuration.
 * It will be merged into config/queue.php.
 * You need to set proper values in `.env`.
 */
return [

    'driver' => 'amqp',
    'queue' => env('AMQP_QUEUE', 'default'),
    'hosts' => [
        [
            'host' => env('AMQP_HOST', '127.0.0.1'),
            'port' => env('AMQP_PORT', 5672),
            'user' => env('AMQP_USER', 'guest'),
            'password' => env('AMQP_PASSWORD', 'guest'),
            'vhost' => env('AMQP_VHOST', '/'),
        ],
    ],

    'options' => [
        'queue' => [
            // ...

            'exchange' => 'application-x',
            'exchange_type' => 'topic',
            'exchange_routing_key' => '',
        ],
    ],
    // 'options' => [
    //     'queue' => [
    //         'job' => Areirei\LaravelQueueAMQP\AMQPJob::class,
    //     ],
    // ],

];
