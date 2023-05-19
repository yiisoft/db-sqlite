<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://yiisoft.github.io/docs/images/yii_logo.svg" height="80px">
    </a>
    <a href="https://www.sqlite.org/" target="_blank">
        <img src="https://upload.wikimedia.org/wikipedia/commons/3/38/SQLite370.svg" height="80px">
    </a>
    <h1 align="center">SQLite driver for Yii Database</h1>
    <br>
</p>

SQLite driver for [Yii Database](https://github.com/yiisoft/db) is a package for working with [SQLite] databases in PHP.

The package provides a set of classes for interacting with [SQLite] databases in PHP. It includes a database connection class,
a command builder class, and a set of classes for representing database tables and rows as PHP objects.

You can perform a variety of tasks with [SQLite] databases in PHP, such as connecting to a database, executing SQL queries,
and working with database transactions. You can also use it to create and manipulate database tables and rows, and to
perform advanced database operations such as joins and aggregates.

[SQLite]: https://www.sqlite.org/

[![Latest Stable Version](https://poser.pugx.org/yiisoft/db-sqlite/v/stable.png)](https://packagist.org/packages/yiisoft/db-sqlite)
[![Total Downloads](https://poser.pugx.org/yiisoft/db-sqlite/downloads.png)](https://packagist.org/packages/yiisoft/db-sqlite)
[![rector](https://github.com/yiisoft/db-sqlite/actions/workflows/rector.yml/badge.svg)](https://github.com/yiisoft/db-sqlite/actions/workflows/rector.yml)
[![codecov](https://codecov.io/gh/yiisoft/db-sqlite/branch/master/graph/badge.svg?token=YXUHCPPITH)](https://codecov.io/gh/yiisoft/db-sqlite)
[![StyleCI](https://github.styleci.io/repos/145220194/shield?branch=master)](https://github.styleci.io/repos/145220194?branch=master)

## Support version

|  PHP | Sqlite Version            |  CI-Actions
|:----:|:------------------------:|:---:|
|**8.0 - 8.2**| **3:latest**|[![build](https://github.com/yiisoft/db-sqlite/actions/workflows/build.yml/badge.svg?branch=dev)](https://github.com/yiisoft/db-sqlite/actions/workflows/build.yml) [![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fdb-sqlite%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/db-sqlite/master) [![static analysis](https://github.com/yiisoft/db-sqlite/actions/workflows/static.yml/badge.svg?branch=dev)](https://github.com/yiisoft/db-sqlite/actions/workflows/static.yml) [![type-coverage](https://shepherd.dev/github/yiisoft/db-sqlite/coverage.svg)](https://shepherd.dev/github/yiisoft/db-sqlite)

## Installation

The package could be installed via composer:

```php
composer require yiisoft/db-sqlite
```

## Usage 

For config connection to SQLite database check [Connecting SQLite](https://github.com/yiisoft/db/blob/master/docs/en/connection/sqlite.md).

[Check the documentation docs](https://github.com/yiisoft/db/blob/master/docs/en/README.md) to learn about usage.

## Testing

[Check the documentation](/docs/en/testing.md) to learn about testing.

## Support the project

[![Open Collective](https://img.shields.io/badge/Open%20Collective-sponsor-7eadf1?logo=open%20collective&logoColor=7eadf1&labelColor=555555)](https://opencollective.com/yiisoft)

## Follow updates

[![Official website](https://img.shields.io/badge/Powered_by-Yii_Framework-green.svg?style=flat)](https://www.yiiframework.com/)
[![Twitter](https://img.shields.io/badge/twitter-follow-1DA1F2?logo=twitter&logoColor=1DA1F2&labelColor=555555?style=flat)](https://twitter.com/yiiframework)
[![Telegram](https://img.shields.io/badge/telegram-join-1DA1F2?style=flat&logo=telegram)](https://t.me/yii3en)
[![Facebook](https://img.shields.io/badge/facebook-join-1DA1F2?style=flat&logo=facebook&logoColor=ffffff)](https://www.facebook.com/groups/yiitalk)
[![Slack](https://img.shields.io/badge/slack-join-1DA1F2?style=flat&logo=slack)](https://yiiframework.com/go/slack)

## License

The Yii DataBase SQLite Extension is free software. It is released under the terms of the BSD License.
Please see [`LICENSE`](./LICENSE.md) for more information.

Maintained by [Yii Software](https://www.yiiframework.com/).
