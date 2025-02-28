<?php

namespace Squirrel\QueriesBundle\Tests;

use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\TestCase;
use Squirrel\QueriesBundle\DependencyInjection\Configuration;

final class ConfigurationTest extends TestCase
{
    use ConfigurationTestCaseTrait;

    protected function getConfiguration(): Configuration
    {
        return new Configuration('squirrel_queries');
    }

    public function testValidConfigurations(): void
    {
        $this->assertConfigurationIsValid(
            [],
        );

        $this->assertConfigurationIsValid(
            [
                [
                    'connections' => [
                        'default' => [
                            'type' => 'sqlite',
                        ],
                    ],
                ],
            ],
        );
    }

    public function testInvalidConfigurations(): void
    {
        $this->assertConfigurationIsInvalid(
            [
                [
                    'something' => [
                        'default' => [
                            'type' => 'sqlite',
                        ],
                    ],
                ],
            ],
        );
    }
}
