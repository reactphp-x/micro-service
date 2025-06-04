<?php

namespace ReactphpX\Service;

use ReactphpX\RegisterCenter\Register;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use WyriHaximus\React\Stream\Json\JsonStream;
use React\Promise\Deferred;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ServerMiddleware
{
    private ServiceCallDistributor $serviceCallDistributor;
    public function __construct(private Register $register, private ?LoggerInterface $logger = null)
    {
        $this->serviceCallDistributor = new ServiceCallDistributor($this->register);
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(ServerRequestInterface $request)
    {
        $params = $request->getQueryParams() ?? [];

        $body = json_decode((string) $request->getBody(), true);

        $data = array_merge($params, $body);
        $serverCalls = $data['server_calls'] ?? [];

        $this->logger->debug('server_calls', ['server_calls' => $serverCalls, 'params' => $params, 'body' => $body]);

        // Validate server calls structure
        if (!is_array($serverCalls)) {
            $this->logger->debug('server_calls must be an array');
            return new Response(
                400,
                ['Content-Type' => 'application/json'],
                json_encode(['error' => 'server_calls must be an array'])
            );
        }

        if (empty($serverCalls)) {
            $this->logger->debug('server_calls is empty');
            return new Response(
                400,
                ['Content-Type' => 'application/json'],
                json_encode(['error' => 'server_calls is empty'])
            );
        }

        // Validate each service and its calls
        foreach ($serverCalls as $serviceName => $calls) {
            if (!is_string($serviceName)) {
                $this->logger->debug('Service name must be a string');
                return new Response(
                    400,
                    ['Content-Type' => 'application/json'],
                    json_encode(['error' => 'Service name must be a string'])
                );
            }

            if (!is_array($calls)) {
                $this->logger->debug("Calls for service '$serviceName' must be an array");
                return new Response(
                    400,
                    ['Content-Type' => 'application/json'],
                    json_encode(['error' => "Calls for service '$serviceName' must be an array"])
                );
            }

            foreach ($calls as $index => $call) {
                if (!is_array($call)) {
                    $this->logger->debug("Call at index $index for service '$serviceName' must be an array");
                    return new Response(
                        400,
                        ['Content-Type' => 'application/json'],
                        json_encode(['error' => "Call at index $index for service '$serviceName' must be an array"])
                    );
                }

                if (!isset($call['method']) || !is_string($call['method'])) {
                    $this->logger->debug("Method is required and must be a string for service '$serviceName'");
                    return new Response(
                        400,
                        ['Content-Type' => 'application/json'],
                        json_encode(['error' => "Method is required and must be a string for service '$serviceName'"])
                    );
                }

                if (!isset($call['params']) || !is_array($call['params'])) {
                    $this->logger->debug("Params is required and must be an array for service '$serviceName'");
                    return new Response(
                        400,
                        ['Content-Type' => 'application/json'],
                        json_encode(['error' => "Params is required and must be an array for service '$serviceName'"])
                    );
                }
            }
        }
        
        $distributedCalls = $this->serviceCallDistributor->distributeServiceCalls($serverCalls);

        $results = $this->serviceCallDistributor->getNoServiceResults();
        foreach ($distributedCalls as $masterId => $calls) {
            $results = $this->serviceCallDistributor->callService($masterId, $calls, $results);
        }

        return $this->getJsonPromise($results)->then(function ($results) {
            $this->logger->debug('results', ['results' => $results]);
            return new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode($results)
            );
        }, function ($error) {
            $this->logger->debug('error', ['error' => $error->getMessage()]);
            return new Response(
                500,
                ['Content-Type' => 'application/json'],
                json_encode(['error' => $error->getMessage()])
            );
        });

    }


    public function getJsonPromise($array = [])
    {

        $deferred = new Deferred();
        $buffer = '';
        $jsonStream = new JsonStream();
        $jsonStream->on('data', function ($data) use (&$buffer) {
            $buffer .= $data;
        });

        $jsonStream->on('end', function () use (&$buffer, $deferred) {
            $deferred->resolve(json_decode($buffer, true));
            $buffer = '';
        });

        $jsonStream->end($array);
        return $deferred->promise();
    }
}