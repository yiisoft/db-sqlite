# SQLite driver for Yii Database Change Log

## 1.0.2 under development

- Enh #263: Support json type (@Tigrov)
- Bug #268: Fix foreign keys: support multiple foreign keys referencing to one table and possible null columns for reference (@Tigrov)
- Enh #273: Implement `ColumnSchemaInterface` classes according to the data type of database table columns
  for type casting performance. Related with yiisoft/db#752 (@Tigrov)

## 1.0.1 July 24, 2023

- Enh #260: Typecast refactoring (@Tigrov)
- Enh #262: Refactoring of `Schema::normalizeDefaultValue()` method (@Tigrov)

## 1.0.0 April 12, 2023

- Initial release.
