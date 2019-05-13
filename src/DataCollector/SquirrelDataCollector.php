<?php

namespace Squirrel\QueriesBundle\DataCollector;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Types\Type;
use Squirrel\Queries\DBRawInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * @codeCoverageIgnore Mostly similar to Doctrine DBAL DataCollector, testing is not worth it
 */
class SquirrelDataCollector extends DataCollector
{
    /**
     * @var array List of data about the squirrel connections
     */
    private $connections;

    /**
     * @var DebugStack[]
     */
    private $loggers = [];

    /**
     * @var array|null
     */
    private $groupedQueries;

    /**
     * @param array $squirrelConnections
     */
    public function __construct(array $squirrelConnections)
    {
        $this->connections = $squirrelConnections;

        // Key = name of connection, value = array with 'connection' and 'services'
        foreach ($squirrelConnections as $connectionName => $connectionDetails) {
            /**
             * @var DBRawInterface $squirrelConnection
             */
            $squirrelConnection = $connectionDetails['connection'];

            /**
             * @var Connection $doctrineConnection
             */
            $doctrineConnection = $squirrelConnection->getConnection();

            /**
             * @var DebugStack|null $logger Assigned in LayersPass class
             * @psalm-suppress InternalMethod
             */
            $logger = $doctrineConnection->getConfiguration()->getSQLLogger();

            if (isset($logger)) {
                $this->loggers[$connectionName] = $logger;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null): void
    {
        $queries = array();
        foreach ($this->loggers as $name => $logger) {
            $queries[$name] = $this->sanitizeQueries($name, $logger->queries);
        }

        $connectionNames = [];

        // Key = defined connection name, value = list of services to access the connection
        foreach ($this->connections as $name => $details) {
            $connectionNames[$name] = '"' . implode('", "', $details['services']) . '"';
        }

        $this->data = array(
            'queries' => $queries,
            'connections' => $connectionNames,
        );

        // Might be good idea to replicate this block in doctrine bridge so we can drop this from here after some time.
        // This code is compatible with such change, because cloneVar is supposed to check if input is already cloned.
        foreach ($this->data['queries'] as &$queries) {
            foreach ($queries as &$query) {
                $query['params'] = $this->cloneVar($query['params']);
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
            usort($connectionGroupedQueries, static function (array $a, array $b) {
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

    /**
     * @param float|int $executionTimeMS
     * @param float|int $totalExecutionTimeMS
     * @return float
     */
    private function executionTimePercentage($executionTimeMS, $totalExecutionTimeMS): float
    {
        if ($totalExecutionTimeMS === 0.0 || $totalExecutionTimeMS === 0) {
            return 0;
        }

        return $executionTimeMS / $totalExecutionTimeMS * 100;
    }

    public function getGroupedQueryCount(): int
    {
        $count = 0;
        foreach ($this->getGroupedQueries() as $connectionGroupedQueries) {
            $count += count($connectionGroupedQueries);
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
            $logger->queries = [];
            $logger->currentQuery = 0;
        }
    }

    public function getQueryCount(): int
    {
        return intval(array_sum(array_map('count', $this->data['queries'])));
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

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'squirrel';
    }

    private function sanitizeQueries(string $connectionName, array $queries): array
    {
        foreach ($queries as $i => $query) {
            $queries[$i] = $this->sanitizeQuery($connectionName, $query);
        }

        return $queries;
    }

    private function sanitizeQuery(string $connectionName, array $query): array
    {
        $query['explainable'] = true;
        if ($query['params'] === null) {
            $query['params'] = array();
        }
        if (!\is_array($query['params'])) {
            $query['params'] = array($query['params']);
        }
        foreach ($query['params'] as $j => $param) {
            if (isset($query['types'][$j])) {
                // Transform the param according to the type
                $type = $query['types'][$j];
                if (\is_string($type)) {
                    $type = Type::getType($type);
                }
                if ($type instanceof Type) {
                    $query['types'][$j] = $type->getBindingType();
                    $param = $type->convertToDatabaseValue(
                        $param,
                        $this->connections[$connectionName]['connection']->getConnection()->getDatabasePlatform()
                    );
                }
            }

            list($query['params'][$j], $explainable) = $this->sanitizeParam($param);
            if (!$explainable) {
                $query['explainable'] = false;
            }
        }

        return $query;
    }

    /**
     * Sanitizes a param.
     *
     * The return value is an array with the sanitized value and a boolean
     * indicating if the original value was kept (allowing to use the sanitized
     * value to explain the query).
     *
     * @param mixed $var
     * @return array
     */
    private function sanitizeParam($var): array
    {
        if (\is_object($var)) {
            $className = \get_class($var);

            return method_exists($var, '__toString') ?
                array(sprintf('/* Object(%s): */"%s"', $className, $var->__toString()), false) :
                array(sprintf('/* Object(%s) */', $className), false);
        }

        if (\is_array($var)) {
            $a = array();
            $original = true;
            foreach ($var as $k => $v) {
                list($value, $orig) = $this->sanitizeParam($v);
                $original = $original && $orig;
                $a[$k] = $value;
            }

            return array($a, $original);
        }

        if (\is_resource($var)) {
            return array(sprintf('/* Resource(%s) */', get_resource_type($var)), false);
        }

        return array($var, true);
    }
}
