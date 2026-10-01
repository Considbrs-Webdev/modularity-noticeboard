# Changelog

All notable changes to this project are documented here in English.

The format follows Keep a Changelog and Semantic Versioning. These first releases
were published retroactively; the dates below are their publication dates.

## [1.1.0] - 2026-10-02

### Added

- Shared Sokigo Nova integration for receiving building permit notices and decisions, preserving the existing publication route and credentials.
- HTTPS publication API with revocable bearer tokens, source ownership and separate create, update and withdrawal permissions.
- Token-scoped discovery of permitted notice types and groups through `GET /noticeboard/v1/terms`.
- Integration administration with token creation, rotation and revocation, Nova configuration and links to API instructions.
- English API documentation and Swedish translations for integration and noticeboard settings.

### Changed

- Imported publications use a shared writer with durable external identities and protection against duplicate concurrent deliveries.
- Integration controls use WordPress-style panels and display token creation dates using the site's timezone and date/time formats.

### Compatibility and installation

- Requires PHP 8.2+, WordPress 5.5+, Modularity and ACF PRO; Municipio is recommended.
- WordPress's native scheduled publication behavior is preserved. The existing noticeboard archival job must remain configured.
- The Nova adapter recognises existing Piteå publication IDs. If another plugin owns the Nova endpoint, it retains ownership until that implementation is removed. The paired Piteå cleanup has not been included in this release; see `docs/integrations.md` before migrating.
- The attached WordPress ZIP includes compiled assets and production Composer dependencies. GitHub's automatic source archives do not include these generated files.

## [1.0.0] - 2026-10-02

### Added

- Digital noticeboard with notices, notice types and responsible groups.
- Modularity display module, notice detail views, archive navigation and breadcrumb support.
- Configurable notice URL slug, publication metadata and document links.
- WP-CLI archival command with archive dates and times, and configurable withdrawal or deletion.
- Swedish translations for noticeboard administration.

### Release baseline

- Retroactive tag of commit `615bc14507791af9937fe72c34fe9ade972a8fb5`, the last 1.0.0 snapshot before the shared Nova adapter and general publication API.
- This historical release provides source archives only. Built frontend assets and Composer dependencies must be installed separately.
