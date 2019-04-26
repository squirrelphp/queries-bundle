<?php

namespace Squirrel\QueriesBundle;

use Squirrel\QueriesBundle\DependencyInjection\Compiler\LayersPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SquirrelQueriesBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        // Generate decorated database connections with compiler pass
        // Priority (third argument) has to be higher than 0, otherwise ProfilerPass executes before us
        // and our DataCollector is not added to Symfony profiler (if it is active)
        $container->addCompilerPass(new LayersPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
    }

    public function getContainerExtension()
    {
        // No container extension needed
    }
}
