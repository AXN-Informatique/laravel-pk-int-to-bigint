Laravel convert primary keys INT to BIGINT
==========================================

Convert DB primary keys and related foreign keys type from INT to BIGINT in a Laravel project.

This package is particularly useful for old projects that need to be updated.

Indeed, since Laravel 5.8 the ID columns are by default of type BIGINT. If you install new packages that use this new "standard" you will have trouble creating the foreign keys.

As a result, this package will transform all the primary keys of type INT into BIGINT.
It will also update the relationships on these keys.

It proceeds in 4 steps:

- introspection of the database and verification of the integrity of foreign keys (if an integrity is not respected it stops and indicates it to you)
- droping all foreign key constraints on each table
- converting INT to BIGINT on primary and foreign key columns on each table
- restoring all foreign key constraints on each table

Instalation
-----------

Install the package with Composer:

```sh
composer require axn/laravel-pk-int-to-bigint
```

Usage
-----

Create a dump of your data in case there is a problem.

Run the command:

```sh
php artisan pk-int-to-bigint:transform
```
