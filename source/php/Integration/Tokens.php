<?php

namespace ModularityNoticeboard\Integration;

final class Tokens
{
    /** @return array|\WP_Error Called by authorised administration, never a public REST route. */
    public function create(string $source, string $label, array $scopes, array $typeIds, array $groupIds)
    {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $source) || $source === 'nova'
            || trim($label) === '' || strlen($label) > 200 || !$scopes
            || array_diff($scopes, ['create', 'update', 'withdraw'])) {
            return Validation::error('noticeboard_invalid_token_policy', __('Use a source slug other than nova, a label, and at least one valid scope.', 'modularity-noticeboard'));
        }
        if (!$typeIds) {
            return Validation::error('noticeboard_invalid_token_policy', __('Select at least one permitted notice type.', 'modularity-noticeboard'));
        }
        foreach (['noticeboard_notice_type' => $typeIds, 'notice_group' => $groupIds] as $taxonomy => $ids) {
            foreach ($ids as $id) {
                if (!is_int($id) || $id <= 0 || !term_exists($id, $taxonomy)) {
                    return Validation::error('noticeboard_invalid_token_policy', __('Select existing permitted taxonomy terms.', 'modularity-noticeboard'));
                }
            }
        }
        global $wpdb;
        $id = bin2hex(random_bytes(16));
        $secret = bin2hex(random_bytes(32));
        $policy = ['scopes' => array_values(array_unique($scopes)),
            'type_ids' => array_values(array_unique($typeIds)), 'group_ids' => array_values(array_unique($groupIds))];
        $saved = $wpdb->insert(Storage::table('tokens'), [
            'token_id' => $id, 'source' => $source, 'label' => sanitize_text_field($label),
            'token_hash' => hash('sha256', $secret), 'policy' => wp_json_encode($policy),
            'created_at' => gmdate('Y-m-d H:i:s'), 'revoked' => 0,
        ]);
        if (!$saved) {
            return Validation::error('noticeboard_token_save_failed', __('Token could not be saved.', 'modularity-noticeboard'), 500);
        }
        return ['token_id' => $id, 'token' => 'nb_' . $id . '.' . $secret];
    }

    /** @return array|\WP_Error */
    public function authenticate(\WP_REST_Request $request)
    {
        if (!is_ssl()) {
            return Validation::error('noticeboard_https_required', 'Use HTTPS for publication credentials.', 403);
        }
        $header = (string) $request->get_header('authorization');
        if (!preg_match('/^Bearer nb_([a-f0-9]{32})\.([a-f0-9]{64})$/iD', $header, $match)) {
            return Validation::error('noticeboard_invalid_token', 'Invalid or revoked publication token.', 401);
        }
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Storage::table('tokens') . ' WHERE token_id = %s', $match[1]), ARRAY_A);
        if (!$row || (int) $row['revoked'] || !hash_equals($row['token_hash'], hash('sha256', $match[2]))) {
            return Validation::error('noticeboard_invalid_token', 'Invalid or revoked publication token.', 401);
        }
        $policy = json_decode($row['policy'], true);
        if (!is_array($policy) || !isset($policy['scopes'], $policy['type_ids'], $policy['group_ids'])) {
            return Validation::error('noticeboard_invalid_token', 'Invalid or revoked publication token.', 401);
        }
        return ['source' => $row['source'], 'policy' => $policy];
    }

    public function revoke(string $id): bool
    {
        global $wpdb;
        return false !== $wpdb->update(Storage::table('tokens'), ['revoked' => 1], ['token_id' => $id]);
    }

    /** Permanently remove a revoked credential without touching publications or identities. */
    public function deleteRevoked(string $id): bool
    {
        global $wpdb;
        return 1 === $wpdb->delete(Storage::table('tokens'), ['token_id' => $id, 'revoked' => 1]);
    }

    /** @return array|\WP_Error Rotation replaces the secret while retaining source and policy. */
    public function rotate(string $id)
    {
        global $wpdb;
        $secret = bin2hex(random_bytes(32));
        $saved = $wpdb->update(Storage::table('tokens'), ['token_hash' => hash('sha256', $secret)], ['token_id' => $id, 'revoked' => 0]);
        if ($saved !== 1) {
            return Validation::error('noticeboard_token_rotation_failed', __('Active token could not be rotated.', 'modularity-noticeboard'), 400);
        }
        return ['token_id' => $id, 'token' => 'nb_' . $id . '.' . $secret];
    }

    /** Secret hashes are deliberately excluded from administration data. */
    public function listing(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT token_id, source, label, policy, created_at, revoked FROM ' . Storage::table('tokens') . ' ORDER BY created_at DESC', ARRAY_A) ?: [];
    }
}
