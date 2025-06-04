<?php

require __DIR__ . '/../vendor/autoload.php';

use ReactphpX\Service\Client;
use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

// Create logger
$logger = new Logger('registration-center');
$handler = new StreamHandler('php://stdout', Level::Debug);
$handler->setFormatter(new LineFormatter(
    "[%datetime%] %channel%.%level_name%: %message% %context%\n",
    null,
    true,
    true
));
$logger->pushHandler($handler);

// success
$response = Client::call('127.0.0.1:8011', 'hello-wrold', 'sayHello2', ['name' => 'John']);

$response->then(function ($response) use ($logger) {
    $logger->debug('call', [
        'status' => $response->getStatusCode(),
        'body' => (string) $response->getBody(),
    ]);
}, function ($error) use ($logger) {
    $logger->error($error->getMessage());
});

// success
Client::callMulti('127.0.0.1:8011', [
    'hello-wrold' => [
        [
            'method' => 'sayHello2',
            'params' => ['name' => 'John']
        ]
    ]
])->then(function ($response) use ($logger) {
    $logger->debug('callMulti', [
        'status' => $response->getStatusCode(),
        'body' => (string) $response->getBody(),
    ]);
}, function ($error) use ($logger) {
    $logger->error($error->getMessage());
});

// error
Client::callMulti('127.0.0.1:8011', [
    'hello-wrold' => [
        [
            'method' => 'sayHello3',
            'params' => ['name' => 'John']
        ]
    ]
])->then(function ($response) use ($logger) {
    $logger->debug('callMulti', [
        'status' => $response->getStatusCode(),
        'body' => (string) $response->getBody(),
    ]);
}, function ($error) use ($logger) {
    $logger->error($error->getMessage());
});