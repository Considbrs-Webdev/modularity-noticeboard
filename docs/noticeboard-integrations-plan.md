# Noticeboard integrations implementation plan

Status: implemented on the feature branch. See [the API and rollout contract](integrations.md)
and [verification instructions](../tests/README.md). Automated integration and
concurrency checks pass locally; paired Piteå cleanup and full staging rollout are
separate release steps.

Local verification: PHP 8.4.17 / WordPress 7.0.2; 89 integration assertions passed
both with ACF Pro and without ACF, six-worker concurrency and lock timeout/retry
passed, and WP-CLI dry-run/unpublish/delete archival checks passed. Composer
validation and PHP syntax checks passed. No dependency versions were changed.

## Goal

Move the reusable Sokigo Nova publication integration from pitea-customisation
into modularity-noticeboard and provide a general API for external integrators.
Clients must be able to use the integration without installing Piteå-specific code.

## 1. Shared notice-writing service

- Extract notice persistence from NovaPublicationEndpoint into a service independent
  of transport, credentials, and vendor payloads. Use the plugin's post type and
  taxonomy constants and centralise ACF field references.
- Define validated notice data: external ID, title, content, publication timestamp,
  optional archive timestamp, document URL, and configured notice types/groups.
- Scope identity to integration/source plus external ID. Nova also needs its type
  discriminator because the existing identity is ID plus type.
- Ensure sequential retries and concurrent deliveries do not create duplicate
  notices; choose and document an atomic identity/locking mechanism before coding.
- Define ownership of imported fields, handling of local edits, and behaviour for
  archived, trashed, withdrawn, and already-expired publications. Expired notices
  must not briefly become public. Retries must not undo an explicit withdrawal.
- Reuse noticeboard scheduling and archival settings. Document the required
  WordPress scheduling and existing `wp noticeboard archive` job setup.
- Return actionable errors for failed post, taxonomy, or metadata writes and define
  recovery behaviour for partial writes.

## 2. Optional Nova adapter

- Move payload validation, Basic authentication, and Nova content mapping into a
  dedicated integration that calls the shared writer.
- Preserve POST /wp-json/nova/v1/publish, response shape, supported types, timestamps,
  and SOKIGO_NOVA_PUBLISH_USERNAME / SOKIGO_NOVA_PUBLISH_PASSWORD compatibility.
- Tighten scalar, integer/type, timestamp, required-field, and date-order validation
  without silently changing the documented Nova contract.
- Recognise existing _pitea_nova_publication_id/type metadata before creating posts.
  Define an idempotent migration to neutral metadata; preserve IDs and avoid copying
  stored raw payloads unnecessarily. Specify payload retention and sanitisation.
- Keep current type mappings as compatible defaults, with documented filters for
  client-specific terms and formatting. Do not register an accepting endpoint unless
  configured; report configuration status accurately.
- Expose a stable configuration/status interface for consuming plugins, including
  endpoint URL and enabled/configured state, without revealing credentials.

## 3. General token API

- Publish a versioned REST contract, proposed namespace noticeboard/v1. Finalise
  routes before implementation: PUT /notices/{external_id} for create/update and
  DELETE /notices/{external_id} for withdrawal from public display.
- Authenticate bearer tokens over HTTPS. Generate cryptographically random tokens,
  reveal them once, store hashes, and support revocation and rotation per integration.
- Resolve integration identity from the token, never from untrusted request data.
  Authorise only notices owned by that integration; do not grant general WordPress
  editing rights. Define create/update/withdraw scopes and allowed taxonomy terms.
- Provide explicit field validation, payload limits, consistent error responses,
  and response IDs/URLs. Document PUT replacement semantics, optional-field clearing,
  percent-encoded external IDs, and repeat-withdraw behaviour.
- Limit the first version to publication management; defer attachment uploads,
  outbound webhooks, and general WordPress access.

## 4. Administration and documentation

- Add shared integration settings to the noticeboard: Nova status/setup information
  and authorised-admin token creation, revocation, rotation, scopes, and sources.
- Protect admin mutations with capability checks and nonces; never display stored
  secrets or write credentials/tokens to logs.
- Document setup, example requests, schema, errors, retry/withdraw semantics,
  publication/archive scheduling, and Nova migration/deployment order.
- Allow Piteå's existing panel to consume the status interface and link to shared
  settings; keep Piteå-specific policy in its customisation plugin through filters.

## 5. Verification and rollout

- Verify authentication failures, revoked tokens, cross-source isolation, malformed
  payloads, sanitisation, taxonomy failures, scheduling/timezones, and expiry.
- Verify retries and concurrent upserts, withdrawal retries, archived/trashed notices,
  and migration updates against existing Piteå metadata without duplication.
- Exercise the existing Nova request/response contract and ACF archive fields on a
  WordPress staging environment; verify actual archival job execution.
- Coordinate with pitea-customisation branch codex/fix-extract-nova-integration.
  Transfer route ownership without a deployment window where both plugins register
  the route or neither provides it. Choose either an explicit activation switch or
  a compatibility guard, and document rollback and old/new version combinations.
- Release the shared implementation before deploying dependent Piteå cleanup.

## Completion criteria

Nova works independently of pitea-customisation, existing publications continue to
update, a second client can configure the adapter, and a generic integrator can
create/update/withdraw only its own notices with a revocable token. Piteå's status
panel remains useful, and the paired cleanup can ship without interrupting imports.
