<?php

namespace Squirrel\QueriesBundle\Examples;

use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Exception\RetryableException;
use Psr\Log\LoggerInterface;
use Squirrel\Debug\Debug;
use Squirrel\Queries\DBPassToLowerLayerTrait;
use Squirrel\Queries\DBRawInterface;
use Squirrel\Queries\DBSelectQueryInterface;

/**
 * Log connection and deadlock failures - these are autocorrected by the error handler, so
 * we record them before they get to the error handler so we know how often they occur
 *
 * Priority for the Symfony service tag needs to be below zero, so this all happens before
 * the error handler autocorrects
 */
class SQLLogTemporaryFailuresListener implements DBRawInterface
{
    // Default implementation of all DBRawInterface functions - pass to lower layer
    use DBPassToLowerLayerTrait;

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Look for deadlocks and connection exceptions, log the exact function and arguments
     * and then rethrow the exception to the error handler so it can be repeated
     */
    protected function internalCall(string $name, array $arguments): mixed
    {
        try {
            return $this->lowerLayer->$name(...$arguments);
        } catch (RetryableException $e) {
            $this->logger->info(
                'Deadlock occured for ' . Debug::sanitizeData($name) .
                '->(' . Debug::sanitizeArguments($arguments) . '): ' . $e->getMessage(),
            );
            throw $e;
        } catch (ConnectionException $e) {
            $this->logger->info(
                'Connection problem occured for ' . Debug::sanitizeData($name) .
                '->(' . Debug::sanitizeArguments($arguments) . '): ' . $e->getMessage(),
            );
            throw $e;
        }
    }

    public function select($query, array $vars = []): DBSelectQueryInterface
    {
        return $this->internalCall(__FUNCTION__, \func_get_args());
    }

    public function fetch(DBSelectQueryInterface $selectQuery): ?array
    {
        return $this->internalCall(__FUNCTION__, \func_get_args());
    }

    public function clear(DBSelectQueryInterface $selectQuery): void
    {
        $this->internalCall(__FUNCTION__, \func_get_args());
    }

    public function fetchOne($query, array $vars = []): ?array
    {
        return $this->internalCall(__FUNCTION__, \func_get_args());
    }

    public function fetchAll($query, array $vars = []): array
    {
        return $this->internalCall(__FUNCTION__, \func_get_args());
    }

    public function fetchAllAndFlatten($query, array $vars = []): array
    {
        return $this->internalCall(__FUNCTION__, \func_get_args());
    }

    public function insert(string $table, array $row = [], string $autoIncrement = ''): ?string
    {
        return $this->internalCall(__FUNCTION__, \func_get_args());
    }

    public function insertOrUpdate(
        string $table,
        array $row = [],
        array $index = [],
        ?array $update = null,
    ): void {
        $this->internalCall(__FUNCTION__, \func_get_args());
    }

    public function update(string $table, array $changes, array $where = []): int
    {
        return $this->internalCall(__FUNCTION__, \func_get_args());
    }

    public function delete(string $table, array $where = []): int
    {
        return $this->internalCall(__FUNCTION__, \func_get_args());
    }

    public function change(string $query, array $vars = []): int
    {
        return $this->internalCall(__FUNCTION__, \func_get_args());
    }
}
