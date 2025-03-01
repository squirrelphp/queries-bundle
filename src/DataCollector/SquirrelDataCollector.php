<?php

namespace Squirrel\QueriesBundle\DataCollector;

use Squirrel\Connection\ConnectionInterface;
use Squirrel\Connection\LargeObject;
use Squirrel\Connection\Log\ConnectionLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

final class SquirrelDataCollector extends DataCollector
{
    /** @var array<string, ConnectionLogger> */
    private readonly array $loggers;

    /** @var array<string, array<string, array{query: string, executionMS: float|int, executionPercent: float, count: int, index: int, values: Data}>>|null */
    private ?array $groupedQueries = null;

    /** @param array<array{connection: ConnectionInterface, services: string[]}> $connectionsWithServices */
    public function __construct(array $connectionsWithServices)
    {
        $loggers = [];
        $connections = [];

        foreach ($connectionsWithServices as $connectionName => $connectionDetails) {
            if ($connectionDetails['connection'] instanceof ConnectionLogger) {
                $loggers[$connectionName] = $connectionDetails['connection'];
                $connections[$connectionName] = '"' . \implode('", "', $connectionDetails['services']) . '"';
            }
        }

        $this->loggers = $loggers;

        $this->data = [
            'connections' => $connections,
            'queries' => [],
        ];
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data['queries'] = [];

        foreach ($this->loggers as $name => $logger) {
            $this->data['queries'][$name] = $this->normalizeQueries($logger->getLogs());
        }

        $this->groupedQueries = null;
    }

    public function getGroupedQueries(): array
    {
        if ($this->groupedQueries !== null) {
            return $this->groupedQueries;
        }

        $this->groupedQueries = [];

        $totalExecutionMS = 0;

        foreach ($this->data['queries'] as $connectionName => $queries) {
            $groupedQueries = [];

            foreach ($queries as $index => $query) {
                $key = $query['query'];

                $groupedQueries[$key] ??= $query;
                $groupedQueries[$key]['executionMS'] ??= 0;
                $groupedQueries[$key]['count'] ??= 0;
                $groupedQueries[$key]['index'] ??= $index;

                $groupedQueries[$key]['executionMS'] += $query['executionMS'];
                $groupedQueries[$key]['count']++;

                $totalExecutionMS += $query['executionMS'];
            }

            \usort($groupedQueries, static function (array $a, array $b) {
                if ($a['executionMS'] === $b['executionMS']) {
                    return 0;
                }

                return $a['executionMS'] < $b['executionMS'] ? 1 : -1;
            });

            $this->groupedQueries[$connectionName] = $groupedQueries;
        }

        foreach ($this->groupedQueries as $connectionName => $queries) {
            foreach ($queries as $i => $query) {
                $this->groupedQueries[$connectionName][$i]['executionPercent'] = $this->executionTimePercentage($query['executionMS'], $totalExecutionMS);
            }
        }

        return $this->groupedQueries;
    }

    private function executionTimePercentage(float $executionTimeMS, float $totalExecutionTimeMS): float
    {
        if ($totalExecutionTimeMS < PHP_FLOAT_EPSILON) {
            return 0;
        }

        return $executionTimeMS / $totalExecutionTimeMS * 100;
    }

    public function getGroupedQueryCount(): int
    {
        $count = 0;

        foreach ($this->getGroupedQueries() as $connectionGroupedQueries) {
            $count += \count($connectionGroupedQueries);
        }

        return $count;
    }

    /** @return array<string, string> */
    public function getConnections(): array
    {
        return $this->data['connections'];
    }

    public function reset(): void
    {
        $this->data['queries'] = [];

        foreach ($this->loggers as $logger) {
            $logger->resetLogs();
        }
    }

    public function getQueryCount(): int
    {
        return \intval(\array_sum(\array_map('count', $this->data['queries'])));
    }

    public function getQueries(): array
    {
        return $this->data['queries'];
    }

    public function getTime(): float
    {
        $time = 0;

        foreach ($this->data['queries'] as $queries) {
            foreach ($queries as $query) {
                $time += $query['executionMS'];
            }
        }

        return $time;
    }

    public function getName(): string
    {
        return 'squirrel';
    }

    /**
     * @param list<array{query: string, values: array<int|float|string|bool|null|LargeObject>, time: int}> $queries
     * @return list<array{query: string, values: Data, executionMS: float, explainable: bool}>
     */
    private function normalizeQueries(array $queries): array
    {
        $sanitizedQueries = [];

        foreach ($queries as $query) {
            $sanitizedQuery = [
                'query' => $query['query'],
                'executionMS' => $query['time'] / 1_000_000,
                'explainable' => true,
            ];

            $queryValues = [];

            foreach ($query['values'] as $key => $value) {
                if ($value instanceof LargeObject) {
                    $sanitizedQuery['explainable'] = false;
                    $queryValues[$key] = \sprintf('/* Object(%s): */"%s"', 'LargeObject', $value->getString());
                } else {
                    $queryValues[$key] = $value;
                }
            }

            // Prepare query values so they can be displayed in twig with VarDumper
            $sanitizedQuery['values'] = $this->cloneVar($queryValues);

            $sanitizedQueries[] = $sanitizedQuery;
        }

        return $sanitizedQueries;
    }
}
