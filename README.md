# Activity Log

A WordPress plugin that logs user and system actions: post edits, login attempts, plugin activations, option changes, REST API calls, WP-CLI commands, and more. Each log entry records who made the change, when, from which IP, and through which request source (WP Admin, REST API, WP-CLI, WP-Cron, XML-RPC, or WP Abilities).

Events are stored in a dedicated database table, separate from core WordPress tables.

- [activitylog.io](https://activitylog.io/?utm_source=github&utm_medium=readme)
- [WordPress.org plugin page](https://wordpress.org/plugins/aryo-activity-log/)

## Requirements

- WordPress 6.2 or later
- PHP 7.4 or later

## Install

Install from the WordPress.org plugin directory, or search "Activity Log" under Plugins > Add New in the WordPress dashboard.

This repository is the development source. If you want to run a released version, use the WordPress.org zip.

## Develop from source

Clone the repository into your `wp-content/plugins/` directory. The folder name should be `aryo-activity-log` to match the plugin slug.

```bash
cd /path/to/wp-content/plugins
git clone https://github.com/elementor/activity-log.git aryo-activity-log
cd aryo-activity-log
```

Install PHP dev dependencies (PHPUnit, PHPCS):

```bash
composer install
```

Install JS dependencies and build the admin UI:

```bash
npm ci
npm run build
```

`npm start` runs a watch build for development. `npm run package` creates a distributable plugin directory, and `npm run package:zip` wraps it in a zip.

## Tests and lint

See [tests/README.md](tests/README.md) for the full PHPUnit setup (WordPress test harness, database, troubleshooting).

```bash
composer test     # PHPUnit
composer lint     # PHPCS (WordPress coding standards)
```

CI runs on every push and pull request:

- PHPUnit across PHP 7.4, 8.0, 8.1, 8.2, 8.3 and recent WordPress versions (MySQL 5.7)
- PHPCS on PHP 7.4
- JS build verification on pull requests

## Changelog

Recent release notes are in `readme.txt`. The complete version history is in [changelog.txt](changelog.txt).

## Contributing

Open issues and pull requests on the [GitHub repo](https://github.com/elementor/activity-log). Release notes go in `readme.txt` and `changelog.txt`.

Report security vulnerabilities through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/aryo-activity-log).

## License

GPLv2 or later.
