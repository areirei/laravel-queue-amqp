<h1 align="center">Laravel Queue AMQP</h1>

<p align="center">
  <a href="https://packagist.org/packages/areirei/laravel-queue-amqp"><img src="https://img.shields.io/packagist/v/areirei/laravel-queue-amqp" alt="Latest Stable Version"></a>
  <a href="https://github.com/areirei/laravel-queue-amqp/actions"><img src="https://github.com/areirei/laravel-queue-amqp/workflows/Tests/badge.svg" alt="Actions Status"></a>
  <a href="https://github.com/areirei/laravel-queue-amqp/blob/main/LICENSE"><img src="https://img.shields.io/badge/license-MIT-informational" alt="License"></a>
  <a href="https://app.bors.tech/repositories/42103"><img src="https://bors.tech/images/badge_small.svg" alt="Bors enabled"></a>
</p>

<p align="center">⚡ The AMQP driver for Laravel Queue</p>

**PHP AMQP** is an [object-oriented PHP bindings](https://github.com/php-amqp/php-amqp) for the RabbitMQ C AMQP client library. 

## 🔧 Installation

Install the Plugin
```bash
composer require areirei/laravel-queue-amqp
```
Add connection to `config/queue.php`:
```
'connections' => [
    // ...

    'amqp' => [
    
       'driver' => 'amqp',
       'queue' => 'default',
   
       'hosts' => [
           [
               'host' => env('RABBITMQ_HOST', '127.0.0.1'),
               'port' => env('RABBITMQ_PORT', 5672),
               'user' => env('RABBITMQ_USER', 'guest'),
               'password' => env('RABBITMQ_PASSWORD', 'guest'),
               'vhost' => env('RABBITMQ_VHOST', '/'),
           ],
       ],
   
       'options' => [
           'queue' => [
               //'exchange' => 'default',
               //'exchange_flag' => 'noparam',
               //'exchange_type' => 'direct',
               //'exchange_routeing_key' => 'default',
           ],
       ],
        
    ],  
],
```

## 💡 Learn More

- **Laravel Queue**: If you wanna know how to use the queue. see the http://laravel.com/docs/queues.
- **PHP AMQP**: You can use more advance function by reading the documentation of AMQP PHP Client. https://github.com/php-amqp/php-amqp.
- **Rabbit C**: This is a C-language AMQP client library for the RabbitMQ broker. see the https://github.com/alanxz/rabbitmq-c.
