<?php

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Loop;
use ReactphpX\RegisterCenter\Register;
use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

// Create event loop
$loop = Loop::get();

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

// Create and start registration center with logger
$center = new Register(8010, $loop, $logger);
$center->start();

$serverMiddleware = new ReactphpX\Service\ServerMiddleware($center, $logger);

$http = new React\Http\HttpServer($serverMiddleware);

$http->listen(new \React\Socket\SocketServer('0.0.0.0:8011'));

// http://

// Run the loop
$loop->run(); 