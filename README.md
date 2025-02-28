Squirrel Queries Integration for Symfony
========================================

[![Build Status](https://img.shields.io/travis/com/squirrelphp/queries-bundle.svg)](https://travis-ci.com/squirrelphp/queries-bundle) ![PHPStan](https://img.shields.io/badge/style-level%20max-success.svg?style=flat-round&label=phpstan) [![Packagist Version](https://img.shields.io/packagist/v/squirrelphp/queries-bundle.svg?style=flat-round)](https://packagist.org/packages/squirrelphp/queries-bundle) [![PHP Version](https://img.shields.io/packagist/php-v/squirrelphp/queries-bundle.svg)](https://packagist.org/packages/squirrelphp/queries-bundle) [![Software License](https://img.shields.io/badge/license-MIT-success.svg?style=flat-round)](LICENSE)

Integration of [squirrelphp/queries](https://github.com/squirrelphp/queries) into Symfony through service tags and bundle configuration.

Installation
------------

```
composer require squirrelphp/queries-bundle
```

Configuration
-------------

Enable the bundle in your project by adding `Squirrel\QueriesBundle\SquirrelQueriesBundle` to the list of bundles (usually in `config/bundles.php`).

Create a configuration file for your connections, here is an example:

    squirrel_queries:
        connections:
            default:
                type: 'mysql'
                host: '%env(DB_HOST)'
                port: '%env(DB_PORT)'
                user: '%env(DB_USER)'
                password: '%env(DB_PASSWORD)'
                dbname: '%env(DB_DBNAME)'
            error:
                type: 'mariadb'
                host: 'mariadb_database'
                port: 9999
                user: 'app'
                password: 'hg84kdhgjg84'
                dbname: 'app_prod'
                ssl:
                    rootCertificatePath: '/home/app/ssl/ca.crt'
                    privateKeyPath: '/home/app/ssl/ca.key'
                    certificatePath: '/home/app/ssl/cert.crt'
                    verification: 'CaAndHostname'
            sqlite:
                type: 'sqlite'
                path: '/home/database.db'

The example shows all possible values you can set (except for `charset`, which is explained further below but should ideally be omitted/left at the default).
`type` can only be either `mysql`, `mariadb`, `pgsql` or `sqlite`. `mysql` and `mariadb` are functionally equivalent.

Type `sqlite` only supports the optional path parameter to the database file, when not provided (or null) it creates an in-memory database.

For `mysql`, `mariadb` and `pgsql` connections the following values can be provided:

- `host` can be a hostname or IP in order to connect to the database server
- `port` is an integer and is optional (defaults to 3306 for mysql/mariadb, 5432 for postgresql)
- `user` is the username with which to connect
- `password` is the password to connect with
- `dbname` is the default database to open and is optional, by default (or with a value of null) no database is opened
- `charset` can be provided and defaults to `utf8mb4` for mysql/mariadb and `UTF8` for postgresql, the recommendation is to leave it at the default
- `ssl` sets additional values so the connection will be encrypted (if omitted or set to null no encryption is used):
  - `rootCertificatePath` is the path to the root certificate file used by the database server
  - `privateKeyPath` is the path to the private key the client sends to the database server
  - `certificatePath` is the path to the certificate file the client sends to the database server
  - `verification` can be one of the following values:
    - `CaAndHostname` (which is the default) enforces that the CA of the database server certificate matches the CA in `rootCertificatePath` and that the hostname of the server certificate matches the hostname in `host`
    - `Ca` enforces that the CA of the database server certificate matches the CA in `rootCertificatePath` (the hostname is not verified) - beware that this does not work for mysql/mariadb and will throw an exception for those connections, because just checking the CA is not supported by PHP for mysql/mariadb (it works as expected for postgresql)
    - `None` will not check the CA or the hostname of the server certificate

The connection named `default` will be registered as `Squirrel\Queries\DBInterface` which you can then use as a type hint in your services. All connections also get registered as services that start with `squirrel.connection.`, so in the above example the following services would be defined: `squirrel.connection.default`, `squirrel.connection.error` and `squirrel.connection.sqlite`.

### Common behavior of all connections

The following options are hardcoded into all connections and mostly differ from the common defaults in PHP database connections (see [squirrelphp/connection](https://github.com/squirrelphp/connection) for more details):

- Emulation of prepares is turned off, so real query and values separation is enabled instead of emulating it (which is usually the default in PHP). You should not notice this in any way, even in terms of performance: it was tested, and when script and database are running in the same network there is no measureable difference. Your script and database would need to be apart by some distance for any possible effect to manifest. On the other hand, the separation of queries and values has undeniable security benefits and is the way the underlying database client libraries are designed to work.
- For MySQL/MariaDB, the "affected rows" reported for UPDATE queries are the "found rows" in the database, even if nothing changed by executing the UPDATE. By default with MySQL/MariaDB in PHP you get the "changed rows", which is a behavior no other database has or even supports, so MySQL/MariaDB is configured to behave more like any other database. Getting the "found rows" count can be useful information, while relying on the "changed rows" count relies on special behavior in one database system.
- Executing multiple statements in one query is disabled. Multiple statements per query were a source of security exploits in the past, are often not easy to port between different database systems and have little real world relevance. Use transactions instead, which is a guaranteed way to execute multiple statements together, or use parallel connections / multiple connections if speed is an issue.

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