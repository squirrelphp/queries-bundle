<?php

namespace Squirrel\QueriesBundle\Tests;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\DebugStack;
use Squirrel\Queries\Doctrine\DBErrorHandler;
use Squirrel\Queries\Doctrine\DBMySQLImplementation;
use Squirrel\Queries\Doctrine\DBPostgreSQLImplementation;
use Squirrel\Queries\Doctrine\DBSQLiteImplementation;
use Squirrel\QueriesBundle\DataCollector\SquirrelDataCollector;
use Squirrel\QueriesBundle\DependencyInjection\Compiler\LayersPass;
use Squirrel\QueriesBundle\Examples\SQLLogTemporaryFailuresListener;
use Squirrel\QueriesBundle\Twig\SquirrelQueriesExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class LayersPassTest extends \PHPUnit\Framework\TestCase
{
    public function testNoConnections(): void
    {
        $container = new ContainerBuilder();

        $this->processCompilerPass($container);

        $this->assertTrue($container->hasDefinition('squirrel.error_handler'));
        $this->assertEquals(DBErrorHandler::class, $container->getDefinition('squirrel.error_handler')->getClass());
        $this->assertEquals(2, \count($container->getDefinitions())); // error handler + service container
    }

    public function testOneMySQLConnectionAsDefault(): void
    {
        $container = new ContainerBuilder();

        // Set up doctrine connection as we would expect it
        $newConnection = new Definition(Connection::class, [
            '$params' => [
                'driver' => 'pdo_mysql',
                'host' => 'localhost',
                'port' => '3306',
                'dbname' => 'ecommerce',
                'user' => 'username',
                'password' => 'password',
            ],
        ]);
        $newConnection->setFactory('Doctrine\DBAL\DriverManager::getConnection');
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => 'uniquename',
            'connectionType' => 'mysql',
            'isDefault' => true,
        ]);

        $container->setDefinition('mysql_connection', $newConnection);

        $this->processCompilerPass($container);

        // error handler + connection + squirrel connection + query builder + service container
        $this->assertEquals(5, \count($container->getDefinitions()));

        // Make sure all definitions exist that we expect
        $this->assertTrue($container->hasDefinition('squirrel.error_handler'));
        $this->assertTrue($container->hasDefinition('squirrel.connection.uniquename'));
        $this->assertTrue($container->hasDefinition('squirrel.querybuilder.uniquename'));

        // Make sure topmost layer is the error handler
        $squirrelConnection = $container->getDefinition('squirrel.connection.uniquename');
        $this->assertEquals(DBErrorHandler::class, $squirrelConnection->getClass());

        // Check that the implementation layer was set correctly
        $methodCalls = $squirrelConnection->getMethodCalls();
        $this->assertEquals('setLowerLayer', $methodCalls[0][0]);
        $this->assertEquals(1, \count($methodCalls[0][1]));
        $this->assertTrue($methodCalls[0][1][0] instanceof Definition);
        $this->assertEquals(DBMySQLImplementation::class, $methodCalls[0][1][0]->getClass());
        $this->assertEquals([$newConnection], $methodCalls[0][1][0]->getArguments());

        $doctrineConnection = $container->getDefinition('mysql_connection');
        $doctrineArguments = $doctrineConnection->getArguments();

        // Check the adjusted connection options for doctrine
        $this->assertEquals([
            'driver' => 'pdo_mysql',
            'host' => 'localhost',
            'port' => '3306',
            'dbname' => 'ecommerce',
            'user' => 'username',
            'password' => 'password',
            'driverOptions' => [
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::MYSQL_ATTR_FOUND_ROWS => true,
                \PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
            ],
        ], $doctrineArguments['$params']);

        $this->assertEquals(1, \count($doctrineArguments));
    }

    public function testOneMySQLConnectionNotDefault(): void
    {
        $container = new ContainerBuilder();

        // Set up doctrine connection as we would expect it
        $newConnection = new Definition(Connection::class, [
            '$params' => [
                'driver' => 'pdo_mysql',
                'host' => 'localhost',
                'port' => '3306',
                'dbname' => 'ecommerce',
                'user' => 'username',
                'password' => 'password',
            ],
        ]);
        $newConnection->setFactory('Doctrine\DBAL\DriverManager::getConnection');
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => 'greatname',
            'connectionType' => 'mysql',
        ]);

        $container->setDefinition('mysql_connection', $newConnection);

        $this->processCompilerPass($container);

        // error handler + connection + squirrel connection + query builder + service container
        $this->assertEquals(5, \count($container->getDefinitions()));

        // Make sure all definitions exist that we expect
        $this->assertTrue($container->hasDefinition('squirrel.error_handler'));
        $this->assertTrue($container->hasDefinition('squirrel.connection.greatname'));
        $this->assertTrue($container->hasDefinition('squirrel.querybuilder.greatname'));
    }

    public function testOnePostgresConnection(): void
    {
        $container = new ContainerBuilder();

        // Set up doctrine connection as we would expect it
        $newConnection = new Definition(Connection::class, [
            '$params' => [
                'driver' => 'pdo_pgsql',
                'host' => 'localhost',
                'port' => '3306',
                'dbname' => 'ecommerce',
                'user' => 'username',
                'password' => 'password',
            ],
        ]);
        $newConnection->setFactory('Doctrine\DBAL\DriverManager::getConnection');
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => 'uniquename',
            'connectionType' => 'pgsql',
        ]);

        $container->setDefinition('pgsql_connection', $newConnection);

        $this->processCompilerPass($container);

        // error handler + connection + squirrel connection + query builder + service container
        $this->assertEquals(5, \count($container->getDefinitions()));

        // Make sure all definitions exist that we expect
        $this->assertTrue($container->hasDefinition('squirrel.error_handler'));
        $this->assertTrue($container->hasDefinition('squirrel.connection.uniquename'));
        $this->assertTrue($container->hasDefinition('squirrel.querybuilder.uniquename'));

        // Make sure topmost layer is the error handler
        $squirrelConnection = $container->getDefinition('squirrel.connection.uniquename');
        $this->assertEquals(DBErrorHandler::class, $squirrelConnection->getClass());

        // Check that the implementation layer was set correctly
        $methodCalls = $squirrelConnection->getMethodCalls();
        $this->assertEquals('setLowerLayer', $methodCalls[0][0]);
        $this->assertEquals(1, \count($methodCalls[0][1]));
        $this->assertTrue($methodCalls[0][1][0] instanceof Definition);
        $this->assertEquals(DBPostgreSQLImplementation::class, $methodCalls[0][1][0]->getClass());
        $this->assertEquals([$newConnection], $methodCalls[0][1][0]->getArguments());

        $doctrineConnection = $container->getDefinition('pgsql_connection');
        $doctrineArguments = $doctrineConnection->getArguments();

        // Check the adjusted connection options for doctrine
        $this->assertEquals([
            'driver' => 'pdo_pgsql',
            'host' => 'localhost',
            'port' => '3306',
            'dbname' => 'ecommerce',
            'user' => 'username',
            'password' => 'password',
            'driverOptions' => [
                \PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ], $doctrineArguments['$params']);

        $this->assertEquals(1, \count($doctrineArguments));
    }

    public function testOneSQLiteConnection(): void
    {
        $container = new ContainerBuilder();

        // Set up doctrine connection as we would expect it
        $newConnection = new Definition(Connection::class, [
            '$params' => [
                'driver' => 'pdo_sqlite',
                'path' => 'database.db',
                'user' => 'username',
                'password' => 'password',
            ],
        ]);
        $newConnection->setFactory('Doctrine\DBAL\DriverManager::getConnection');
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => 'uniquename',
            'connectionType' => 'sqlite',
        ]);

        $container->setDefinition('sqlite_connection', $newConnection);

        $this->processCompilerPass($container);

        // error handler + connection + squirrel connection + query builder + service container
        $this->assertEquals(5, \count($container->getDefinitions()));

        // Make sure all definitions exist that we expect
        $this->assertTrue($container->hasDefinition('squirrel.error_handler'));
        $this->assertTrue($container->hasDefinition('squirrel.connection.uniquename'));
        $this->assertTrue($container->hasDefinition('squirrel.querybuilder.uniquename'));

        // Make sure topmost layer is the error handler
        $squirrelConnection = $container->getDefinition('squirrel.connection.uniquename');
        $this->assertEquals(DBErrorHandler::class, $squirrelConnection->getClass());

        // Check that the implementation layer was set correctly
        $methodCalls = $squirrelConnection->getMethodCalls();
        $this->assertEquals('setLowerLayer', $methodCalls[0][0]);
        $this->assertEquals(1, \count($methodCalls[0][1]));
        $this->assertTrue($methodCalls[0][1][0] instanceof Definition);
        $this->assertEquals(DBSQLiteImplementation::class, $methodCalls[0][1][0]->getClass());
        $this->assertEquals([$newConnection], $methodCalls[0][1][0]->getArguments());

        $doctrineConnection = $container->getDefinition('sqlite_connection');
        $doctrineArguments = $doctrineConnection->getArguments();

        // Check the adjusted connection options for doctrine
        $this->assertEquals([
            'driver' => 'pdo_sqlite',
            'path' => 'database.db',
            'user' => 'username',
            'password' => 'password',
            'driverOptions' => [
                \PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ], $doctrineArguments['$params']);

        $this->assertEquals(1, \count($doctrineArguments));
    }

    public function testProfilerChanges(): void
    {
        $container = new ContainerBuilder();

        // Set up doctrine connection as we would expect it
        $newConnection = new Definition(Connection::class, [
            '$params' => [
                'driver' => 'pdo_sqlite',
                'path' => 'database.db',
                'user' => 'username',
                'password' => 'password',
            ],
        ]);
        $newConnection->setFactory('Doctrine\DBAL\DriverManager::getConnection');
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => 'uniquename',
            'connectionType' => 'sqlite',
        ]);

        $container->setDefinition('sqlite_connection', $newConnection);
        $container->setDefinition('profiler', new Definition('profiler_from_symfony'));

        $this->processCompilerPass($container);

        // error handler + connection + squirrel connection + query builder + service container
        // + squirrel data collector + squirrel queries extension + profiler
        $this->assertEquals(8, \count($container->getDefinitions()));

        // Make sure all definitions exist that we expect
        $this->assertTrue($container->hasDefinition('squirrel.error_handler'));
        $this->assertTrue($container->hasDefinition('squirrel.connection.uniquename'));
        $this->assertTrue($container->hasDefinition('squirrel.querybuilder.uniquename'));
        $this->assertTrue($container->hasDefinition(SquirrelDataCollector::class));
        $this->assertTrue($container->hasDefinition(SquirrelQueriesExtension::class));

        // Make sure topmost layer is the error handler
        $squirrelConnection = $container->getDefinition('squirrel.connection.uniquename');
        $this->assertEquals(DBErrorHandler::class, $squirrelConnection->getClass());

        // Check that the implementation layer was set correctly
        $methodCalls = $squirrelConnection->getMethodCalls();
        $this->assertEquals('setLowerLayer', $methodCalls[0][0]);
        $this->assertEquals(1, \count($methodCalls[0][1]));
        $this->assertTrue($methodCalls[0][1][0] instanceof Definition);
        $this->assertEquals(DBSQLiteImplementation::class, $methodCalls[0][1][0]->getClass());
        $this->assertEquals([$newConnection], $methodCalls[0][1][0]->getArguments());

        $doctrineConnection = $container->getDefinition('sqlite_connection');
        $doctrineArguments = $doctrineConnection->getArguments();

        // Check the adjusted connection options for doctrine
        $this->assertEquals([
            'driver' => 'pdo_sqlite',
            'path' => 'database.db',
            'user' => 'username',
            'password' => 'password',
            'driverOptions' => [
                \PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ], $doctrineArguments['$params']);

        $this->assertEquals(2, \count($doctrineArguments));

        $configuration = new Definition(Configuration::class);
        $configuration->addMethodCall('setSQLLogger', [new Definition(DebugStack::class)]);

        $this->assertEquals($configuration, $doctrineArguments['$config']);
    }

    public function testOneAddedLayer(): void
    {
        $container = new ContainerBuilder();

        // Set up doctrine connection as we would expect it
        $newConnection = new Definition(Connection::class, [
            '$params' => [
                'driver' => 'pdo_mysql',
                'host' => 'localhost',
                'port' => '3306',
                'dbname' => 'ecommerce',
                'user' => 'username',
                'password' => 'password',
            ],
        ]);
        $newConnection->setFactory('Doctrine\DBAL\DriverManager::getConnection');
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => 'uniquename',
            'connectionType' => 'mysql',
        ]);

        $container->setDefinition('mysql_connection', $newConnection);

        $logLayer = new Definition(SQLLogTemporaryFailuresListener::class);
        $logLayer->addTag('squirrel.layer');

        $container->setDefinition('log_layer', $logLayer);

        $this->processCompilerPass($container);

        // error handler + connection + squirrel connection + query builder + log layer + service container
        $this->assertEquals(6, \count($container->getDefinitions()));

        // Make sure all definitions exist that we expect
        $this->assertTrue($container->hasDefinition('squirrel.error_handler'));
        $this->assertTrue($container->hasDefinition('squirrel.connection.uniquename'));
        $this->assertTrue($container->hasDefinition('squirrel.querybuilder.uniquename'));

        // Make sure topmost layer is the logger
        $squirrelConnection = $container->getDefinition('squirrel.connection.uniquename');
        $this->assertEquals(SQLLogTemporaryFailuresListener::class, $squirrelConnection->getClass());

        // Check that the lower layer was set correctly
        $methodCalls = $squirrelConnection->getMethodCalls();
        $this->assertEquals('setLowerLayer', $methodCalls[0][0]);
        $this->assertEquals(1, \count($methodCalls[0][1]));
        $this->assertTrue($methodCalls[0][1][0] instanceof Definition);
        $this->assertEquals(DBErrorHandler::class, $methodCalls[0][1][0]->getClass());
    }

    public function testMultipleAddedLayers(): void
    {
        $container = new ContainerBuilder();

        // Set up doctrine connection as we would expect it
        $newConnection = new Definition(Connection::class, [
            '$params' => [
                'driver' => 'pdo_mysql',
                'host' => 'localhost',
                'port' => '3306',
                'dbname' => 'ecommerce',
                'user' => 'username',
                'password' => 'password',
            ],
        ]);
        $newConnection->setFactory('Doctrine\DBAL\DriverManager::getConnection');
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => 'uniquename',
            'connectionType' => 'mysql',
        ]);

        $container->setDefinition('mysql_connection', $newConnection);

        $logLayer = new Definition(SQLLogTemporaryFailuresListener::class);
        $logLayer->addTag('squirrel.layer', [
            'priority' => -33,
        ]);

        $logLayer2 = new Definition(SQLLogTemporaryFailuresListener::class);
        $logLayer2->addTag('squirrel.layer', [
            'priority' => 5,
        ]);

        $logLayer3 = new Definition(SQLLogTemporaryFailuresListener::class);
        $logLayer3->addTag('squirrel.layer', [
            'priority' => 50,
        ]);

        $container->setDefinition('log_layer', $logLayer);
        $container->setDefinition('log_layer2', $logLayer2);
        $container->setDefinition('log_layer3', $logLayer3);

        $this->processCompilerPass($container);

        // error handler + connection + squirrel connection + query builder + log layers + service container
        $this->assertEquals(8, \count($container->getDefinitions()));

        // Make sure all definitions exist that we expect
        $this->assertTrue($container->hasDefinition('squirrel.error_handler'));
        $this->assertTrue($container->hasDefinition('squirrel.connection.uniquename'));
        $this->assertTrue($container->hasDefinition('squirrel.querybuilder.uniquename'));

        // Make sure topmost layer is the logger
        $squirrelConnection = $container->getDefinition('squirrel.connection.uniquename');
        $this->assertEquals(SQLLogTemporaryFailuresListener::class, $squirrelConnection->getClass());

        // Check that the lower layer was set correctly (another logger)
        $methodCalls = $squirrelConnection->getMethodCalls();
        $this->assertEquals('setLowerLayer', $methodCalls[0][0]);
        $this->assertEquals(1, \count($methodCalls[0][1]));
        $this->assertTrue($methodCalls[0][1][0] instanceof Definition);
        $this->assertEquals(SQLLogTemporaryFailuresListener::class, $methodCalls[0][1][0]->getClass());

        // Check that the lower layer was set correctly (error handler)
        $methodCalls = $methodCalls[0][1][0]->getMethodCalls();
        $this->assertEquals('setLowerLayer', $methodCalls[0][0]);
        $this->assertEquals(1, \count($methodCalls[0][1]));
        $this->assertTrue($methodCalls[0][1][0] instanceof Definition);
        $this->assertEquals(DBErrorHandler::class, $methodCalls[0][1][0]->getClass());

        // Check that the lower layer was set correctly (another logger)
        $methodCalls = $methodCalls[0][1][0]->getMethodCalls();
        $this->assertEquals('setLowerLayer', $methodCalls[0][0]);
        $this->assertEquals(1, \count($methodCalls[0][1]));
        $this->assertTrue($methodCalls[0][1][0] instanceof Definition);
        $this->assertEquals(SQLLogTemporaryFailuresListener::class, $methodCalls[0][1][0]->getClass());

        // Check that the lower layer was set correctly (implementation)
        $methodCalls = $methodCalls[0][1][0]->getMethodCalls();
        $this->assertEquals('setLowerLayer', $methodCalls[0][0]);
        $this->assertEquals(1, \count($methodCalls[0][1]));
        $this->assertTrue($methodCalls[0][1][0] instanceof Definition);
        $this->assertEquals(DBMySQLImplementation::class, $methodCalls[0][1][0]->getClass());
    }

    public function testOwnImplementationService(): void
    {
        $container = new ContainerBuilder();

        // Set up doctrine connection as we would expect it
        $newConnection = new Definition(Connection::class, [
            '$params' => [
                'driver' => 'pdo_sqlite',
                'path' => 'database.db',
                'user' => 'username',
                'password' => 'password',
            ],
        ]);
        $newConnection->setFactory('Doctrine\DBAL\DriverManager::getConnection');
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => 'uniquename',
            'connectionType' => 'sqlite',
        ]);

        $container->setDefinition('sqlite_connection', $newConnection);

        $ownImplementation = new Definition(SQLLogTemporaryFailuresListener::class);

        $container->setDefinition('squirrel.implementation.uniquename', $ownImplementation);

        $this->processCompilerPass($container);

        // error handler + connection + squirrel connection + query builder + service container + custom implementation
        $this->assertEquals(6, \count($container->getDefinitions()));

        // Make sure all definitions exist that we expect
        $this->assertTrue($container->hasDefinition('squirrel.error_handler'));
        $this->assertTrue($container->hasDefinition('squirrel.connection.uniquename'));
        $this->assertTrue($container->hasDefinition('squirrel.querybuilder.uniquename'));

        // Make sure topmost layer is the error handler
        $squirrelConnection = $container->getDefinition('squirrel.connection.uniquename');
        $this->assertEquals(DBErrorHandler::class, $squirrelConnection->getClass());

        // Check that the implementation layer was set correctly
        $methodCalls = $squirrelConnection->getMethodCalls();
        $this->assertEquals('setLowerLayer', $methodCalls[0][0]);
        $this->assertEquals(1, \count($methodCalls[0][1]));
        $this->assertTrue($methodCalls[0][1][0] instanceof Definition);
        $this->assertEquals(SQLLogTemporaryFailuresListener::class, $methodCalls[0][1][0]->getClass());
        $this->assertEquals([], $methodCalls[0][1][0]->getArguments());
    }

    public function testOwnImplementationClass(): void
    {
        $container = new ContainerBuilder();

        // Set up doctrine connection as we would expect it
        $newConnection = new Definition(Connection::class, [
            '$params' => [
                'driver' => 'pdo_sqlite',
                'path' => 'database.db',
                'user' => 'username',
                'password' => 'password',
            ],
        ]);
        $newConnection->setFactory('Doctrine\DBAL\DriverManager::getConnection');
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => 'uniquename',
            'connectionType' => SQLLogTemporaryFailuresListener::class,
        ]);

        $container->setDefinition('sqlite_connection', $newConnection);

        $this->processCompilerPass($container);

        // error handler + connection + squirrel connection + query builder + service container
        $this->assertEquals(5, \count($container->getDefinitions()));

        // Make sure all definitions exist that we expect
        $this->assertTrue($container->hasDefinition('squirrel.error_handler'));
        $this->assertTrue($container->hasDefinition('squirrel.connection.uniquename'));
        $this->assertTrue($container->hasDefinition('squirrel.querybuilder.uniquename'));

        // Make sure topmost layer is the error handler
        $squirrelConnection = $container->getDefinition('squirrel.connection.uniquename');
        $this->assertEquals(DBErrorHandler::class, $squirrelConnection->getClass());

        // Check that the implementation layer was set correctly
        $methodCalls = $squirrelConnection->getMethodCalls();
        $this->assertEquals('setLowerLayer', $methodCalls[0][0]);
        $this->assertEquals(1, \count($methodCalls[0][1]));
        $this->assertTrue($methodCalls[0][1][0] instanceof Definition);
        $this->assertEquals(SQLLogTemporaryFailuresListener::class, $methodCalls[0][1][0]->getClass());
        $this->assertEquals([new Reference('sqlite_connection')], $methodCalls[0][1][0]->getArguments());
    }

    public function testMultipleConnectionsWithSameName(): void
    {
        $this->expectException(\LogicException::class);

        $container = new ContainerBuilder();

        // Set up doctrine connection as we would expect it
        $newConnection = new Definition(Connection::class, [
            '$params' => [
                'driver' => 'pdo_sqlite',
                'path' => 'database.db',
                'user' => 'username',
                'password' => 'password',
            ],
        ]);
        $newConnection->setFactory('Doctrine\DBAL\DriverManager::getConnection');
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => 'uniquename',
            'connectionType' => 'sqlite',
        ]);

        // Set up doctrine connection as we would expect it
        $secondConnection = new Definition(Connection::class, [
            '$params' => [
                'driver' => 'pdo_sqlite',
                'path' => 'database.db',
                'user' => 'username',
                'password' => 'password',
            ],
        ]);
        $secondConnection->setFactory('Doctrine\DBAL\DriverManager::getConnection');
        $secondConnection->addTag('squirrel.connection', [
            'connectionName' => 'uniquename',
            'connectionType' => 'sqlite',
        ]);

        $container->setDefinition('sqlite_connection', $newConnection);
        $container->setDefinition('sqlite_connection2', $secondConnection);

        $this->processCompilerPass($container);
    }

    public function testMultipleDefaultConnections(): void
    {
        $this->expectException(\LogicException::class);

        $container = new ContainerBuilder();

        // Set up doctrine connection as we would expect it
        $newConnection = new Definition(Connection::class, [
            '$params' => [
                'driver' => 'pdo_sqlite',
                'path' => 'database.db',
                'user' => 'username',
                'password' => 'password',
            ],
        ]);
        $newConnection->setFactory('Doctrine\DBAL\DriverManager::getConnection');
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => 'uniquename',
            'connectionType' => 'sqlite',
            'isDefault' => true,
        ]);

        // Set up doctrine connection as we would expect it
        $secondConnection = new Definition(Connection::class, [
            '$params' => [
                'driver' => 'pdo_sqlite',
                'path' => 'database.db',
                'user' => 'username',
                'password' => 'password',
            ],
        ]);
        $secondConnection->setFactory('Doctrine\DBAL\DriverManager::getConnection');
        $secondConnection->addTag('squirrel.connection', [
            'connectionName' => 'uniquename2',
            'connectionType' => 'sqlite',
            'isDefault' => true,
        ]);

        $container->setDefinition('sqlite_connection', $newConnection);
        $container->setDefinition('sqlite_connection2', $secondConnection);

        $this->processCompilerPass($container);
    }

    public function testUnknownImplementation(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $container = new ContainerBuilder();

        // Set up doctrine connection as we would expect it
        $newConnection = new Definition(Connection::class, [
            '$params' => [
                'driver' => 'pdo_sqlite',
                'path' => 'database.db',
                'user' => 'username',
                'password' => 'password',
            ],
        ]);
        $newConnection->setFactory('Doctrine\DBAL\DriverManager::getConnection');
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => 'uniquename',
            'connectionType' => 'something_not_known',
            'isDefault' => true,
        ]);

        $container->setDefinition('sqlite_connection', $newConnection);

        $this->processCompilerPass($container);
    }

    protected function processCompilerPass(ContainerBuilder $container): void
    {
        (new LayersPass())->process($container);
    }
}
