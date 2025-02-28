<?php

namespace Squirrel\QueriesBundle\Tests;

use PHPUnit\Framework\TestCase;
use Squirrel\QueriesBundle\DependencyInjection\SquirrelQueriesExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ExtensionTest extends TestCase
{
    public function testBuild(): void
    {
        $containerBuilder = new ContainerBuilder();

        $extension = new SquirrelQueriesExtension();

        $extension->load([], $containerBuilder);

        $containerBuilder->compile();
    }
}
