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
planned rotation or for multiple workers of one integration. Ownership is the
boundary for existing notices: any same-source token with the matching scope can
update or withdraw them, whatever terms they carry. Permitted terms limit only which
terms a token may assign. Assign a different source to each independent integrator.

Copy the token from the one-time result page. Only its SHA-256 secret hash is stored;
the secret is generated from 32 random bytes. Rotation immediately invalidates the
previous secret and retains the source and policy. Revocation is permanent for that
token record. Create a replacement token if required. A request already authorised
and executing when a token is revoked may finish.

Revoked tokens can be permanently deleted from administration. Active tokens must
be revoked first. Deleting a token removes only its credential record; published
notices and external identities remain intact.

Every token must permit at least one notice type, and every published notice must
carry at least one permitted type, so the type selection limits what a token can
publish. An empty group selection allows no groups. Integrators cannot create
taxonomy terms through the generic API. Create terms in the WordPress
administration first. Tokens created before 1.2.1 without a permitted notice type
can no longer publish; revoke them and create replacements.

## General API

Base URL: `/wp-json/noticeboard/v1` (use the URL displayed in administration when
WordPress uses plain permalinks or a different REST prefix).

Every request requires HTTPS and `Authorization: Bearer <token>`. Forward the
Authorization header through the web server/proxy. For TLS-terminating proxies,
configure WordPress to recognise HTTPS through your trusted proxy configuration.
Never log this header. Use your gateway's request limits if needed.

### Discover permitted notice types and groups

`GET /terms`

Use the same bearer token as for publication. Returns only existing terms allowed
by that token, including terms that have no notices yet. No extra read scope is
required. Empty selections return empty arrays; deleted terms are omitted.
The response is private and must not be cached.

```sh
curl --request GET 'https://example.se/wp-json/noticeboard/v1/terms' \
  --header 'Authorization: Bearer nb_TOKEN_ID.SECRET'
```

```json
{
  "notice_types": [{"id": 12, "name": "Bygglov", "slug": "bygglov"}],
  "groups": [{"id": 18, "name": "Samhällsbyggnadsnämnden", "slug": "samhallsbyggnadsnamnden"}]
}
```

Use these IDs in `type_ids` and `group_ids` when publishing. This endpoint does not
create terms or change permissions. Refresh the list when configuring an
integration or when a previously accepted term is rejected.

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
| `type_ids` | Yes | Array of 1–50 permitted integer notice type IDs |
| `group_ids` | No | Array of up to 50 permitted integer group IDs |

The route ID always comes from the URL path, never a query/body override. With
plain permalinks, URL-encode the ID once inside the `rest_route` query value. Neither a
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
identity is closed for the generic API. Scheduled publication uses WordPress's
native handler, including its minute threshold for near-future dates. No global
publication callback is removed or replaced. Expiration of scheduled/public notices
is handled by the configured archival job; run it at the interval your policy needs.

Sequential and simultaneous retries of an active identity update the same post.
The `create`/`update` scope is checked under the identity lock. A new notice is staged as a draft until every field is written; if
creation fails, the draft is kept for recovery. Updates keep the notice's current
status, so a public notice stays online and is not republished. A failed update
returns an error and may leave the notice partly updated but still public; retry the
complete PUT to finish it. Third-party hooks and abrupt process termination can
leave incomplete notices; inspect them after an interrupted request. Database writes are not a distributed
transaction with plugins' side effects.

A generic API identity that was withdrawn, expired, locally unpublished, trashed, or permanently
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
| 409 | Closed or removed identity | Resolve locally or use a new publication ID |
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

The adapter owns title/content, publication dates, archive metadata and notice
types; it preserves locally attached documents and groups. Deliveries are tracked
by a durable external identity so retries update the same notice. Draft, pending
and private notices can be updated and republished. Nova stores archive dates and
leaves archival to the existing job; the generic API's closed-ID and
already-expired-delivery rules do not change Nova's publication behaviour.

Registration runs after other plugins and does not override an existing Nova
route. When another plugin owns the route, its behaviour continues; the enable
switch only controls this plugin's adapter. Disabling it does not disable another
plugin's endpoint.

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
   Nova types, timezones, publications and actual archival jobs on staging.
2. Deploy the noticeboard plugin and configure the integrations you need. Generic
   API tokens can be configured independently of the Nova adapter.
3. Check the Nova route owner. If another plugin registers the same endpoint,
   coordinate its removal or disablement before enabling this adapter as the owner.
4. Verify route owner `noticeboard` and test repeated deliveries. Confirm post IDs,
   content, taxonomy, publication dates and ACF archive dates/times.
5. Configure WordPress scheduling and the existing `wp noticeboard archive` command.
   Run archival at the interval your publication policy needs, not just daily when
   minute-level deadlines matter. The CLI and API share locks for imported notices.

Schema upgrades run on `init` and create two per-site tables with your WordPress
prefix: `noticeboard_identities` and `noticeboard_tokens`. DB user needs table/schema
permissions. Identities and withdrawal records persist even when archival deletes a
post, preventing retry resurrection. Do not delete those tables as routine cleanup.
No full incoming payload or plaintext token is added to logs/storage by this plugin.

For rollback, pause incoming deliveries and back up integration tables before
changing plugin versions or endpoint ownership. Review affected notices and the
capabilities of the version being restored; older implementations may not enforce
withdrawal records or token policies. Keep generic integrations paused until a
compatible API is available. Do not remove integration tables during rollback.

## Verification

See [the isolated test instructions](../tests/README.md). Automated checks cover
validation, authentication/scopes, source isolation, token rotation/revocation,
retry/withdraw semantics, expiry, Nova mappings, settings output, injected
metadata failure, and actual concurrent PHP processes. Staging verification of the
full plugin, endpoint ownership and production cron/proxy setup remains a release step.
