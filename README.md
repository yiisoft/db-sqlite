<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://yiisoft.github.io/docs/images/yii_logo.svg" height="80px">
    </a>
    <a href="https://www.sqlite.org/" target="_blank">
        <img src="https://upload.wikimedia.org/wikipedia/commons/3/38/SQLite370.svg" height="80px">
    </a>
    <h1 align="center">Yii DataBase SQLite Extension</h1>
    <br>
</p>

**Yii DataBase SQLite Extension** is a package for working with [SQLite] databases in PHP. It is a part of the [YiiFramework], which is a high-performance, component-based framework for developing modern web applications.

**Yii DataBase SQLite Extension** package provides a set of classes for interacting with [SQLite] databases in PHP. It includes a database connection class, a command builder class, and a set of classes for representing database tables and rows as PHP objects.

Using the **Yii DataBase SQLite Extension** package, you can perform a variety of tasks with [SQLite] databases in PHP, such as connecting to a database, executing SQL queries, and working with database transactions. You can also use it to create and manipulate database tables and rows, and to perform advanced database operations such as joins and aggregates.

Overall, **Yii DataBase SQLite Extension** is a powerful tool for working with [SQLite] databases in PHP, and is well-suited for use in web applications built with the [YiiFramework].

It is used in [YiiFramework] but can be used separately.

[SQLite]: https://www.sqlite.org/
[YiiFramework]: https://github.com/yiisoft/core

[![Latest Stable Version](https://poser.pugx.org/yiisoft/db-sqlite/v/stable.png)](https://packagist.org/packages/yiisoft/db-sqlite)
[![Total Downloads](https://poser.pugx.org/yiisoft/db-sqlite/downloads.png)](https://packagist.org/packages/yiisoft/db-sqlite)
[![rector](https://github.com/yiisoft/db-sqlite/actions/workflows/rector.yml/badge.svg)](https://github.com/yiisoft/db-sqlite/actions/workflows/rector.yml)
[![codecov](https://codecov.io/gh/yiisoft/db-sqlite/branch/master/graph/badge.svg?token=YXUHCPPITH)](https://codecov.io/gh/yiisoft/db-sqlite)
[![StyleCI](https://github.styleci.io/repos/145220194/shield?branch=master)](https://github.styleci.io/repos/145220194?branch=master)


### Support version

|  PHP | Sqlite Version            |  CI-Actions
|:----:|:------------------------:|:---:|
|**8.0 - 8.2**| **3:latest**|[![build](https://github.com/yiisoft/db-sqlite/actions/workflows/build.yml/badge.svg?branch=dev)](https://github.com/yiisoft/db-sqlite/actions/workflows/build.yml) [![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fdb-sqlite%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/db-sqlite/master) [![static analysis](https://github.com/yiisoft/db-sqlite/actions/workflows/static.yml/badge.svg?branch=dev)](https://github.com/yiisoft/db-sqlite/actions/workflows/static.yml) [![type-coverage](https://shepherd.dev/github/yiisoft/db-sqlite/coverage.svg)](https://shepherd.dev/github/yiisoft/db-sqlite)


### Installation

The package could be installed via composer:

```php
composer require yiisoft/db-sqlite
```

### Config with [YiiFramework]

The configuration with [container di](https://github.com/yiisoft/di) of [YiiFramework].

Also you can use any container di which implements [PSR-11](https://www.php-fig.org/psr/psr-11/).

db.php

```php
<?php

declare(strict_types=1);

use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\ConnectionPDO;

return [
    ConnectionInterface::class => [
        'class' => ConnectionPDO::class,
        '__construct()' => [
            'driver' => $params['yiisoft/db-sqlite']['dsn']
        ]
    ]
];
```

params.php

```php
<?php

declare(strict_types=1);

use Yiisoft\Db\Sqlite\PDODriver;

return [
    'yiisoft/db-sqlite' => [
        'dsn' => (new PDODriver('sqlite', 'memory'))->asString(),
    ]
];
```

### Config without [YiiFramework]

```php
<?php

declare(strict_types=1);

use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Sqlite\ConnectionPDO;
use Yiisoft\Db\Sqlite\PDODriver;

// Or any other PSR-16 cache implementation.
$arrayCache = new ArrayCache();

// Or any other PSR-6 cache implementation.
$cache = new Cache($arrayCache); 

// Or any other PDO driver.
$pdoDriver = new PDODriver('sqlite', 'memory'); 
$schemaCache = new SchemaCache($cache);
$db = new ConnectionPDO($pdoDriver, $schemaCache);
```

### Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:

```shell
./vendor/bin/phpunit
```

### Mutation testing

The package tests are checked with [Infection](https://infection.github.io/) mutation framework. To run it:

```shell
./vendor/bin/infection
```

### Static analysis

The code is statically analyzed with [Psalm](https://psalm.dev/). To run static analysis:

```shell
./vendor/bin/psalm
```

### Rector

Use [Rector](https://github.com/rectorphp/rector) to make codebase follow some specific rules or 
use either newest or any specific version of PHP: 

```shell
./vendor/bin/rector
```

### Composer require checker

This package uses [composer-require-checker](https://github.com/maglnet/ComposerRequireChecker) to check if all dependencies are correctly defined in `composer.json`.

To run the checker, execute the following command:

```shell
./vendor/bin/composer-require-checker
```

### Support the project

[![Open Collective](https://img.shields.io/badge/Open%20Collective-sponsor-7eadf1?logo=open%20collective&logoColor=7eadf1&labelColor=555555)](https://opencollective.com/yiisoft)

### Follow updates

[![Official website](https://img.shields.io/badge/Powered_by-Yii_Framework-green.svg?style=flat)](https://www.yiiframework.com/)
[![Twitter](https://img.shields.io/badge/twitter-follow-1DA1F2?logo=twitter&logoColor=1DA1F2&labelColor=555555?style=flat)](https://twitter.com/yiiframework)
[![Telegram](https://img.shields.io/badge/telegram-join-1DA1F2?style=flat&logo=telegram)](https://t.me/yii3en)
[![Facebook](https://img.shields.io/badge/facebook-join-1DA1F2?style=flat&logo=facebook&logoColor=ffffff)](https://www.facebook.com/groups/yiitalk)
[![Slack](https://img.shields.io/badge/slack-join-1DA1F2?style=flat&logo=slack)](https://yiiframework.com/go/slack)

## License

The Yii DataBase SQLite Extension is free software. It is released under the terms of the BSD License.
Please see [`LICENSE`](./LICENSE.md) for more information.

Maintained by [Yii Software](https://www.yiiframework.com/).
