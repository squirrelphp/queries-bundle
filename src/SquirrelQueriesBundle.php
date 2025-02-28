<?php

namespace Squirrel\QueriesBundle;

use Squirrel\QueriesBundle\DependencyInjection\Compiler\LayersPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SquirrelQueriesBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        // Generate decorated database connections with compiler pass
        // Priority (third argument) has to be higher than 0, otherwise ProfilerPass executes earlier than us
        // and our DataCollector is not added to Symfony profiler (if it is active)
        $container->addCompilerPass(new LayersPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
    }

    // Set modern directory structure for bundles
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
