<?php

namespace Squirrel\QueriesBundle\DataCollector;

use Squirrel\Connection\ConnectionInterface;
use Squirrel\Connection\LargeObject;
use Squirrel\Connection\Log\ConnectionLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

final class SquirrelDataCollector extends DataCollector
{
    /** @var array<array{connection: ConnectionInterface, services: string[]}> */
    private array $connections;

    /** @var ConnectionLogger[] */
    private array $loggers = [];

    /** @var array<string, array<string, array{sql: string, executionMS: float|int, executionPercent: float, count: int, index: int, values: array<int|float|string|bool|null>}>>|null */
    private ?array $groupedQueries = null;

    /** @param array<array{connection: ConnectionInterface, services: string[]}> $squirrelConnections */
    public function __construct(array $squirrelConnections)
    {
        $this->connections = $squirrelConnections;

        // Key = name of connection, value = array with 'connection' and 'services'
        foreach ($squirrelConnections as $connectionName => $connectionDetails) {
            if ($connectionDetails['connection'] instanceof ConnectionLogger) {
                $this->loggers[$connectionName] = $connectionDetails['connection'];
            }
        }
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $queries = [];

        foreach ($this->loggers as $name => $logger) {
            $queries[$name] = $this->sanitizeQueries($logger->getLogs());
        }

        $connectionNames = [];

        // Key = defined connection name, value = list of services to access the connection
        foreach ($this->connections as $name => $details) {
            $connectionNames[$name] = '"' . \implode('", "', $details['services']) . '"';
        }

        $this->data = [
            'queries' => $queries,
            'connections' => $connectionNames,
        ];

        foreach ($this->data['queries'] as &$queries) {
            foreach ($queries as &$query) {
                $query['values'] = $this->cloneVar($query['values']);
            }
        }

        $this->groupedQueries   = null;
    }

    public function getGroupedQueries(): array
    {
        if ($this->groupedQueries !== null) {
            return $this->groupedQueries;
        }

        $this->groupedQueries = [];
        $totalExecutionMS     = 0;
        foreach ($this->data['queries'] as $connection => $queries) {
            $connectionGroupedQueries = [];
            foreach ($queries as $i => $query) {
                $key = $query['sql'];
                if (! isset($connectionGroupedQueries[$key])) {
                    $connectionGroupedQueries[$key]                = $query;
                    $connectionGroupedQueries[$key]['executionMS'] = 0;
                    $connectionGroupedQueries[$key]['count']       = 0;
                    $connectionGroupedQueries[$key]['index']       = $i;
                }
                $connectionGroupedQueries[$key]['executionMS'] += $query['executionMS'];
                $connectionGroupedQueries[$key]['count']++;
                $totalExecutionMS += $query['executionMS'];
            }
            \usort($connectionGroupedQueries, static function (array $a, array $b) {
                if ($a['executionMS'] === $b['executionMS']) {
                    return 0;
                }

                return $a['executionMS'] < $b['executionMS'] ? 1 : -1;
            });
            $this->groupedQueries[$connection] = $connectionGroupedQueries;
        }

        foreach ($this->groupedQueries as $connection => $queries) {
            foreach ($queries as $i => $query) {
                $this->groupedQueries[$connection][$i]['executionPercent'] =
                    $this->executionTimePercentage($query['executionMS'], $totalExecutionMS);
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

    public function getConnections(): array
    {
        return $this->data['connections'];
    }

    public function reset(): void
    {
        $this->data = [];

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

    /** @param list<array{query: string, values: array<scalar|LargeObject>, time: int}> $queries */
    private function sanitizeQueries(array $queries): array
    {
        foreach ($queries as $i => $query) {
            $queries[$i] = $this->sanitizeQuery($query);
        }

        return $queries;
    }

    /** @param array{query: string, values: array<scalar|LargeObject>, time: int} $query */
    private function sanitizeQuery(array $query): array
    {
        $sanitizedQuery = [
            'sql' => $query['query'],
            'values' => [],
            'executionMS' => $query['time'] / 1_000_000,
            'explainable' => true,
        ];

        foreach ($query['values'] as $j => $param) {
            [$sanitizedQuery['values'][$j], $explainable] = $this->sanitizeParam($param);

            if (!$explainable) {
                $sanitizedQuery['explainable'] = false;
            }
        }

        return $sanitizedQuery;
    }

    /**
     * Sanitizes a param.
     *
     * The return value is an array with the sanitized value and a boolean
     * indicating if the original value was kept (allowing to use the sanitized
     * value to explain the query).
     *
     * @return array{0: int|float|string|bool|null, 1: bool}
     */
    private function sanitizeParam(int|float|string|bool|null|LargeObject $var): array
    {
        if ($var instanceof LargeObject) {
            return array(\sprintf('/* Object(%s): */"%s"', 'LargeObject', $var->getString()), false);
        }

        return [$var, true];
    }
}
