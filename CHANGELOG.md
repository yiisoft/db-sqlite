# SQLite driver for Yii Database Change Log

## 2.0.0 under development

- Enh #289: Implement `SqlParser` and `ExpressionBuilder` driver classes (@Tigrov)
- New #273: Implement `ColumnSchemaInterface` classes according to the data type of database table columns
  for type casting performance. Related with yiisoft/db#752 (@Tigrov)
- Chg #307: Replace call of `SchemaInterface::getRawTableName()` to `QuoterInterface::getRawTableName()` (@Tigrov)
- New #310: Add JSON overlaps condition builder (@Tigrov)
- Enh #312: Update `bit` type according to main PR yiisoft/db#860 (@Tigrov)
- Enh #315: Raise minimum PHP version to `^8.1` with minor refactoring (@Tigrov)
- New #314: Implement `ColumnFactory` class (@Tigrov)
- Enh #317: Separate column type constants (@Tigrov)
- Enh #318: Realize `ColumnBuilder` class (@Tigrov)
- Enh #319: Set more specific result type in `Connection::createCommand()` method (@vjik)
- Enh #320: Update according changes in `ColumnSchemaInterface` (@Tigrov)
- New #322: Add `ColumnDefinitionBuilder` class (@Tigrov)
- Enh #323: Refactor `Dsn` class (@Tigrov)

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
