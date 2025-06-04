<?php

namespace ReactphpX\Service;

use React\Http\Browser;

class Client
{
    public static function call(string $host, string $serviceName, string $method, array $params = [])
    {
        $browser = new Browser();
        return $browser->post('http://' . $host . '/server_calls', [
            'Content-Type' => 'application/json',
        ], json_encode([
            'server_calls' => [
                $serviceName => [
                    [
                        'method' => $method,
                        'params' => $params
                    ]
                ]
            ]
        ]));
    }

    public static function callMulti(string $host, array $serverCalls)
    {
        $browser = new Browser();
        return $browser->post('http://' . $host . '/server_calls', [
            'Content-Type' => 'application/json',
        ], json_encode(['server_calls' => $serverCalls]));
    }
}