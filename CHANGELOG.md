# SQLite driver for Yii Database Change Log

## 2.0.0 December 05, 2025

- New #273: Implement `ColumnSchemaInterface` classes according to the data type of database table columns
  for type casting performance. Related with yiisoft/db#752 (@Tigrov)
- New #310, #393: Add JSON overlaps condition builder (@Tigrov)
- New #314, #325: Implement `ColumnFactory` class (@Tigrov)
- New #318: Realize `ColumnBuilder` class (@Tigrov)
- New #319: Add parameters `$ifExists` and `$cascade` to `CommandInterface::dropTable()` and
  `DDLQueryBuilderInterface::dropTable()` methods (@vjik)
- New #322, #327: Add `ColumnDefinitionBuilder` class (@Tigrov)
- New #328: Override `QueryBuilder::prepareBinary()` method (@Tigrov)
- New #344: Add `caseSensitive` option to like condition (@vjik)
- New #348: Realize `Schema::loadResultColumn()` method (@Tigrov)
- New #354: Add `FOR` clause to query (@vjik)
- New #355: Use `DateTimeColumn` class for datetime column types (@Tigrov)
- New #356, #357: Implement `DMLQueryBuilder::upsertReturning()` method (@Tigrov)
- New #384, #390: Implement `ArrayMergeBuilder`, `GreatestBuilder` and `LeastBuilder` classes (@Tigrov)
- New #385: Add `Connection::getColumnBuilderClass()` method (@Tigrov)
- New #404: Add enumeration column type support (@vjik)
- New #408: Add source of column information (@Tigrov)
- Chg #307: Replace call of `SchemaInterface::getRawTableName()` to `QuoterInterface::getRawTableName()` (@Tigrov)
- Chg #330: Update `QueryBuilder` constructor (@Tigrov)
- Chg #339, #407: Change supported PHP versions to `8.1 - 8.5` (@Tigrov, @vjik)
- Chg #339: Change return type of `Command::insertWithReturningPks()` method to `array|false` (@Tigrov)
- Chg #342: Remove usage of `hasLimit()` and `hasOffset()` methods of `DQLQueryBuilder` class (@Tigrov)
- Chg #343: Remove `yiisoft/json` dependency (@Tigrov)
- Chg #362: Replace column and table name quote character from ` to " (@Tigrov)
- Chg #364: Use `\InvalidArgumentException` instead of `Yiisoft\Db\Exception\InvalidArgumentException` (@DikoIbragimov)
- Chg #391: Update expression namespaces according to changes in `yiisoft/db` package (@Tigrov)
- Chg #402: Throw exception on "unsigned" column usage (@vjik)
- Enh #289, #352: Implement and use `SqlParser` class (@Tigrov)
- Enh #312: Update `bit` type according to main PR yiisoft/db#860 (@Tigrov)
- Enh #315: Raise minimum PHP version to `^8.1` with minor refactoring (@Tigrov)
- Enh #317: Separate column type constants (@Tigrov)
- Enh #320: Update according changes in `ColumnSchemaInterface` (@Tigrov)
- Enh #323, #373: Refactor `Dsn` class (@Tigrov)
- Enh #324: Set more specific result type in `Connection` methods `createCommand()` and `createTransaction()` (@vjik)
- Enh #326: Refactor `Schema::normalizeDefaultValue()` method and move it to `ColumnFactory` class (@Tigrov)
- Enh #329: Use `ColumnDefinitionBuilder` to generate table column SQL representation (@Tigrov)
- Enh #332: Remove `ColumnInterface` (@Tigrov)
- Enh #334: Rename `ColumnSchemaInterface` to `ColumnInterface` (@Tigrov)
- Enh #335: Replace `DbArrayHelper::getColumn()` with `array_column()` (@Tigrov)
- Enh #337: Move `JsonExpressionBuilder` and JSON type tests to `yiisoft/db` package (@Tigrov)
- Enh #345: Refactor according changes in `db` package (@Tigrov)
- Enh #347: Remove `getCacheKey()` and `getCacheTag()` methods from `Schema` class (@Tigrov)
- Enh #350, #351: Use `DbArrayHelper::arrange()` instead of `DbArrayHelper::index()` method (@Tigrov)
- Enh #356, #357: Refactor `Command::insertWithReturningPks()` and `DMLQueryBuilder::upsert()` methods (@Tigrov)
- Enh #358, #372: Refactor constraints (@Tigrov)
- Enh #360, #361: Implement `DMLQueryBuilder::insertReturningPks()` method (@Tigrov)
- Enh #368: Provide `yiisoft/db-implementation` virtual package (@vjik)
- Enh #371, #374: Adapt to conditions refactoring in `yiisoft/db` package (@vjik)
- Enh #377: Remove `TableSchema` class and refactor `Schema` class (@Tigrov)
- Enh #380: Support column's collation (@Tigrov)
- Enh #387: Refactor `DMLQueryBuilder::upsert()` method (@Tigrov)
- Bug #338: Explicitly mark nullable parameters (@vjik)

## 1.2.0 March 21, 2024

- Enh #281: Remove unused code in `Command` class (@vjik)
- Enh #282: Change property `Schema::$typeMap` to constant `Schema::TYPE_MAP` (@Tigrov)
- Enh #283: Remove unnecessary check for array type in `Schema::loadTableIndexes()` (@Tigrov)
- Enh #287: Resolve deprecated methods (@Tigrov)
- Enh #288: Minor refactoring of `DDLQueryBuilder` and `Schema` (@Tigrov)

## 1.1.0 November 12, 2023

- Enh #263: Support json type (@Tigrov)
- Enh #278: Move methods from `Command` to `AbstractPdoCommand` class (@Tigrov)
- Bug #268: Fix foreign keys: support multiple foreign keys referencing to one table and possible null columns for reference (@Tigrov)
- Bug #271: Refactor `DMLQueryBuilder`, related with yiisoft/db#746 (@Tigrov)

## 1.0.1 July 24, 2023

- Enh #260: Typecast refactoring (@Tigrov)
- Enh #262: Refactoring of `Schema::normalizeDefaultValue()` method (@Tigrov)

## 1.0.0 April 12, 2023

- Initial release.
