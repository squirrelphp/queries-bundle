<?php

namespace Squirrel\QueriesBundle;

use Squirrel\QueriesBundle\DependencyInjection\Compiler\LayersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SquirrelQueriesBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        // Generate decorated database connections with compiler pass
        $container->addCompilerPass(new LayersPass());
    }

    public function getContainerExtension()
    {
        // No container extension needed
    }
}
