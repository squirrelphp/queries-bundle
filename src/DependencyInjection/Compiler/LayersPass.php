<?php

namespace Squirrel\QueriesBundle\DependencyInjection\Compiler;

use Squirrel\Queries\DBInterface;
use Squirrel\Queries\Doctrine\DBErrorHandler;
use Squirrel\Queries\Doctrine\DBMySQLImplementation;
use Squirrel\Queries\Doctrine\DBPostgreSQLImplementation;
use Squirrel\Queries\Doctrine\DBSQLiteImplementation;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Create DBInterface services with different layers
 */
class LayersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        // If no custom error handler has been defined, we use our default one
        if (!$container->hasDefinition('squirrel.error_handler')) {
            $dbErrorHandler = new Definition(DBErrorHandler::class);
            $dbErrorHandler->setPublic(false);

            $container->setDefinition('squirrel.error_handler', $dbErrorHandler);
        }

        // Find all layers (implementing DBRawInterface) which decorate all DBInterface services
        $taggedServices = $container->findTaggedServiceIds('squirrel.layer');

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

        // Get all SQL connection definitions
        $services = $container->findTaggedServiceIds('squirrel.connection');

        // Go through all connection tags
        foreach ($services as $id => $tags) {
            foreach ($tags as $tag) {
                // Connection with this name already exists - each connection name has to be unique
                if ($container->hasDefinition('squirrel.connection.' . $tag['connectionName'])) {
                    throw new \LogicException(
                        'You have multiple squirrel connections with same name - ' .
                        'make sure to have unique connection names'
                    );
                }

                // Custom implementation was defined as a service
                if ($container->hasDefinition('squirrel.implementation.' . $tag['connectionName'])) {
                    $baseImplementation = $container->findDefinition(
                        'squirrel.implementation.' . $tag['connectionName']
                    );
                } else { // Use a default implementation
                    // Connection type can be a class name with a unique implementation
                    if (\class_exists($tag['connectionType'])) {
                        $baseImplementation = new Definition(
                            $tag['connectionType'],
                            [new Reference(\strval($id))]
                        );
                    } else { // Default connection type with existing implementation
                        switch ($tag['connectionType']) {
                            case 'mysql':
                                $implementationClass = DBMySQLImplementation::class;
                                break;
                            case 'pgsql':
                                $implementationClass = DBPostgreSQLImplementation::class;
                                break;
                            case 'sqlite':
                                $implementationClass = DBSQLiteImplementation::class;
                                break;
                            default:
                                throw new \InvalidArgumentException(
                                    'Only MySQL, Postgres and SQLite are currently supported by squirrel, ' .
                                    'yet you have specified none of those as the connection type.'
                                );
                        }

                        // Doctrine MySQL implementation of DBInterface
                        $baseImplementation = new Definition(
                            $implementationClass,
                            [new Reference(\strval($id))]
                        );
                    }
                }

                // The very lowest layer is the base implementation
                $topmostLayerDefinition = clone $baseImplementation;

                // Add the event listeners to our event manager
                foreach ($taggedServicesOrdered as $priority => $entriesForThisPriority) {
                    foreach ($entriesForThisPriority as $callData) {
                        // Break up service and attributes
                        [$id, $attributes] = $callData;

                        // Get definition for the current service
                        $definition = clone $container->findDefinition(\strval($id));

                        // Add lower layer to this service
                        $definition->addMethodCall('setLowerLayer', [$topmostLayerDefinition]);

                        // Mark this new service as the layer on top now
                        $topmostLayerDefinition = $definition;
                    }
                }

                // Set connection service name - relevant if there are multiple connections
                $container->setDefinition('squirrel.connection.' . $tag['connectionName'], $topmostLayerDefinition);

                // If this is the default connection we enable DBInterface type hints
                if (\boolval($tag['isDefault']) === true) {
                    // Only one default connection can exists, everything else is an error
                    if ($container->hasDefinition(DBInterface::class)) {
                        throw new \LogicException(
                            'You have multiple squirrel default connections - ' .
                            'make sure you only defined one connection as "default"'
                        );
                    }

                    $container->setDefinition(DBInterface::class, $topmostLayerDefinition);
                }
            }
        }
    }
}
