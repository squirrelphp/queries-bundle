Squirrel Queries Integration for Symfony
========================================

[![Build Status](https://img.shields.io/travis/com/squirrelphp/queries-bundle.svg)](https://travis-ci.com/squirrelphp/queries-bundle) [![Test Coverage](https://api.codeclimate.com/v1/badges/811a4b617f29bd286a75/test_coverage)](https://codeclimate.com/github/squirrelphp/queries-bundle/test_coverage) ![PHPStan](https://img.shields.io/badge/style-level%207-success.svg?style=flat-round&label=phpstan) [![Packagist Version](https://img.shields.io/packagist/v/squirrelphp/queries-bundle.svg?style=flat-round)](https://packagist.org/packages/squirrelphp/queries-bundle) [![PHP Version](https://img.shields.io/packagist/php-v/squirrelphp/queries-bundle.svg)](https://packagist.org/packages/squirrelphp/queries-bundle) [![Software License](https://img.shields.io/badge/license-MIT-success.svg?style=flat-round)](LICENSE)

Integration of [squirrelphp/queries](https://github.com/squirrelphp/queries) into Symfony through service tags and bundle configuration.

Installation
------------

```
composer require squirrelphp/queries-bundle
```

Configuration
-------------

Enable the bundle in your AppKernel by adding `Squirrel\QueriesBundle\SquirrelQueriesBundle` to the list of bundles.

Create a Symfony service for each of your Doctrine DBAL connections and tag it with squirrel.connection, for example like this:

    services:
        database_connection:
            class: Doctrine\DBAL\Connection
            factory: Doctrine\DBAL\DriverManager::getConnection
            arguments:
                $params:
                    driver:   pdo_mysql
                    host:     "%database_host%"
                    port:     "%database_port%"
                    dbname:   "%database_name%"
                    user:     "%database_user%"
                    password: "%database_password%"
                    charset:  UTF8
            tags:
                - { name: squirrel.connection, connectionName: default, connectionType: mysql, isDefault: true }
                
You can use any DBAL connection settings, and the service name (`database_connection` in this case) is irrelevant. For the tag, just make sure:

- to use one of the three supported database types as `connectionType`: `mysql` for MySQL/MariaDB, `pgsql` for PostgreSQL, `sqlite` for SQLite
- set a unique `connectionName` for each tag entry

If you set `isDefault` to true, that connection will be registered as `Squirrel\Queries\DBInterface` which you can then use as a type hint in your services.

If you have multiple connections and need to reference them in your service definitions, you can specifically inject them through the `connectionName` - just prefix it with `squirrel.connection.` to get the correct registered service name. So for a connectionName of `mysql_remote`, the service in Symfony would be called `squirrel.connection.mysql_remote`.

Adding layers
-------------

By default, this bundle creates DBInterface services with an implementation layer and an error handling layer (see [squirrelphp/queries](https://github.com/squirrelphp/queries) for details).

If you want to add additional layers to decorate DBInterface, create a service for each additional layer and tag it with `squirrel.layer`. Make sure the service implements `Squirrel\Queries\DBRawInterface` and to add the trait `Squirrel\Queries\DBPassToLowerLayerTrait` in the service. Define a `priority` for the tag and set it to below zero if you want to inject it between the implementation and the error handler, or above zero if it should be above the error handler.

Example for the service definition of a logger which logs deadlocks / connection timeouts before the error handler automatically retries the query/transaction:

    services:
        Squirrel\QueriesBundle\Examples\SQLLogTemporaryFailuresListener:
            tags:
                - { name: squirrel.layer, priority: -250 }
    
Because the priority is below zero it is a layer beneath the error handler. You can find a possible implementation in the examples directory.

Symfony Profiler
----------------

When using Symfony Profiler this library offers similar integration like the DoctrineBundle automatically - so you can check what queries were sent to the database and how long they took.

More is coming
--------------

For now this is a super minimalistic bundle with no configuration of its own, and no tests. This will probably be changed, although much configuration should not be needed anyway.