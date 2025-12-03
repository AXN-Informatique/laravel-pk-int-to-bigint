# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel package that converts database primary keys and related foreign keys from INT to BIGINT. It's designed to help modernize older Laravel projects (pre-5.8) where INT was the default ID type, enabling compatibility with newer packages that use BIGINT.

**Version 4.0** requires Laravel 12+ and PHP 8.4+, using native Laravel schema methods.

## Package Architecture

### Core Components

- **ServiceProvider** (`src/ServiceProvider.php`): Registers the Artisan command and publishes the migration stub
- **Transformer** (`src/Transformer.php`): Contains the core transformation logic
- **TransformCommand** (`src/Console/TransformCommand.php`): Artisan command entry point

### Transformation Process

The Transformer class performs a 4-step process (see `Transformer::transform()`):

1. **Introspection**: `extractSchemaInfos()` scans all tables using Laravel's native schema methods:
   - `Schema::getTables()` - Get all database tables
   - `Schema::getColumns($table)` - Get column information including type, nullable, default, auto_increment
   - `Schema::getIndexes($table)` - Identify primary key columns
   - `Schema::getForeignKeys($table)` - Get foreign key constraints
   - Identifies integer columns (int, tinyint, smallint, mediumint, bigint) that are primary or foreign keys

2. **Validation**: Checks referential integrity before making changes via `hasConstraintAnomaly()`. If any foreign key constraint would be violated, the process stops and reports the anomaly.

3. **Drop constraints**: Temporarily drops all foreign key constraints to allow column type changes

4. **Transform columns**: Changes INT to BIGINT for all identified key columns, preserving:
   - Nullable/NOT NULL status
   - Default values
   - Auto-increment settings

5. **Restore constraints**: Re-creates all foreign key constraints with their original:
   - ON DELETE actions
   - ON UPDATE actions

### Important Technical Details

- **v3.0+**: Uses Laravel 11+ native schema methods (`getTables()`, `getColumns()`, `getIndexes()`, `getForeignKeys()`)
- **Pre-v3.0**: Used Doctrine DBAL (no longer supported in v3.0)
- Column type detection via `isIntegerType()` method supports all MySQL integer types (int, tinyint, smallint, mediumint, bigint)
- Column type name retrieved from `$column['type_name']` or `$column['type']` depending on database driver
- Handles all integer columns that are keys (primary or foreign)
- Foreign keys are assumed to be single-column (accesses `[0]` index)
- Migration stub includes safety check: won't run if package is uninstalled

## Usage Commands

### Run transformation directly:
```bash
php artisan pk-int-to-bigint:transform
```

### Publish migration for deployment workflow:
```bash
php artisan vendor:publish --tag="pk-int-to-bigint-migration"
php artisan migrate
```

## Development Requirements

**Version 4.0+:**
- PHP ^8.4
- Laravel ^12.0
- No Doctrine DBAL dependency (uses native Laravel schema methods)

**Version 3.x:**
- PHP ^8.2 || ^8.4
- Laravel ^11.0 || ^12.0

**Version 2.x (legacy):**
- PHP ^8.1
- Laravel ^8.0 || ^9.0 || ^10.0
- Doctrine DBAL ^3.5 || ^3.6

## Testing Considerations

When making changes:
- Test with various foreign key configurations (CASCADE, RESTRICT, SET NULL, etc.)
- Verify behavior with composite keys (though current implementation assumes single-column FKs)
- Check handling of nullable columns vs NOT NULL columns
- Test with different integer types (tinyint, smallint, mediumint, int, bigint)
- Test referential integrity validation catches actual constraint violations
- Verify compatibility with MySQL, PostgreSQL, and SQLite databases
- Test that column attributes (auto_increment, default values) are preserved correctly
