# SQLite driver for Yii Database Change Log

## 1.1.1 under development

- Enh #302: Remove unused code in `Command` class (@vjik)

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
