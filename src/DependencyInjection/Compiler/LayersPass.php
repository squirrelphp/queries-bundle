<?php

namespace Squirrel\QueriesBundle\DependencyInjection\Compiler;

use Squirrel\Connection\Log\ConnectionLogger;
use Squirrel\Debug\Debug;
use Squirrel\Queries\DB\ErrorHandler;
use Squirrel\Queries\DB\MySQLImplementation;
use Squirrel\Queries\DB\PostgreSQLImplementation;
use Squirrel\Queries\DB\SQLiteImplementation;
use Squirrel\Queries\DBBuilder;
use Squirrel\Queries\DBBuilderInterface;
use Squirrel\Queries\DBInterface;
use Squirrel\QueriesBundle\DataCollector\SquirrelDataCollector;
use Squirrel\QueriesBundle\Twig\SquirrelQueriesExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Create DBInterface services with different layers
 */
final class LayersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Whether Symfony profiler / toolbar is active
        $profilerActive = $container->has('profiler');

        // If no custom error handler has been defined, we use our default one
        $this->createErrorHandlerIfNotSet($container);

        // Find all layers (implementing DBRawInterface) which decorate all DBInterface services
        $taggedServices = $container->findTaggedServiceIds('squirrel.layer');

        // Order the layers
        $taggedServicesOrdered = $this->sortLayers($taggedServices);

        // Get all SQL connection definitions
        $services = $container->findTaggedServiceIds('squirrel.connection');

        // List of connections for profiler
        $connectionList = [];

        // Go through all tagged services
        foreach ($services as $id => $tags) {
            // Go through all our tags in the service
            foreach ($tags as $tag) {
                // Activate logging if the debug toolbar is active
                if ($profilerActive) {
                    $this->replaceConnectionWithLogger(
                        $container,
                        $id,
                    );
                }

                $connectionList[$tag['connectionName']] = $this->createConnectionFromTag(
                    $container,
                    $id,
                    $tag,
                    $taggedServicesOrdered,
                );
            }
        }

        // Create data collector & twig extension for profiler / web toolbar
        if ($profilerActive === true) {
            $this->createDataCollectorService($container, $connectionList);
        }
    }

    private function createConnectionFromTag(
        ContainerBuilder $container,
        string $id,
        array $tag,
        array $taggedServicesOrdered,
    ): array {
        $layeredConnectionDefinition = $this->createLayeredConnection(
            $container,
            $this->getBaseImplementation($container, $id, $tag),
            $taggedServicesOrdered,
        );

        $connectionServiceName = 'squirrel.connection.' . $tag['connectionName'];
        $builderServiceName = 'squirrel.querybuilder.' . $tag['connectionName'];

        $container->setDefinition($connectionServiceName, $layeredConnectionDefinition);

        $builderDefinition = new Definition(DBBuilder::class, [new Reference($connectionServiceName)]);
        $container->setDefinition($builderServiceName, $builderDefinition);

        $servicesList = [$connectionServiceName];

        // If this is the default connection we enable DBInterface type hints
        if (\boolval($tag['isDefault'] ?? false) === true) {
            // Only one default connection can exists, everything else is an error
            if ($container->hasAlias(DBInterface::class)) {
                throw new \LogicException(
                    'You have multiple squirrel default connections - ' .
                    'make sure you only defined one connection as "default"',
                );
            }

            $container->setAlias(DBInterface::class, $connectionServiceName);
            $container->setAlias(DBBuilderInterface::class, $builderServiceName);
            $servicesList[] = DBInterface::class;
        }

        return [
            'connection' => new Reference($id),
            'services' => $servicesList,
        ];
    }

    private function createErrorHandlerIfNotSet(ContainerBuilder $container): void
    {
        // If no custom error handler has been defined, we use our default one
        if (!$container->hasDefinition('squirrel.error_handler')) {
            $dbErrorHandler = new Definition(ErrorHandler::class);
            $dbErrorHandler->setPublic(false);

            $container->setDefinition('squirrel.error_handler', $dbErrorHandler);
        }
    }

    // Sort DBRawInterface layers according to priority and return them
    private function sortLayers(array $taggedServices): array
    {
        // Ordered services according to priority
        $taggedServicesOrdered = [];

        // Create ordered services array
        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $attributes) {
                // Find out the priority of this tagged service
                $attributes['priority'] = \intval($attributes['priority'] ?? 0);

                // If priority is equal or above zero, add one so error handler can
                // be set to zero and there is no overlap with another layer
                if ($attributes['priority'] >= 0) {
                    $attributes['priority']++;
                }

                // Make sure priority has a fixed length for correct ordering
                $priority = \sprintf("%'.08d", $attributes['priority']);

                // Create priority sub-array if necessary
                if (!isset($taggedServicesOrdered[$priority])) {
                    $taggedServicesOrdered[$priority] = [];
                }

                // Add to list of ordered services
                $taggedServicesOrdered[$priority][] = [$id, $attributes];
            }
        }

        // Add error handler with priority zero
        $taggedServicesOrdered[\sprintf("%'.08d", 0)][] = ['squirrel.error_handler', ['priority' => 0]];

        // Order services according to priority and configuration order
        \ksort($taggedServicesOrdered, SORT_NUMERIC);

        return $taggedServicesOrdered;
    }

    private function replaceConnectionWithLogger(
        ContainerBuilder $container,
        string $serviceId,
    ): void {
        $connection = $container->getDefinition($serviceId);

        $container->setDefinition($serviceId, new Definition(ConnectionLogger::class, [$connection]));
    }

    // Get base implementation interacting with Squirrel Connection Service
    private function getBaseImplementation(ContainerBuilder $container, string $serviceId, array $tag): Definition
    {
        // Connection with this name already exists - each connection name has to be unique
        if ($container->hasDefinition('squirrel.connection.' . $tag['connectionName'])) {
            throw new \LogicException(
                'You have multiple squirrel connections with the name ' . Debug::sanitizeData($tag['connectionName']) . ' - ' .
                'make sure to have unique connection names',
            );
        }

        $implementationClass = match ($tag['connectionType']) {
            'mysql', 'mariadb' => MySQLImplementation::class,
            'pgsql' => PostgreSQLImplementation::class,
            'sqlite' => SQLiteImplementation::class,
            default => throw new InvalidConfigurationException(
                'Only mysql, mariadb, pgsql and sqlite connection types are currently supported by squirrel database connections, ' .
                'yet you have specified ' . Debug::sanitizeData($tag['connectionType']) . ' instead.',
            ),
        };

        return new Definition($implementationClass, [new Reference($serviceId)]);
    }

    private function createLayeredConnection(
        ContainerBuilder $container,
        Definition $implementationLayer,
        array $otherLayers,
    ): Definition {
        // The very lowest layer is the base implementation
        $topmostLayerDefinition = clone $implementationLayer;

        // Add the event listeners to our event manager
        foreach ($otherLayers as $priority => $entriesForThisPriority) {
            foreach ($entriesForThisPriority as $callData) {
                // Break up service and attributes
                [$id] = $callData;

                // Get definition for the current service
                $definition = clone $container->findDefinition(\strval($id));

                // Add lower layer to this service
                $definition->addMethodCall('setLowerLayer', [$topmostLayerDefinition]);

                // Mark this new service as the layer on top now
                $topmostLayerDefinition = $definition;
            }
        }

        return $topmostLayerDefinition;
    }

    private function createDataCollectorService(ContainerBuilder $container, array $connectionList): void
    {
        $dataCollector = new Definition(SquirrelDataCollector::class, [$connectionList]);
        $dataCollector->addTag('data_collector', [
            'template' => '@SquirrelQueries/Collector/squirrel.html.twig',
            'id'       => 'squirrel',
            'priority' => 250,
        ]);
        $container->setDefinition(SquirrelDataCollector::class, $dataCollector);

        $twigExtension = new Definition(SquirrelQueriesExtension::class);
        $twigExtension->addTag('twig.extension');
        $container->setDefinition(SquirrelQueriesExtension::class, $twigExtension);
    }
}
