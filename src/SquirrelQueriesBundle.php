<?php

namespace Squirrel\QueriesBundle;

use Squirrel\QueriesBundle\DependencyInjection\Compiler\LayersPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @codeCoverageIgnore Just adds one compiler pass to Symfony, there is nothing to test
 */
class SquirrelQueriesBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        // Generate decorated database connections with compiler pass
        // Priority (third argument) has to be higher than 0, otherwise ProfilerPass executes before us
        // and our DataCollector is not added to Symfony profiler (if it is active)
        $container->addCompilerPass(new LayersPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
    }

    public function getContainerExtension()
    {
        // No container extension needed
        return null;
    }
}
