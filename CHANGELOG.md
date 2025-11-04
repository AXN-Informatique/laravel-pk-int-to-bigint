Changelog
=========

3.0.0 (unreleased)
------------------

Add Laravel 11 & 12 support
- Support PHP 8.2 and 8.4
- Add illuminate/support ^11.0 || ^12.0
- Add illuminate/database ^11.0 || ^12.0

Breaking changes:
- Drop Laravel 8, 9, 10 support
- Drop PHP 8.1 support"
- Remove doctrine/dbal dependency completely
- Use native Laravel database introspection


2.1.1 (2023-07-03)
------------------

- Prevent running migration if package is uninstalled
- Removed unsigned constraint for keys
- Prevent errors in the presence of enum type columns (close #2)


2.1.0 (2023-04-12)
------------------

- Added support for Laravel 10
- Uppercase README and CHANGELOG fielnames


2.0.0 (2022-12-19)
------------------

- PHP < 8.1 support removed
- doctrine/dbal < 3.5 support


1.3.2 (2022-12-01)
------------------

- Typo in changelog : doctrine/dbal >= 3.5 (instead of >= 3.1)


1.3.1 (2022-11-09)
------------------

- Fixed support for doctrine/dbal >= 3.5
- Dropped Transformer injection from command constructor


1.3.0 (2022-08-05)
------------------

- Added support for Laravel 9


1.2.0 (2022-01-11)
------------------

- Possibility of generating a migration in order to simplify the implementation


1.1.0 (2022-01-10)
------------------

- Uses doctrine/dbal instead of custom driver for getting DB schema information


1.0.0 (2021-10-27)
------------------

- First release.
