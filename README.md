<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://avatars0.githubusercontent.com/u/993323" height="80px">
    </a>
    <a href="https://www.sqlite.org/" target="_blank">
        <img src="https://upload.wikimedia.org/wikipedia/commons/3/38/SQLite370.svg" height="80px">
    </a>
    <h1 align="center">Yii DataBase SQLite Extension</h1>
    <br>
</p>

This package provides [SQLite] extension for [Yii DataBase] library.
It is used in [Yii Framework] but is supposed to be usable separately.

[SQLite]: https://www.sqlite.org/
[Yii DataBase]: https://github.com/yiisoft/db
[Yii Framework]: https://github.com/yiisoft/core

[![Latest Stable Version](https://poser.pugx.org/yiisoft/db-sqlite/v/stable.png)](https://packagist.org/packages/yiisoft/db-sqlite)
[![Total Downloads](https://poser.pugx.org/yiisoft/db-sqlite/downloads.png)](https://packagist.org/packages/yiisoft/db-sqlite)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yiisoft/db-sqlite/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/db-sqlite/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/yiisoft/db-sqlite/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/db-sqlite/?branch=master)


## Support version

|  PHP | Sqlite Version            |  CI-Actions
|:----:|:------------------------:|:---:|
|**7.4 - 8.0**| **3:latest**|[![Build status](https://github.com/yiisoft/db-sqlite/workflows/build/badge.svg)](https://github.com/yiisoft/db-sqlite/actions?query=workflow%3Abuild) [![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fdb-sqlite%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/db-sqlite/master) [![static analysis](https://github.com/yiisoft/db-sqlite/workflows/static%20analysis/badge.svg)](https://github.com/yiisoft/db-sqlite/actions?query=workflow%3A%22static+analysis%22) [![type-coverage](https://shepherd.dev/github/yiisoft/db-sqlite/coverage.svg)](https://shepherd.dev/github/yiisoft/db-sqlite)


## Installation

The package could be installed via composer:

```php
composer require yiisoft/db-sqlite
```

## Configuration

Di-Container:

```php
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Log\Logger;
use Yiisoft\Log\Target\File\FileRotator;
use Yiisoft\Log\Target\File\FileRotatorInterface;
use Yiisoft\Log\Target\File\FileTarget;
use Yiisoft\Profiler\Profiler;

return [
    ContainerInterface::class => static function (ContainerInterface $container) {
        return $container;
    },

    Aliases::class => [
        '@root' => dirname(__DIR__, 1), // directory / packages.
        '@runtime' => '@root/runtime' 
    ],

    CacheInterface::class => static function () {
        return new Cache(new ArrayCache());
    },

    FileRotatorInterface::class => static function () {
        return new FileRotator(10);
    },

    LoggerInterface::class => static function (ContainerInterface $container) {
        $aliases = $container->get(Aliases::class);
        $fileRotator = $container->get(FileRotatorInterface::class);

        $fileTarget = new FileTarget(
            $aliases->get('@runtime/logs/app.log'),
            $fileRotator
        );

        $fileTarget->setLevels(
            [
                LogLevel::EMERGENCY,
                LogLevel::ERROR,
                LogLevel::WARNING,
                LogLevel::INFO,
                LogLevel::DEBUG
            ]
        );

        return new Logger(['file' => $fileTarget]);
    },

    Profiler::class => static function (ContainerInterface $container) {
        return new Profiler($container->get(LoggerInterface::class));
    },

    ConnectionInterface::class  => static function (ContainerInterface $container) use ($params) {
        $connection = new Connection(
            $container->get(CacheInterface::class),
            $container->get(LoggerInterface::class),
            $container->get(Profiler::class),
            $params['yiisoft/db-sqlite']['dsn'],
        );

        return $connection;
    }
];
```
Params.php

```php
protected function params(): array
{
    return [
        'yiisoft/db-sqlite' => [
            'dsn' => 'sqlite:' . __DIR__ . '/Data/yiitest.sq3',
        ]
    ];
}
```

## Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:

```php
./vendor/bin/phpunit
```

Note: You must have SQLITE installed to run the tests, it supports all SQLITE version 3.

## Mutation testing

The package tests are checked with [Infection](https://infection.github.io/) mutation framework. To run it:

```php
./vendor/bin/infection
```

## Static analysis

The code is statically analyzed with [Psalm](https://psalm.dev/docs/). To run static analysis:

```php
./vendor/bin/psalm
```
