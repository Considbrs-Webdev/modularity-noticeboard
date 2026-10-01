# External noticeboard publications

Available from plugin version 1.1.0. Requires PHP 8.2+, WordPress 5.5+, and a
MySQL/MariaDB database supporting connection-owned `GET_LOCK`/`RELEASE_LOCK`.
The existing Modularity/ACF requirements still apply to the complete plugin.

## Administration

Use **Noticeboard → Integrations**, accessible to administrators with
`manage_options`. All mutations require a WordPress nonce. Create/rotate tokens
over HTTPS. The generic endpoint does not grant a WordPress user account or
permission to access other WordPress REST endpoints.

Create a token with a stable source slug (for example `building-permits`), label,
scopes (`create`, `update`, `withdraw`), and permitted notice type/group IDs.
Sources are lowercase ASCII slugs, at most 64 characters; `nova` is reserved.
Tokens with the same source share notice ownership, which is useful during a
planned rotation or for multiple workers of one integration. Assign a different
source to each independent integrator.

Copy the token from the one-time result page. Only its SHA-256 secret hash is stored;
the secret is generated from 32 random bytes. Rotation immediately invalidates the
previous secret and retains the source and policy. Revocation is permanent for that
token record. Create a replacement token if required. A request already authorised
and executing when a token is revoked may finish.

Empty permitted-term selections allow no terms. Integrators cannot create taxonomy
terms through the generic API. Create terms in the WordPress administration first.

## General API

Base URL: `/wp-json/noticeboard/v1` (use the URL displayed in administration when
WordPress uses plain permalinks or a different REST prefix).

Every request requires HTTPS and `Authorization: Bearer <token>`. Forward the
Authorization header through the web server/proxy. For TLS-terminating proxies,
configure WordPress to recognise HTTPS through your trusted proxy configuration.
Never log this header. Use your gateway's request limits if needed.

### Create or replace a notice

`PUT /notices/{external_id}`

The ID is scoped to the token's source. It must be 1–200 UTF-8 bytes without
whitespace, slashes, backslashes, control characters, or markup. URL-encode it once
as a single path segment. IDs are case sensitive because identity keys hash the
original value. Do not recycle an ID for a different publication.

The request must be a JSON object, at most 256 KiB including JSON syntax. Unknown
fields are rejected. PUT replaces all integration-owned fields; omitted optional
fields are cleared. Local edits to those fields are overwritten by the next PUT.

| Field | Required | Value |
| --- | --- | --- |
| `title` | Yes | Nonempty string, at most 500 bytes; plain text |
| `content` | Yes | String, may be empty; WordPress-permitted post HTML |
| `publish_at` | Yes | Positive integral Unix seconds (integer or digit string), through year 9999 |
| `archive_at` | No | Integral Unix seconds after `publish_at`, or `null` |
| `document_url` | No | HTTP(S) URL up to 2048 bytes, or empty string |
| `type_ids` | No | Array of up to 50 permitted integer notice type IDs |
| `group_ids` | No | Array of up to 50 permitted integer group IDs |

The route ID always comes from the URL path, never a query/body override. Neither a
WordPress post ID nor integration source is accepted in the body.

```bash
curl --request PUT 'https://example.se/wp-json/noticeboard/v1/notices/BL-2026-42' \
  --header "Authorization: Bearer $NOTICEBOARD_TOKEN" \
  --header 'Content-Type: application/json' \
  --data '{"title":"Building permit decision","content":"<p>A decision has been made.</p>","publish_at":1790841600,"archive_at":1792656000,"type_ids":[12],"group_ids":[],"document_url":"https://example.se/documents/decision.pdf"}'
```

Use current timestamps and term IDs from your site; the example values are illustrative.

Response: `201` for a new post, `200` for an existing one:

```json
{"success":true,"post_id":123,"created":true,"status":"publish","url":"https://example.se/notice/building-permit-decision/"}
```

`status` may be `publish`, `future`, or `draft`. A draft's URL is a permalink, not a
guarantee that it is publicly readable. Scheduled notices rely on WordPress cron.
Archive metadata has minute resolution, so `archive_at` is rounded down to a minute
in the WordPress timezone. The imported Unix instant is also retained to disambiguate
the repeated hour when daylight saving ends. Already-expired deliveries remain unpublished and their
identity is closed. A delayed scheduling job also refuses to publish after expiry.

Sequential and simultaneous retries of an active identity update the same post.
The `create`/`update` scope is checked under the identity lock. A failed write returns
an error and keeps its draft for recovery; retry the complete PUT to finish it.
Updates temporarily stage the notice as a draft while the replacement is written.
Third-party hooks and abrupt process termination can leave an incomplete draft;
inspect drafts after an interrupted request. Database writes are not a distributed
transaction with plugins' side effects.

An identity that was withdrawn, expired, locally unpublished, trashed, or permanently
deleted cannot be republished by a PUT: expect `409` and use a new ID for a genuinely
new publication. Locally changing imported content while API jobs run is not supported.

### Withdraw a notice

`DELETE /notices/{external_id}` with a token having `withdraw` scope.

```bash
curl --request DELETE 'https://example.se/wp-json/noticeboard/v1/notices/BL-2026-42' \
  --header "Authorization: Bearer $NOTICEBOARD_TOKEN"
```

Returns `200`:

```json
{"success":true,"post_id":123,"status":"withdrawn"}
```

Withdrawal sets the post to draft and cancels scheduled publication; it does not
permanently delete it. Repeated DELETE succeeds. DELETE for an unknown ID records
a withdrawal tombstone and returns `post_id: null`, so an out-of-order PUT cannot
recreate it. Withdrawal never affects another source's notice.

### Errors and retries

Errors use WordPress REST shape: `{"code":"...","message":"...","data":{"status":400}}`.

| Status | Meaning | Action |
| --- | --- | --- |
| 400 | Invalid JSON, unsupported field, invalid data/ID/term | Correct the request |
| 401 | Invalid/revoked token or Nova credentials | Correct credentials |
| 403 | HTTPS, scope, or term policy violation | Correct URL or token policy |
| 409 | Closed/removed identity or ambiguous legacy match | Resolve locally or use a new publication ID |
| 413 | Valid JSON request exceeds 256 KiB | Reduce request size |
| 500 | Save/mapping failure | Retry same identity; contact administrator if persistent |
| 503 | Busy identity or unavailable noticeboard | Retry with backoff |

The identity lock waits up to three seconds. Unavailable advisory locks fail closed.
Use bounded exponential backoff for 500/503 or network timeouts. After an uncertain
PUT result, resend the same ID and complete body; do not invent a new ID just to retry.

## Sokigo Nova

The optional shared adapter preserves:

- `POST /wp-json/nova/v1/publish` with HTTP Basic authentication over HTTPS.
- `SOKIGO_NOVA_PUBLISH_USERNAME` and `SOKIGO_NOVA_PUBLISH_PASSWORD` configuration
  constants, with the original trimming behaviour.
- Successful response `{"success":true,"post_id":123}` with status `200`.
- Nova ID plus type as identity, publication scheduling and archive date/time.
- Existing content/details rendering, with sanitisation, and default type mappings:
  `1 → Kungörelser + Bygglov`, `2 → Beslut + Bygglov`, `3 → Bygglov`.

Required payload fields: `type`, `id`, `title`, `content`, `publishDate`.
Supported optional fields: `publishEndDate`, `decisionDate`, `responseDate`, `estate`,
`decision`, `decisionNumber`, `publicNotification`. Type is integer/string 1, 2, or 3;
dates are integral Unix seconds. IDs and decision numbers may also be integers.
Empty optional date strings are ignored. The end date must follow publication.
Unknown vendor fields are ignored, but raw payloads are not newly retained.

The shared adapter owns title/content, publication dates, archive metadata and notice
types; it preserves locally attached documents and groups. Existing
`_pitea_nova_publication_id/type` metadata is recognised on the first delivery and
retained alongside neutral provenance for rollback. Multiple matching legacy posts
return `409`, including matches in trash. Archived legacy drafts are not resurrected.
Old stored `_pitea_nova_publication_payload` values are left untouched for an explicit
retention decision; they are never copied to new metadata.

Shared registration runs after legacy registration and does not override an existing
Nova route. While the old Piteå plugin owns it, its original behaviour continues;
the shared enable switch only controls this plugin's adapter. Disabling the shared
switch does not disable another plugin's endpoint.

### Client customisation interface

`ModularityNoticeboard\Integration\NovaPublicationEndpoint` exposes:

- `isConfigured()`: credential constants have nonempty values.
- `isEnabled()`: shared adapter switch (enabled by default when first installed).
- `getEndpointUrl()`.
- `getStatus()`: `configured`, `enabled`, `active`, `owner` (`noticeboard`,
  `external`, or `none`), and `endpoint_url`. No secrets.

`ModularityNoticeboard\Integration\Admin::url()` links to shared settings.
Consuming plugins should guard `class_exists` and `method_exists` before calling
these interfaces. An `external` owner may still be active under another plugin;
`active` deliberately means the shared adapter is active.

Filters:

```php
add_filter('Modularity/Noticeboard/Nova/TypeNames', function ($names, $type, $payload) {
    return $type === 2 ? ['Beslut', 'Bygglov'] : $names;
}, 10, 3);

add_filter('Modularity/Noticeboard/Nova/Content', function ($html, $payload) {
    return $html; // Final output is sanitised by the shared writer.
}, 10, 2);
```

## Rollout, storage and rollback

1. Back up the database and configuration. Validate credentials, representative
   Nova types, timezones, existing publications and actual archival jobs on staging.
2. Deploy noticeboard 1.1.0 first. The old Piteå route retains ownership, avoiding two
   competing callbacks. Generic API tokens can be configured independently.
3. Deploy the paired Piteå cleanup that removes its Nova route registration/class and
   reads shared status from its existing panel. The shared adapter takes over on the
   next request without an endpoint URL or credential change.
4. Verify route owner `noticeboard`, retry an existing Nova publication and confirm
   its post ID, content, taxonomy, publication date and ACF archive date/time.
5. Configure WordPress scheduling and the existing `wp noticeboard archive` command.
   Run archival at the interval your publication policy needs, not just daily when
   minute-level deadlines matter. The CLI and API share locks for imported notices.

Schema upgrades run on `init` and create two per-site tables with your WordPress
prefix: `noticeboard_identities` and `noticeboard_tokens`. DB user needs table/schema
permissions. Identities and withdrawal records persist even when archival deletes a
post, preventing retry resurrection. Do not delete those tables as routine cleanup.
No full incoming payload or plaintext token is added to logs/storage by this plugin.

For rollback, restore the legacy Piteå route owner before downgrading the shared
plugin; retain legacy metadata. The old implementation does not enforce shared
withdrawal/expiry tombstones or generic token policies, so pause incoming deliveries
and review affected notices before rolling back. Generic integrations must remain
paused if the shared API is unavailable. Do not deploy Piteå removal alone.

## Verification

See [the isolated test instructions](../tests/README.md). Automated checks cover
validation, authentication/scopes, source isolation, token rotation/revocation,
retry/withdraw semantics, expiry, Nova migration/mappings, settings output, injected
metadata failure, and actual concurrent PHP processes. Staging verification of the
paired Piteå deployment and the production cron/proxy setup remains a release step.
