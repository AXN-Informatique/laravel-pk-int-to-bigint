Laravel convert primary keys INT to BIGINT
==========================================

This package convert DB primary keys and related foreign keys type from INT to BIGINT in a Laravel project.

This is especially useful for old projects that need to be updated.

Indeed, since Laravel 5.8 the ID columns are by default of type BIGINT. If you install new packages that use this new "standard" you will have trouble creating the foreign keys.

As a result, this package will be of great help to you to modernize an old application.

It proceeds in 4 steps:

1. introspection of the database and verification of the integrity of foreign keys (if an integrity is not respected it stops and indicates it to you)
2. droping all foreign key constraints on each table
3. converting INT to BIGINT on primary and foreign key columns on each table
4. restoring all foreign key constraints on each table

Instalation
-----------

Install the package with Composer:

```sh
composer require axn/laravel-pk-int-to-bigint
```

Usage
-----

First create a dump of your database in case there is a problem.

### Manualy

If you want to run the command directly:

```sh
php artisan pk-int-to-bigint:transform
```

### With migration

Pusblish the migration:

```sh
php artisan vendor:publish --tag="pk-int-to-bigint-migration"

php artisan migrate
```

So you can incorporate it into your deployment workflow.
