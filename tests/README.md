# Activity Log — Testing

## Prerequisites

- **PHP** >= 7.4 (PHPUnit 9 requires >= 7.3; CI tests up to 8.3)
- **Composer** >= 2.x
- **MySQL** or **MariaDB** running locally
- **Subversion** (`svn`) — used by `install-wp-tests.sh` to fetch WP test includes
- **curl** or **wget**

## One-time setup

Install the WordPress PHPUnit test suite and create the test database:

```bash
bash bin/install-wp-tests.sh wordpress_test root 'YOUR_PASSWORD' 127.0.0.1 latest
```

This downloads WordPress core and the test harness into `$TMPDIR` (defaults to `/tmp`).

If you need a custom location, export before running:

```bash
export WP_TESTS_DIR=/path/to/wordpress-tests-lib
export WP_CORE_DIR=/path/to/wordpress
bash bin/install-wp-tests.sh wordpress_test root 'YOUR_PASSWORD' 127.0.0.1 latest
```

Then install PHP dependencies:

```bash
composer install
```

## Running tests

```bash
composer test
# or directly:
./vendor/bin/phpunit
```

## Running lint (PHPCS)

```bash
composer lint
```

Security rules are currently reported as warnings. To see them:

```bash
./vendor/bin/phpcs --standard=./ruleset.xml --extensions=php .
```

## Troubleshooting: macOS PHP (`libffi` dyld error)

If `php -v` fails with `Library not loaded: libffi.8.dylib`, your Homebrew PHP
installation is broken. Fix it:

```bash
brew reinstall libffi
brew reinstall php composer
hash -r
php -v && composer -V
```

If it still fails, install a specific PHP version:

```bash
brew install shivammathur/php/php@8.2
brew link php@8.2 --force
```

## CI

Tests run automatically on push/PR to `master` via GitHub Actions:

- **PHPUnit**: PHP 7.4–8.3 × WP 6.0 + latest (MySQL 5.7 service)
- **PHPCS**: PHP 7.4, `composer lint`

See `.github/workflows/phpunit.yml` and `.github/workflows/php-coding-standards.yml`.
