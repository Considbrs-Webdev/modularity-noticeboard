<?php
require __DIR__ . '/bootstrap.php';

use ModularityNoticeboard\Integration\Admin;
use ModularityNoticeboard\Integration\NoticeLifecycle;
use ModularityNoticeboard\Integration\NoticeWriter;
use ModularityNoticeboard\Integration\NovaPublicationEndpoint;
use ModularityNoticeboard\Integration\Storage;
use ModularityNoticeboard\Integration\Tokens;
use ModularityNoticeboard\Integration\Validation;

$checks = 0;
function check($condition, string $label): void
{
    global $checks;
    ++$checks;
    if (!$condition) {
        throw new RuntimeException($label);
    }
}
function request(string $method, string $id, string $token, ?array $body = null): WP_REST_Response
{
    $r = new WP_REST_Request($method, '/noticeboard/v1/notices/' . rawurlencode($id));
    $r->set_header('authorization', 'Bearer ' . $token);
    if ($body !== null) {
        $r->set_header('content-type', 'application/json');
        $r->set_body(wp_json_encode($body));
    }
    return rest_get_server()->dispatch($r);
}
function permittedTerms(string $token, array $query = []): WP_REST_Response
{
    $r = new WP_REST_Request('GET', '/noticeboard/v1/terms');
    $r->set_header('authorization', 'Bearer ' . $token);
    $r->set_query_params($query);
    return rest_get_server()->dispatch($r);
}
function nova(array $body): WP_REST_Response
{
    $r = new WP_REST_Request('POST', '/nova/v1/publish');
    $r->set_header('authorization', 'Basic ' . base64_encode(SOKIGO_NOVA_PUBLISH_USERNAME . ':' . SOKIGO_NOVA_PUBLISH_PASSWORD));
    $r->set_header('content-type', 'application/json');
    $r->set_body(wp_json_encode($body));
    return rest_get_server()->dispatch($r);
}
try {
    $source = 'test-' . bin2hex(random_bytes(4));
    $type = wp_insert_term($source, 'noticeboard_notice_type');
    $group = wp_insert_term($source, 'notice_group');
    check(!is_wp_error($type) && !is_wp_error($group), 'Create fixture terms');
    $typeId = (int) $type['term_id'];
    $groupId = (int) $group['term_id'];
    $tokens = new Tokens();
    $issued = $tokens->create($source, 'Test integration', ['create', 'update', 'withdraw'], [$typeId], [$groupId]);
    check(!is_wp_error($issued), 'Token issuance');
    $token = $issued['token'];
    $termsResponse = permittedTerms($token);
    check($termsResponse->get_status() === 200 && $termsResponse->get_data() === [
        'notice_types' => [['id' => $typeId, 'name' => $source, 'slug' => $source]],
        'groups' => [['id' => $groupId, 'name' => $source, 'slug' => $source]],
    ], 'Discovery exposes only permitted terms, including unused terms, with integer IDs, names and slugs');
    check($termsResponse->get_headers()['Cache-Control'] === 'private, no-store', 'Discovery response cannot be shared by caches');
    check(permittedTerms($token, ['type_ids' => [$groupId], 'include' => [], 'hide_empty' => true])->get_data() === $termsResponse->get_data(), 'Query parameters cannot override token discovery policy');
    check(permittedTerms('')->get_status() === 401 && permittedTerms('invalid')->get_status() === 401, 'Discovery rejects missing and invalid tokens');
    $_SERVER['HTTPS'] = 'off';
    check(permittedTerms($token)->get_status() === 403, 'Discovery requires HTTPS');
    $_SERVER['HTTPS'] = 'on';
    $emptyToken = $tokens->create($source . '-empty', 'Empty discovery policy', ['create'], [], []);
    check(permittedTerms($emptyToken['token'])->get_data() === ['notice_types' => [], 'groups' => []], 'Empty discovery policy exposes no terms');
    $tokens->revoke($emptyToken['token_id']);
    check(permittedTerms($emptyToken['token'])->get_status() === 401, 'Discovery rejects revoked tokens');
    $deletedTerm = wp_insert_term($source . '-deleted', 'noticeboard_notice_type');
    $deletedToken = $tokens->create($source . '-deleted', 'Deleted term policy', ['create'], [(int) $deletedTerm['term_id']], []);
    wp_delete_term($deletedTerm['term_id'], 'noticeboard_notice_type');
    check(permittedTerms($deletedToken['token'])->get_data() === ['notice_types' => [], 'groups' => []], 'Discovery omits deleted permitted terms');
    $tokens->revoke($deletedToken['token_id']);

    $data = ['title' => 'Test notice', 'content' => '<p>Allowed</p><script>alert(1)</script>',
        'publish_at' => time() - 300, 'archive_at' => time() + 86400,
        'document_url' => 'https://example.invalid/document.pdf', 'type_ids' => [$typeId], 'group_ids' => [$groupId]];
    $r = request('PUT', 'case-1', $token, $data);
    check($r->get_status() === 201, 'Create returns 201: ' . wp_json_encode($r->get_data()));
    $postId = $r->get_data()['post_id'];
    check(get_post_status($postId) === 'publish', 'Past publication becomes public');
    check(strpos(get_post($postId)->post_content, '<script>') === false, 'Content sanitised');
    check(get_post_meta($postId, NoticeWriter::SOURCE_META, true) === $source, 'Source provenance');
    check(get_post_meta($postId, 'pdf_file', true)['url'] === $data['document_url'], 'ACF link storage');
    check(get_post_meta($postId, 'archive_date', true) === wp_date('Ymd', $data['archive_at']), 'ACF date storage');
    check(NoticeLifecycle::archiveTimestamp($postId) === intdiv($data['archive_at'], 60) * 60, 'Archive timezone and minute resolution');
    check(wp_get_object_terms($postId, 'noticeboard_notice_type', ['fields' => 'ids']) === [$typeId], 'Notice type saved');
    $r = request('PUT', 'case-1', $token, $data);
    check($r->get_status() === 200 && $r->get_data()['post_id'] === $postId, 'Retry preserves post ID');
    $r = request('PUT', 'case-1', $token, array_diff_key($data, array_flip(['archive_at', 'document_url', 'type_ids', 'group_ids'])));
    check($r->get_status() === 200 && get_post_meta($postId, 'archive_date', true) === '', 'PUT clears omitted optional fields');
    check(wp_get_object_terms($postId, 'noticeboard_notice_type', ['fields' => 'ids']) === [], 'PUT clears taxonomy');
    check(request('PUT', 'bad-token', 'invalid', $data)->get_status() === 401, 'Invalid token rejected');
    $_SERVER['HTTPS'] = 'off';
    check(request('PUT', 'insecure', $token, $data)->get_status() === 403, 'HTTP rejected');
    $_SERVER['HTTPS'] = 'on';
    foreach ([['publish_at' => 1.5], ['title' => []], ['archive_at' => $data['publish_at']], ['document_url' => 'javascript:alert(1)'], ['type_ids' => ['1']], ['type_ids' => null], ['document_url' => null], ['source' => 'nova']] as $change) {
        check(request('PUT', 'invalid', $token, array_replace($data, $change))->get_status() === 400, 'Malformed field rejected: ' . key($change));
    }
    check(request('PUT', 'invalid', $token, array_replace($data, ['type_ids' => [$typeId + 999999]]))->get_status() === 403, 'Unpermitted term rejected');
    check(request('PUT', 'bad/id', $token, $data)->get_status() === 400, 'Encoded slash rejected');
    check(request('PUT', 'bad id', $token, $data)->get_status() === 400, 'Encoded whitespace rejected');
    $r = new WP_REST_Request('PUT', '/noticeboard/v1/notices/array');
    $r->set_header('authorization', 'Bearer ' . $token);
    $r->set_header('content-type', 'application/json');
    $r->set_body('[]');
    check(rest_get_server()->dispatch($r)->get_status() === 400, 'JSON array rejected');
    $r->set_body(wp_json_encode(array_replace($data, ['content' => str_repeat('x', Validation::MAX_BODY_BYTES + 1)])));
    check(rest_get_server()->dispatch($r)->get_status() === 413, 'Oversized request rejected');
    $other = $tokens->create($source . '-other', 'Other source', ['create', 'update', 'withdraw'], [$typeId], [$groupId]);
    $r = request('PUT', 'case-1', $other['token'], $data);
    check($r->get_status() === 201 && $r->get_data()['post_id'] !== $postId, 'Same ID isolated by source');
    check(request('DELETE', 'case-1', $other['token'])->get_status() === 200 && get_post_status($postId) === 'publish', 'Withdrawal cannot affect another source');
    $createOnly = $tokens->create($source, 'Create only', ['create'], [$typeId], [$groupId]);
    $updateOnly = $tokens->create($source, 'Update only', ['update'], [$typeId], [$groupId]);
    check(request('PUT', 'case-1', $createOnly['token'], $data)->get_status() === 403, 'Create-only token cannot update');
    check(request('PUT', 'not-created', $updateOnly['token'], $data)->get_status() === 403, 'Update-only token cannot create');
    check(request('DELETE', 'case-1', $createOnly['token'])->get_status() === 403, 'Withdrawal scope enforced');
    $failCreate = static function ($value, $objectId, $key) { return $key === 'archive_date' ? false : $value; };
    add_filter('update_post_metadata', $failCreate, 10, 3);
    check(request('PUT', 'create-recovery', $createOnly['token'], $data)->get_status() === 500, 'Create-only partial failure reported');
    remove_filter('update_post_metadata', $failCreate, 10);
    check(request('PUT', 'create-recovery', $createOnly['token'], $data)->get_status() === 200, 'Create-only token can recover its unfinished creation');
    check(request('PUT', 'create-recovery', $createOnly['token'], $data)->get_status() === 403, 'Create-only recovery does not permit subsequent updates');
    $rotated = $tokens->rotate($issued['token_id']);
    check(!is_wp_error($rotated), 'Rotation succeeded');
    check(request('PUT', 'case-1', $token, $data)->get_status() === 401, 'Old token rejected after rotation');
    $token = $rotated['token'];
    check(request('DELETE', 'case-1', $token)->get_status() === 200 && get_post_status($postId) === 'draft', 'Withdraw unpublishes');
    check(request('DELETE', 'case-1', $token)->get_status() === 200, 'Repeated withdrawal succeeds');
    check(request('PUT', 'case-1', $token, $data)->get_status() === 409, 'Retry cannot undo withdrawal');
    check(request('DELETE', 'unknown', $token)->get_status() === 200, 'Unknown withdrawal writes tombstone');
    check(request('PUT', 'unknown', $token, $data)->get_status() === 409, 'Out-of-order create cannot undo tombstone');
    $future = array_replace($data, ['publish_at' => time() + 3600, 'archive_at' => time() + 7200]);
    $r = request('PUT', 'scheduled', $token, $future);
    $scheduledId = $r->get_data()['post_id'];
    check($r->get_status() === 201 && get_post_status($scheduledId) === 'future', 'Future publication scheduled');
    check((bool) wp_next_scheduled('publish_future_post', [$scheduledId]), 'WordPress publication event exists');
    check(request('DELETE', 'scheduled', $token)->get_status() === 200 && !wp_next_scheduled('publish_future_post', [$scheduledId]), 'Withdrawal clears future job');
    $r = request('PUT', 'near-future', $token, array_replace($data, ['publish_at' => time() + 30]));
    check($r->get_status() === 201 && $r->get_data()['status'] === 'publish', 'Near-future dates follow WordPress standard minute threshold');
    request('DELETE', 'near-future', $token);
    foreach ([strtotime('2027-10-31T00:30:00Z'), strtotime('2027-10-31T01:30:00Z')] as $index => $archiveAt) {
        $r = request('PUT', 'dst-' . $index, $token, array_replace($data, ['archive_at' => $archiveAt]));
        check($r->get_status() === 201 && NoticeLifecycle::archiveTimestamp($r->get_data()['post_id']) === $archiveAt, 'Archive instant preserved through repeated DST hour ' . $index);
    }
    $expired = array_replace($data, ['publish_at' => time() - 7200, 'archive_at' => time() - 3600]);
    $r = request('PUT', 'expired', $token, $expired);
    check($r->get_status() === 201 && $r->get_data()['status'] === 'draft', 'Expired delivery never public');
    check(request('PUT', 'expired', $token, $data)->get_status() === 409, 'Expired identity cannot reopen');
    $r = request('PUT', 'archive-before-job', $token, $data);
    $expiredId = $r->get_data()['post_id'];
    update_post_meta($expiredId, 'archive_date', wp_date('Ymd', time() - 3600));
    update_post_meta($expiredId, 'archive_time', wp_date('H:i', time() - 3600));
    check(request('PUT', 'archive-before-job', $token, $data)->get_status() === 409 && get_post_status($expiredId) === 'draft', 'Retry cannot extend already-expired notice');
    $r = request('PUT', 'local-draft', $token, $data);
    wp_update_post(['ID' => $r->get_data()['post_id'], 'post_status' => 'draft']);
    check(request('PUT', 'local-draft', $token, $data)->get_status() === 409, 'Locally unpublished notice remains unpublished');
    $r = request('PUT', 'local-trash', $token, $data);
    wp_trash_post($r->get_data()['post_id']);
    check(request('PUT', 'local-trash', $token, $data)->get_status() === 409, 'Trash not resurrected');
    $r = request('PUT', 'local-delete', $token, $data);
    wp_delete_post($r->get_data()['post_id'], true);
    check(request('PUT', 'local-delete', $token, $data)->get_status() === 409, 'Permanent deletion not recreated');
    // Keep the native publisher and other plugins' callbacks intact.
    check(has_action('publish_future_post', 'check_and_publish_future_post') === 10, 'Native WordPress publisher retained');
    $observed = [];
    $observer = static function ($id) use (&$observed) { $observed[] = $id; };
    add_action('publish_future_post', $observer, 20);
    $r = request('PUT', 'late-cron', $token, $future);
    $lateId = $r->get_data()['post_id'];
    update_post_meta($lateId, 'archive_date', wp_date('Ymd', time() - 3600));
    update_post_meta($lateId, 'archive_time', wp_date('H:i', time() - 3600));
    global $wpdb;
    $wpdb->update($wpdb->posts, ['post_date_gmt' => gmdate('Y-m-d H:i:s', time() - 60)], ['ID' => $lateId]);
    clean_post_cache($lateId);
    do_action('publish_future_post', $lateId);
    check(get_post_status($lateId) === 'publish', 'Imported scheduling remains WordPress standard; archival is handled by the archive job');
    $ordinary = wp_insert_post(['post_type' => 'post', 'post_title' => 'Ordinary scheduled post', 'post_status' => 'future',
        'post_date' => wp_date('Y-m-d H:i:s', time() + 3600), 'post_date_gmt' => gmdate('Y-m-d H:i:s', time() + 3600)]);
    $wpdb->update($wpdb->posts, ['post_date_gmt' => gmdate('Y-m-d H:i:s', time() - 60)], ['ID' => $ordinary]);
    clean_post_cache($ordinary);
    do_action('publish_future_post', $ordinary);
    check(get_post_status($ordinary) === 'publish', 'Ordinary scheduled posts retain native publication');
    check($observed === [$lateId, $ordinary], 'Other plugin callbacks retained for all scheduled posts');
    remove_action('publish_future_post', $observer, 20);
    do_action('publish_future_post', $scheduledId);
    check(get_post_status($scheduledId) === 'draft', 'Native publisher does not resurrect a withdrawn draft');
    // Inject metadata failure and confirm a retry completes the same unpublished draft.
    $fail = static function ($value, $objectId, $key) { return $key === 'archive_date' ? false : $value; };
    add_filter('update_post_metadata', $fail, 10, 3);
    $r = request('PUT', 'failure', $token, $data);
    check($r->get_status() === 500, 'Metadata failure reported');
    $pending = Storage::identity(Storage::key($source, 'failure'));
    check($pending && get_post_status((int) $pending['post_id']) === 'draft', 'Failed write remains unpublished');
    remove_filter('update_post_metadata', $fail, 10);
    $r = request('PUT', 'failure', $token, $data);
    check($r->get_status() === 200 && $r->get_data()['post_id'] === (int) $pending['post_id'], 'Retry recovers same draft after failure');
    // Nova publication contract and durable identity.
    $novaId = 'N-' . $source;
    $payload = ['type' => 2, 'id' => $novaId, 'title' => 'Nova decision', 'content' => '<p>Decision</p>',
        'publishDate' => time() - 300, 'publishEndDate' => time() + 86400, 'decisionDate' => time() - 600, 'decisionNumber' => '42'];
    $r = nova($payload);
    check($r->get_status() === 200 && $r->get_data()['success'] === true, 'Nova creates notice and preserves response contract');
    $novaPostId = $r->get_data()['post_id'];
    check(get_post_meta($novaPostId, NoticeWriter::SOURCE_META, true) === 'nova', 'Nova records neutral source provenance');
    check(get_post_meta($novaPostId, NoticeWriter::ID_META, true) === '2:' . $novaId, 'Nova records neutral external identity');
    wp_set_object_terms($novaPostId, [$groupId], 'notice_group');
    update_post_meta($novaPostId, 'pdf_file', ['url' => 'https://example.invalid/document.pdf', 'title' => '', 'target' => '']);
    check(nova($payload)->get_data()['post_id'] === $novaPostId, 'Nova retry updates same notice');
    check(wp_get_object_terms($novaPostId, 'notice_group', ['fields' => 'ids']) === [$groupId], 'Nova preserves locally assigned groups');
    check(get_post_meta($novaPostId, 'pdf_file', true)['url'] === 'https://example.invalid/document.pdf', 'Nova preserves attached document');
    check(nova(array_replace($payload, ['type' => 1.9]))->get_status() === 400, 'Fractional Nova type rejected');
    check(nova(array_replace($payload, ['decisionDate' => []]))->get_status() === 400, 'Non-scalar Nova date rejected');
    foreach ([1 => ['Bygglov', 'Kungörelser'], 2 => ['Beslut', 'Bygglov'], 3 => ['Bygglov']] as $type => $expected) {
        $r = nova(array_replace($payload, ['type' => $type, 'id' => $novaId . '-' . $type, 'publicNotification' => 'Public notification']));
        check($r->get_status() === 200, 'Nova type ' . $type . ' succeeds');
        $names = wp_get_object_terms($r->get_data()['post_id'], 'noticeboard_notice_type', ['fields' => 'names']);
        sort($names); sort($expected);
        check($names === $expected, 'Nova taxonomy mapping ' . $type);
    }
    $draftResponse = nova(array_replace($payload, ['id' => $novaId . '-draft']));
    check($draftResponse->get_status() === 200, 'Nova creates draft-update fixture');
    $novaDraftId = $draftResponse->get_data()['post_id'];
    wp_update_post(['ID' => $novaDraftId, 'post_status' => 'draft']);
    $draftResponse = nova(array_replace($payload, ['id' => $novaId . '-draft']));
    check($draftResponse->get_status() === 200 && $draftResponse->get_data()['post_id'] === $novaDraftId && get_post_status($novaDraftId) === 'publish', 'Nova republishes its existing draft without duplicating it');
    $pastResponse = nova(array_replace($payload, ['id' => $novaId . '-past', 'publishDate' => time() - 7200, 'publishEndDate' => time() - 3600]));
    check($pastResponse->get_status() === 200 && get_post_status($pastResponse->get_data()['post_id']) === 'publish', 'Nova stores past archive dates for the standard archival job');
    Storage::saveIdentity(Storage::key('nova', '2:' . $novaId . '-draft'), 'nova', '2:' . $novaId . '-draft', $novaDraftId, 'expired');
    check(nova(array_replace($payload, ['id' => $novaId . '-draft']))->get_status() === 200, 'Generic API closed-identity rules do not alter Nova updates');
    $status = NovaPublicationEndpoint::getStatus();
    check($status['active'] && $status['owner'] === 'noticeboard', 'Shared status interface');
    update_option(NovaPublicationEndpoint::ENABLED_OPTION, '0');
    $disabled = nova($payload);
    check($disabled->get_status() === 401, 'Disabled Nova rejects request even with existing route: ' . wp_json_encode($disabled->get_data()));
    update_option(NovaPublicationEndpoint::ENABLED_OPTION, '1');
    // Simulate another route owner. Shared adapter must leave its callback intact.
    $GLOBALS['wp_rest_server'] = null;
    $legacyRoute = static function (): void {
        register_rest_route('nova/v1', '/publish', ['methods' => 'POST', 'callback' => '__return_true', 'permission_callback' => '__return_true']);
    };
    add_action('rest_api_init', $legacyRoute, 10);
    $routes = rest_get_server()->get_routes();
    check($routes['/nova/v1/publish'][0]['callback'] === '__return_true', 'Legacy route ownership preserved');
    check(NovaPublicationEndpoint::getStatus()['owner'] === 'external', 'External route status accurate');
    remove_action('rest_api_init', $legacyRoute, 10);
    $GLOBALS['wp_rest_server'] = null;
    check(NovaPublicationEndpoint::getStatus()['owner'] === 'noticeboard', 'Shared endpoint takes over after cleanup');
    global $wp;
    $wp->query_vars['rest_route'] = '/nova/v1/publish';
    check(!apply_filters('application_password_is_api_request', true), 'Nova Basic credentials isolated from WordPress authentication');
    $wp->query_vars['rest_route'] = '/wp/v2/posts';
    check(apply_filters('application_password_is_api_request', true), 'Other REST authentication unchanged');
    unset($wp->query_vars['rest_route']);
    wp_set_current_user(0);
    $admin = new Admin();
    ob_start(); $admin->render(); $html = ob_get_clean();
    check($html === '', 'Admin screen capability checked');
    $dieHandler = static function () {
        return static function ($message, $title, $args) { throw new RuntimeException('blocked:' . ($args['response'] ?? 500)); };
    };
    add_filter('wp_die_handler', $dieHandler);
    try { $admin->save(); check(false, 'Unauthorised admin mutation must fail'); }
    catch (RuntimeException $error) { check($error->getMessage() === 'blocked:403', 'Admin mutation capability enforced'); }
    wp_set_current_user(1);
    try { $admin->save(); check(false, 'Nonce-less admin mutation must fail'); }
    catch (RuntimeException $error) { check($error->getMessage() === 'blocked:403', 'Admin mutation nonce enforced'); }
    remove_filter('wp_die_handler', $dieHandler);
    ob_start(); $admin->render(); $html = ob_get_clean();
    check(strpos($html, '_wpnonce') !== false && strpos($html, 'name="operation"') !== false, 'Admin mutation forms include nonce');
    check(strpos($html, $token) === false && strpos($html, SOKIGO_NOVA_PUBLISH_PASSWORD) === false, 'Admin does not reveal secrets');
    check(!array_key_exists('token_hash', $tokens->listing()[0]), 'Token listing excludes hashes');
    check(strpos($html, 'nb-api-heading') < strpos($html, 'nb-nova-heading'), 'General API precedes Nova settings');
    global $wpdb;
    $storedDate = $wpdb->get_var($wpdb->prepare('SELECT created_at FROM ' . Storage::table('tokens') . ' WHERE token_id = %s', $issued['token_id']));
    $dateOptions = [];
    foreach (['timezone_string', 'date_format', 'time_format'] as $option) {
        $dateOptions[$option] = get_option($option);
    }
    try {
        $wpdb->update(Storage::table('tokens'), ['created_at' => '2026-01-01 23:30:00'], ['token_id' => $issued['token_id']]);
        update_option('date_format', 'Y-m-d');
        update_option('time_format', 'H:i');
        update_option('timezone_string', 'Europe/Stockholm');
        ob_start(); $admin->render(); $datedHtml = ob_get_clean();
        check(strpos($datedHtml, 'Created: 2026-01-02 00:30') !== false, 'Admin converts UTC creation time across date boundary to WordPress timezone');
        update_option('timezone_string', 'America/New_York');
        update_option('date_format', 'd/m/Y');
        ob_start(); $admin->render(); $datedHtml = ob_get_clean();
        check(strpos($datedHtml, 'Created: 01/01/2026 18:30') !== false, 'Admin follows changed WordPress timezone and date format');
        check(strpos($datedHtml, 'Created (UTC)') === false, 'Admin no longer labels local creation times UTC');
    } finally {
        $wpdb->update(Storage::table('tokens'), ['created_at' => $storedDate], ['token_id' => $issued['token_id']]);
        foreach ($dateOptions as $option => $value) {
            update_option($option, $value);
        }
    }

    $tokens->revoke($issued['token_id']);
    check(request('PUT', 'revoked', $token, $data)->get_status() === 401, 'Revoked token rejected');
    check(is_wp_error($tokens->rotate($issued['token_id'])), 'Revoked token cannot be rotated back to active');
    $deletionToken = $tokens->create($source . '-delete', 'Deletion test', ['create', 'update'], [$typeId], [$groupId]);
    $deletionResponse = request('PUT', 'keep-notice', $deletionToken['token'], $data);
    check($deletionResponse->get_status() === 201, 'Create notice before token deletion');
    $deletionPostId = $deletionResponse->get_data()['post_id'];
    $deletionKey = Storage::key($source . '-delete', 'keep-notice');
    $identityBefore = Storage::identity($deletionKey);
    check(!$tokens->deleteRevoked($deletionToken['token_id']), 'Active token cannot be permanently deleted');
    check(permittedTerms($deletionToken['token'])->get_status() === 200, 'Failed active-token deletion preserves access');
    $tokens->revoke($deletionToken['token_id']);
    ob_start(); $admin->render(); $revokedHtml = ob_get_clean();
    check(strpos($revokedHtml, 'Delete revoked token: Deletion test') !== false, 'Revoked token has deletion control');
    check($tokens->deleteRevoked($deletionToken['token_id']), 'Revoked token can be permanently deleted');
    check(!$wpdb->get_var($wpdb->prepare('SELECT token_id FROM ' . Storage::table('tokens') . ' WHERE token_id = %s', $deletionToken['token_id'])), 'Deleted credential removed from database');
    check(!in_array($deletionToken['token_id'], array_column($tokens->listing(), 'token_id'), true), 'Deleted credential removed from admin listing');
    check(permittedTerms($deletionToken['token'])->get_status() === 401, 'Deleted token remains unusable');
    check(get_post_status($deletionPostId) === 'publish' && Storage::identity($deletionKey) === $identityBefore, 'Token deletion preserves published notice and external identity');
    check(!$tokens->deleteRevoked($deletionToken['token_id']), 'Deleting missing token is rejected');
    echo "PASS: $checks integration assertions" . (function_exists('update_field') ? ' with ACF' : ' without ACF') . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL after ' . $checks . ' checks: ' . $error->getMessage() . "\n" . $error->getTraceAsString() . "\n");
    exit(1);
}
