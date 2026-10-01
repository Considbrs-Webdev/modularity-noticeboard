# Integration tests

These tests use real WordPress REST dispatch, post/taxonomy/metadata APIs and
MySQL/MariaDB locks. They must only run against a disposable WordPress installation;
bootstrap refuses prefixes that do not start with `nbtest_`.

Create an isolated WordPress install using a dedicated test database or a unique
`nbtest_` table prefix. Configure HTTPS (`$_SERVER['HTTPS'] = 'on'` for CLI tests),
`DISABLE_WP_CRON`, and test-only constants:

```php
define('SOKIGO_NOVA_PUBLISH_USERNAME', 'nova-test');
define('SOKIGO_NOVA_PUBLISH_PASSWORD', 'nova-test-password');
```

Never point the suite at a live site. Tests retain fixtures with random source slugs
to permit repeated runs. Drop only the disposable test installation's tables when
finished, or recreate it between runs.

From the plugin directory:

```bash
NOTICEBOARD_TEST_WP_ROOT=/path/to/disposable-wordpress php tests/integration.php
NOTICEBOARD_TEST_WP_ROOT=/path/to/disposable-wordpress php tests/concurrency.php
```

The concurrency suite requires `proc_open` and starts six simultaneous PHP workers.
Their credentials are passed on stdin, not command-line arguments or printed output.
It also exercises concurrent withdrawal/update and a real lock timeout/retry.

To verify ACF storage and readback, install/load ACF Pro in that test installation
at normal plugin bootstrap time and set `NOTICEBOARD_TEST_ACF_PLUGIN` to its main PHP
file. A test-only mu-plugin may require that environment-specified path. The suite
loads the notice field group; do not load the complete noticeboard plugin on this
minimal test install, since its full bootstrap expects the Municipio ecosystem.

```bash
NOTICEBOARD_TEST_WP_ROOT=/path/to/disposable-wordpress \
NOTICEBOARD_TEST_ACF_PLUGIN=/path/to/acf-pro/acf.php \
php tests/integration.php
```

Archival policy verification (WP-CLI and ACF required):

```bash
NOTICEBOARD_TEST_WP_ROOT=/path/to/disposable-wordpress \
NOTICEBOARD_TEST_ACF_PLUGIN=/path/to/acf-pro/acf.php \
wp --path=/path/to/disposable-wordpress eval-file tests/archive.php
```

The standalone bootstrap registers only the post type/taxonomies and integration
components. It does not activate or modify the real site's plugins or settings.

Syntax validation:

```bash
find source/php/Integration tests -name '*.php' -exec php -l {} \;
```

Before release, additionally test the full plugin and paired Piteå cleanup in staging,
including the actual server's Authorization forwarding, TLS proxy configuration,
scheduled publication, archival CLI job and settings navigation.
