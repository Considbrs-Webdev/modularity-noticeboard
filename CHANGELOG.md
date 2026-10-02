# Changelog

All notable changes to this project are documented here in English.

The format follows Keep a Changelog and Semantic Versioning. These first releases
were published retroactively; the dates below are their publication dates.

## [1.2.1] - 2026-10-02

### Security

- Publication tokens must permit at least one notice type, and every notice published through the general API must carry at least one permitted type. Previously a token could publish untyped notices, which appear on unfiltered noticeboards regardless of the token's type restrictions.

### Fixed

- Updating a published notice through the general API no longer takes it offline while the update is written, and no longer fires publication hooks again. New notices are still staged as drafts until complete.
- External IDs in plain-permalink requests (`?rest_route=`) are no longer URL-decoded twice.
- The integration schema marker is autoloaded, avoiding an extra database query on every request.

### Changed

- `type_ids` is required on `PUT /noticeboard/v1/notices/{external_id}` and must contain at least one permitted notice type ID. Requests without one return `400`.
- Existing tokens without a permitted notice type can no longer publish. Revoke them and create replacements with at least one type.
- Integration documentation clarifies that source ownership, not term policy, governs which tokens may update or withdraw existing notices.

### Removed

- Nova deliveries no longer adopt notices created by an earlier standalone Nova plugin through its legacy metadata.

## [1.2.0] - 2026-10-02

### Added

- Administrators can permanently delete revoked publication tokens from the integrations screen, removing their credential records from the database and token list.
- A confirmation prompt explains that deleting a token leaves published notices intact.
- Swedish translations for token deletion controls and messages.

### Security

- Active tokens must be revoked before they can be deleted. The database deletion enforces this condition, including for manually submitted requests.
- Token deletion retains published notices and durable external identities, preserving duplicate-delivery and ownership protection.

### Changed

- Release documentation focuses on the shared noticeboard integration functionality.

## [1.1.0] - 2026-10-02

### Added

- Shared Sokigo Nova integration for receiving building permit notices and decisions.
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
