<?php

namespace ReactphpX\Service;

use ReactphpX\RegisterCenter\Register;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

class ServiceCallDistributor
{
    private array $nodeCallCounts = [];
    private array $noServiceResults = [];

    public function __construct(private Register $register)
    {
        $this->register->on('service-registered', function ($masterId) {
            $this->nodeCallCounts[$masterId] = 0;
        });

        $this->register->on('service-removed', function ($masterId) {
            unset($this->nodeCallCounts[$masterId]);
        });
    }

    public function distributeServiceCalls(array $serviceCalls): array
    {
        $distributedCalls = [];
        $this->noServiceResults = [];



        foreach ($serviceCalls as $serviceName => $calls) {
            // Get available master nodes for this service
            $masterNodes = $this->register->getServicesMasterByServiceName($serviceName);
            if (empty($masterNodes)) {

                // Skip if no master nodes available for this service
                foreach ($calls as $call) {
                    $this->noServiceResults[$serviceName][$call['method']] = [
                        'code' => 1,
                        'msg' => 'no master nodes available for this service',
                        'data' => null,
                    ];
                }
                
                continue;
            }

            // Initialize call counts for new nodes
            foreach (array_keys($masterNodes) as $masterId) {
                if (!isset($this->nodeCallCounts[$masterId])) {
                    $this->nodeCallCounts[$masterId] = 0;
                }
            }

            // Distribute calls for this service
            foreach ($calls as $call) {
                // Find the node with the least number of calls
                $selectedMasterId = $this->getLeastLoadedNode(array_keys($masterNodes));
                
                // Add the call to the selected node
                if (!isset($distributedCalls[$selectedMasterId])) {
                    $distributedCalls[$selectedMasterId] = [];
                }
                $distributedCalls[$selectedMasterId][] = [
                    'service' => $serviceName,
                    'method' => $call['method'],
                    'params' => $call['params']
                ];
                
                // Increment the call count for the selected node
                $this->nodeCallCounts[$selectedMasterId]++;
            }
        }

        return $distributedCalls;
    }

    private function getLeastLoadedNode(array $nodeIds): string
    {
        $minCalls = PHP_INT_MAX;
        $selectedNode = null;

        foreach ($nodeIds as $nodeId) {
            $callCount = $this->nodeCallCounts[$nodeId] ?? 0;
            if ($callCount < $minCalls) {
                $minCalls = $callCount;
                $selectedNode = $nodeId;
            }
        }

        return $selectedNode;
    }

    public function getNoServiceResults(): array
    {
        return $this->noServiceResults;
    }

    public function callService(string $masterId, array $calls, array $results): array
    {
        foreach ($calls as $call) {
            $serviceName = $call['service'];
            $method = $call['method'];
            $params = $call['params'];
            $results[$call['service']][$method] = $this->callServiceOnMaster($masterId, $serviceName, $method, $params);
        }

        return $results;
    }

    private function callServiceOnMaster(string $masterId, string $serviceName, string $method, array $params): PromiseInterface
    {
        
        $deferred = new Deferred();
        $stream = $this->register->runOnMaster($masterId, function ($stream) use ($serviceName, $method, $params) {
            $stream->end(\ReactphpX\RegisterCenter\ServiceRegistry::execute($serviceName, $method, $params));
        });


        $stream->on('data', function ($data) use ($masterId, $deferred) {
            $deferred->resolve($data ?: null);
        });

        $stream->on('error', function ($error) use ($masterId, $deferred) {
            $deferred->resolve([
                'code' => 1,
                'errorCode' => $error->getCode(),
                'msg' => $error->getMessage(),
            ]);
        });

        $stream->on('close', function () use ($deferred) {
            $deferred->resolve([
                'code' => 1,
                'msg' => 'stream closed by master',
            ]);
        });

        return $deferred->promise();
    }

    public function getNodeCallCounts(): array
    {
        return $this->nodeCallCounts;
    }
} 