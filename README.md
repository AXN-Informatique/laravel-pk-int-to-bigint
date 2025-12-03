Laravel convert primary keys INT to BIGINT
==========================================

This package convert DB primary keys and related foreign keys type from INT to BIGINT in a Laravel project.

This is especially useful for old projects that need to be updated.

Indeed, since Laravel 5.8 the ID columns are by default of type BIGINT. If you install new packages that use this new "standard" you will have trouble creating the foreign keys.

As a result, this package will be of great help to you to modernize an old application.

## Version Compatibility

- **Version 4.x**: Laravel 12+ / PHP 8.4+ (uses native Laravel schema methods)
- **Version 3.x**: Laravel 11 / PHP 8.2+ (uses native Laravel schema methods) ⚠️ **Not compatible with Laravel 12**
- **Version 2.x**: Laravel 8-10 / PHP 8.1+ (uses Doctrine DBAL)

## How it works

It proceeds in 4 steps:

1. **Introspection**: Scans the database and verifies the integrity of foreign keys (if an integrity is not respected it stops and indicates it to you)
2. **Drop constraints**: Temporarily drops all foreign key constraints on each table
3. **Convert columns**: Converts INT to BIGINT on primary and foreign key columns on each table (preserving nullable, default values, and auto-increment settings)
4. **Restore constraints**: Restores all foreign key constraints on each table with their original ON DELETE/ON UPDATE actions

## Installation

Install the package with Composer:

```sh
composer require axn/laravel-pk-int-to-bigint
```

For Laravel 11 (version 3.x):

```sh
composer require axn/laravel-pk-int-to-bigint:^3.0
```

For Laravel 8-10 (version 2.x):

```sh
composer require axn/laravel-pk-int-to-bigint:^2.0
```

## Usage

**⚠️ Important**: First create a dump of your database in case there is a problem.

### Option 1: Run manually

If you want to run the command directly:

```sh
php artisan pk-int-to-bigint:transform
```

You can specify a specific database/schema:

```sh
php artisan pk-int-to-bigint:transform --database=my_database
```

### Option 2: With migration

Publish the migration:

```sh
php artisan vendor:publish --tag="pk-int-to-bigint-migration"
```

So you can incorporate it into your deployment workflow with:

```sh
php artisan migrate
```

## What gets converted

The package will convert:
- All primary key columns of type INT, TINYINT, SMALLINT, MEDIUMINT to BIGINT
- All foreign key columns of type INT, TINYINT, SMALLINT, MEDIUMINT to BIGINT
- Preserves all column attributes (nullable, default values, auto-increment)
- Preserves all foreign key constraints (ON DELETE, ON UPDATE actions)

