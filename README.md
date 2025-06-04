# ReactPHP X Micro Service

A high-performance service distribution system built with ReactPHP, designed to distribute service calls across multiple master nodes efficiently and evenly.

## Features

- Even distribution of service calls across available master nodes
- Real-time load balancing based on call counts
- Input validation for service calls
- JSON-based API interface
- Built with ReactPHP for high performance and non-blocking I/O

## Installation

Install via Composer:

```bash
composer require reactphp-x/micro-service -vvv
```

## Requirements

- PHP 8.0 or higher
- ReactPHP HTTP ^1.11
- ReactPHP X Register Center ^1.0

## Usage

### Basic Setup

```php
use ReactphpX\MicroService\ServerMiddleware;
use ReactphpX\RegisterCenter\Register;
use React\Http\HttpServer;
use React\Socket\SocketServer;

// Initialize the register center
$register = new Register(8010);

// Create the server middleware
$middleware = new ServerMiddleware($register);

// Setup HTTP server
$server = new HttpServer($middleware);

// Listen on port 8080
$socket = new SocketServer('127.0.0.1:8080');
$server->listen($socket);
```

### Making Service Calls

Send a POST request to your server with the following JSON structure:

```json
{
    "server_calls": {
        "service_name1": [
            {
                "method": "methodName",
                "params": ["param1", "param2"]
            },
            {
                "method": "anotherMethod",
                "params": []
            }
        ],
        "service_name2": [
            {
                "method": "someMethod",
                "params": {"key": "value"}
            }
        ]
    }
}
```

### Response Format

For successful requests, you'll receive a response with the distributed calls:

```json
{
    "service_name1": [
        "methodName" : result,
        "anotherMethod" : result
    ],
    "service_name2": [
        "someMethod" : result
    ]
}
```

For invalid requests, you'll receive a 400 Bad Request response:

error result

```json
{
    "code": 1,
    "errorCode": 500,
    "msg": "Error description here",
    "data": null
}
```

success result

```json
{
    "code": 0,
    "errorCode": 0,
    "msg": "Success",
    "data": {
       
    }
}
```


## Error Handling

The service validates all incoming requests and will return appropriate error messages for:

- Invalid server_calls format (must be an array)
- Invalid service names (must be strings)
- Invalid call format (must be arrays with method and params)
- Missing or invalid method names
- Missing or invalid parameters

## Load Distribution

The service automatically distributes calls across available master nodes using the following strategy:

1. Maintains a count of calls per master node
2. Assigns new calls to the least loaded node
3. Ensures even distribution of load across all available nodes

## License

MIT

## Author

wpjscc <wpjscc@gmail.com>

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request 