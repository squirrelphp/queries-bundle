<?php

namespace Squirrel\QueriesBundle\Tests;

use Squirrel\Connection\Config\Mysql;
use Squirrel\Connection\Config\Pgsql;
use Squirrel\Connection\Config\Sqlite;
use Squirrel\Connection\PDO\ConnectionPDO;
use Squirrel\Queries\DB\ErrorHandler;
use Squirrel\Queries\DB\MySQLImplementation;
use Squirrel\Queries\DB\PostgreSQLImplementation;
use Squirrel\Queries\DB\SQLiteImplementation;
use Squirrel\QueriesBundle\DataCollector\SquirrelDataCollector;
use Squirrel\QueriesBundle\DependencyInjection\Compiler\LayersPass;
use Squirrel\QueriesBundle\Examples\SQLLogTemporaryFailuresListener;
use Squirrel\QueriesBundle\Twig\SquirrelQueriesExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class LayersPassTest extends \PHPUnit\Framework\TestCase
{
    public function testNoConnections(): void
    {
        $container = new ContainerBuilder();

        $this->processCompilerPass($container);

        $this->assertTrue($container->hasDefinition('squirrel.error_handler'));
        $this->assertEquals(ErrorHandler::class, $container->getDefinition('squirrel.error_handler')->getClass());
        $this->assertEquals(2, \count($container->getDefinitions())); // error handler + service container
    }

    public function testOneMySQLConnectionAsDefault(): void
    {
        $container = new ContainerBuilder();

        $connectionName = 'default';

        $newConnection = new Definition(ConnectionPDO::class, [
            new Mysql(
                host: 'localhost',
                user: 'username',
                password: 'password',
                port: 3306,
                dbname: 'ecommerce',
            ),
        ]);
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => $connectionName,
            'connectionType' => 'mysql',
            'isDefault' => true,
        ]);

        $container->setDefinition('squirrel_connection.' . $connectionName, $newConnection);

        $this->processCompilerPass($container);

        // error handler + connection + squirrel connection + query builder + service container
        $this->assertEquals(5, \count($container->getDefinitions()));

        // Make sure all definitions exist that we expect
        $this->assertTrue($container->hasDefinition('squirrel.error_handler'));
        $this->assertTrue($container->hasDefinition('squirrel.connection.default'));
        $this->assertTrue($container->hasDefinition('squirrel.querybuilder.default'));

        // Make sure topmost layer is the error handler
        $squirrelConnection = $container->getDefinition('squirrel.connection.default');
        $this->assertEquals(ErrorHandler::class, $squirrelConnection->getClass());

        // Check that the implementation layer was set correctly
        $methodCalls = $squirrelConnection->getMethodCalls();
        $this->assertEquals('setLowerLayer', $methodCalls[0][0]);
        $this->assertEquals(1, \count($methodCalls[0][1]));
        $this->assertTrue($methodCalls[0][1][0] instanceof Definition);
        $this->assertEquals(MySQLImplementation::class, $methodCalls[0][1][0]->getClass());
        $this->assertEquals([new Reference('squirrel_connection.' . $connectionName)], $methodCalls[0][1][0]->getArguments());
    }

    public function testOneMySQLConnectionNotDefault(): void
    {
        $container = new ContainerBuilder();

        $connectionName = 'greatname';

        $newConnection = new Definition(ConnectionPDO::class, [
            new Mysql(
                host: 'localhost',
                user: 'username',
                password: 'password',
                port: 3306,
                dbname: 'ecommerce',
            ),
        ]);
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => $connectionName,
            'connectionType' => 'mysql',
            'isDefault' => false,
        ]);

        $container->setDefinition('squirrel_connection.' . $connectionName, $newConnection);

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

        $connectionName = 'uniquename';

        $newConnection = new Definition(ConnectionPDO::class, [
            new Pgsql(
                host: 'localhost',
                user: 'username',
                password: 'password',
                port: 5432,
                dbname: 'ecommerce',
            ),
        ]);
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => $connectionName,
            'connectionType' => 'pgsql',
            'isDefault' => false,
        ]);

        $container->setDefinition('squirrel_connection.' . $connectionName, $newConnection);

        $this->processCompilerPass($container);

        // error handler + connection + squirrel connection + query builder + service container
        $this->assertEquals(5, \count($container->getDefinitions()));

        // Make sure all definitions exist that we expect
        $this->assertTrue($container->hasDefinition('squirrel.error_handler'));
        $this->assertTrue($container->hasDefinition('squirrel.connection.uniquename'));
        $this->assertTrue($container->hasDefinition('squirrel.querybuilder.uniquename'));

        // Make sure topmost layer is the error handler
        $squirrelConnection = $container->getDefinition('squirrel.connection.uniquename');
        $this->assertEquals(ErrorHandler::class, $squirrelConnection->getClass());

        // Check that the implementation layer was set correctly
        $methodCalls = $squirrelConnection->getMethodCalls();
        $this->assertEquals('setLowerLayer', $methodCalls[0][0]);
        $this->assertEquals(1, \count($methodCalls[0][1]));
        $this->assertTrue($methodCalls[0][1][0] instanceof Definition);
        $this->assertEquals(PostgreSQLImplementation::class, $methodCalls[0][1][0]->getClass());
        $this->assertEquals([new Reference('squirrel_connection.' . $connectionName)], $methodCalls[0][1][0]->getArguments());
    }

    public function testOneSQLiteConnection(): void
    {
        $container = new ContainerBuilder();

        $connectionName = 'uniquename';

        $newConnection = new Definition(ConnectionPDO::class, [
            new Sqlite(),
        ]);
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => $connectionName,
            'connectionType' => 'sqlite',
            'isDefault' => false,
        ]);

        $container->setDefinition('squirrel_connection.' . $connectionName, $newConnection);

        $this->processCompilerPass($container);

        // error handler + connection + squirrel connection + query builder + service container
        $this->assertEquals(5, \count($container->getDefinitions()));

        // Make sure all definitions exist that we expect
        $this->assertTrue($container->hasDefinition('squirrel.error_handler'));
        $this->assertTrue($container->hasDefinition('squirrel.connection.uniquename'));
        $this->assertTrue($container->hasDefinition('squirrel.querybuilder.uniquename'));

        // Make sure topmost layer is the error handler
        $squirrelConnection = $container->getDefinition('squirrel.connection.uniquename');
        $this->assertEquals(ErrorHandler::class, $squirrelConnection->getClass());

        // Check that the implementation layer was set correctly
        $methodCalls = $squirrelConnection->getMethodCalls();
        $this->assertEquals('setLowerLayer', $methodCalls[0][0]);
        $this->assertEquals(1, \count($methodCalls[0][1]));
        $this->assertTrue($methodCalls[0][1][0] instanceof Definition);
        $this->assertEquals(SQLiteImplementation::class, $methodCalls[0][1][0]->getClass());
        $this->assertEquals([new Reference('squirrel_connection.' . $connectionName)], $methodCalls[0][1][0]->getArguments());
    }

    public function testProfilerChanges(): void
    {
        $container = new ContainerBuilder();

        $connectionName = 'uniquename';

        $newConnection = new Definition(ConnectionPDO::class, [
            new Sqlite(),
        ]);
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => $connectionName,
            'connectionType' => 'sqlite',
            'isDefault' => false,
        ]);

        $container->setDefinition('squirrel_connection.' . $connectionName, $newConnection);

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
        $this->assertEquals(ErrorHandler::class, $squirrelConnection->getClass());

        // Check that the implementation layer was set correctly
        $methodCalls = $squirrelConnection->getMethodCalls();
        $this->assertEquals('setLowerLayer', $methodCalls[0][0]);
        $this->assertEquals(1, \count($methodCalls[0][1]));
        $this->assertTrue($methodCalls[0][1][0] instanceof Definition);
        $this->assertEquals(SQLiteImplementation::class, $methodCalls[0][1][0]->getClass());
        $this->assertEquals([new Reference('squirrel_connection.' . $connectionName)], $methodCalls[0][1][0]->getArguments());
    }

    public function testOneAddedLayer(): void
    {
        $container = new ContainerBuilder();

        $connectionName = 'default';

        $newConnection = new Definition(ConnectionPDO::class, [
            new Mysql(
                host: 'localhost',
                user: 'username',
                password: 'password',
                port: 3306,
                dbname: 'ecommerce',
            ),
        ]);
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => $connectionName,
            'connectionType' => 'mysql',
            'isDefault' => true,
        ]);

        $container->setDefinition('squirrel_connection.' . $connectionName, $newConnection);

        $logLayer = new Definition(SQLLogTemporaryFailuresListener::class);
        $logLayer->addTag('squirrel.layer');

        $container->setDefinition('log_layer', $logLayer);

        $this->processCompilerPass($container);

        // error handler + connection + squirrel connection + query builder + log layer + service container
        $this->assertEquals(6, \count($container->getDefinitions()));

        // Make sure all definitions exist that we expect
        $this->assertTrue($container->hasDefinition('squirrel.error_handler'));
        $this->assertTrue($container->hasDefinition('squirrel.connection.' . $connectionName));
        $this->assertTrue($container->hasDefinition('squirrel.querybuilder.' . $connectionName));

        // Make sure topmost layer is the logger
        $squirrelConnection = $container->getDefinition('squirrel.connection.' . $connectionName);
        $this->assertEquals(SQLLogTemporaryFailuresListener::class, $squirrelConnection->getClass());

        // Check that the lower layer was set correctly
        $methodCalls = $squirrelConnection->getMethodCalls();
        $this->assertEquals('setLowerLayer', $methodCalls[0][0]);
        $this->assertEquals(1, \count($methodCalls[0][1]));
        $this->assertTrue($methodCalls[0][1][0] instanceof Definition);
        $this->assertEquals(ErrorHandler::class, $methodCalls[0][1][0]->getClass());
    }

    public function testMultipleAddedLayers(): void
    {
        $container = new ContainerBuilder();

        $connectionName = 'default';

        $newConnection = new Definition(ConnectionPDO::class, [
            new Mysql(
                host: 'localhost',
                user: 'username',
                password: 'password',
                port: 3306,
                dbname: 'ecommerce',
            ),
        ]);
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => $connectionName,
            'connectionType' => 'mysql',
            'isDefault' => true,
        ]);

        $container->setDefinition('squirrel_connection.' . $connectionName, $newConnection);

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
        $this->assertTrue($container->hasDefinition('squirrel.connection.' . $connectionName));
        $this->assertTrue($container->hasDefinition('squirrel.querybuilder.' . $connectionName));

        // Make sure topmost layer is the logger
        $squirrelConnection = $container->getDefinition('squirrel.connection.' . $connectionName);
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
        $this->assertEquals(ErrorHandler::class, $methodCalls[0][1][0]->getClass());

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
        $this->assertEquals(MySQLImplementation::class, $methodCalls[0][1][0]->getClass());
    }

    public function testMultipleConnectionsWithSameName(): void
    {
        $this->expectException(\LogicException::class);

        $container = new ContainerBuilder();

        $connectionName = 'uniquename';

        $newConnection = new Definition(ConnectionPDO::class, [
            new Mysql(
                host: 'localhost',
                user: 'username',
                password: 'password',
                port: 3306,
                dbname: 'ecommerce',
            ),
        ]);
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => $connectionName,
            'connectionType' => 'mysql',
            'isDefault' => false,
        ]);

        $container->setDefinition('squirrel_connection.' . $connectionName, $newConnection);
        $container->setDefinition('squirrel_connection.' . $connectionName . '2', $newConnection);

        $this->processCompilerPass($container);
    }

    public function testMultipleDefaultConnections(): void
    {
        $this->expectException(\LogicException::class);

        $container = new ContainerBuilder();

        $connectionName = 'default';

        $newConnection = new Definition(ConnectionPDO::class, [
            new Mysql(
                host: 'localhost',
                user: 'username',
                password: 'password',
                port: 3306,
                dbname: 'ecommerce',
            ),
        ]);
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => $connectionName,
            'connectionType' => 'mysql',
            'isDefault' => true,
        ]);

        $connectionName2nd = 'default2';

        $newConnection2nd = new Definition(ConnectionPDO::class, [
            new Mysql(
                host: 'localhost',
                user: 'username',
                password: 'password',
                port: 3306,
                dbname: 'ecommerce',
            ),
        ]);
        $newConnection2nd->addTag('squirrel.connection', [
            'connectionName' => $connectionName2nd,
            'connectionType' => 'mysql',
            'isDefault' => true,
        ]);

        $container->setDefinition('squirrel_connection.' . $connectionName, $newConnection);
        $container->setDefinition('squirrel_connection.' . $connectionName2nd, $newConnection2nd);

        $this->processCompilerPass($container);
    }

    public function testInvalidConnectionType(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $container = new ContainerBuilder();

        $connectionName = 'uniquename';

        $newConnection = new Definition(ConnectionPDO::class, [
            new Mysql(
                host: 'localhost',
                user: 'username',
                password: 'password',
                port: 3306,
                dbname: 'ecommerce',
            ),
        ]);
        $newConnection->addTag('squirrel.connection', [
            'connectionName' => $connectionName,
            'connectionType' => 'gaga',
            'isDefault' => false,
        ]);

        $container->setDefinition('squirrel_connection.' . $connectionName, $newConnection);

        $this->processCompilerPass($container);
    }

    protected function processCompilerPass(ContainerBuilder $container): void
    {
        (new LayersPass())->process($container);
    }
}
