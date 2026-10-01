<?php

namespace ModularityNoticeboard\Integration;

use ModularityNoticeboard\Data\Posttype;
use WP_Error;

final class NoticeWriter
{
    public const SOURCE_META = '_noticeboard_integration_source';
    public const ID_META = '_noticeboard_external_id';
    private const FIELDS = [
        'archive_date' => 'field_69679a808b9be',
        'archive_time' => 'field_6a0eeddaa1aa7',
        'pdf_file' => 'field_69679b488b9c0',
    ];

    /** @return array|WP_Error $legacy is internal Nova identity information, never request data. */
    public function upsert(string $source, string $id, array $data, ?array $legacy = null)
    {
        $scopes = $data['_scopes'] ?? ['create', 'update'];
        $data = Validation::notice($data);
        if (is_wp_error($data)) {
            return $data;
        }
        if (!post_type_exists(Posttype::NOTICE_POST_TYPE) || !taxonomy_exists(Posttype::NOTICE_TAXONOMY)
            || !taxonomy_exists(Posttype::NOTICE_GROUP_TAXONOMY)) {
            return Validation::error('noticeboard_unavailable', 'Noticeboard post type or taxonomies are unavailable.', 503);
        }
        foreach ([Posttype::NOTICE_TAXONOMY => $data['type_ids'], Posttype::NOTICE_GROUP_TAXONOMY => $data['group_ids']] as $taxonomy => $ids) {
            foreach ($ids as $termId) {
                if (!term_exists($termId, $taxonomy)) {
                    return Validation::error('noticeboard_unknown_term', 'Unknown term ID in ' . $taxonomy . '.');
                }
            }
        }
        $key = Storage::key($source, $id);
        if (!Storage::lock($key)) {
            return Validation::error('noticeboard_busy', 'Publication is busy; retry the request.', 503);
        }
        try {
            return $this->writeLocked($key, $source, $id, $data, $legacy, $scopes);
        } finally {
            Storage::unlock($key);
        }
    }

    /** @return array|WP_Error */
    private function writeLocked(string $key, string $source, string $id, array $data, ?array $legacy, array $scopes)
    {
        $row = Storage::identity($key);
        if (!$legacy && $row && in_array($row['state'], ['withdrawn', 'expired'], true)) {
            return Validation::error('noticeboard_closed', 'This external ID is withdrawn or expired; use a new ID for a new publication.', 409);
        }
        $postId = $row ? (int) $row['post_id'] : 0;
        $creationPending = !$row || $row['state'] === 'creating';
        if (!in_array($creationPending ? 'create' : 'update', $scopes, true)) {
            return Validation::error('noticeboard_forbidden_scope', 'Token does not permit this publication operation.', 403);
        }
        if ($legacy && (!$postId || !get_post($postId) || get_post_status($postId) === 'trash')) {
            $postId = 0;
            $creationPending = true;
        }
        if (!$postId && $legacy) {
            $matches = get_posts([
                'post_type' => Posttype::NOTICE_POST_TYPE,
                'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
                'posts_per_page' => 2, 'fields' => 'ids', 'suppress_filters' => true,
                'meta_query' => [
                    ['key' => '_pitea_nova_publication_id', 'value' => $legacy['id']],
                    ['key' => '_pitea_nova_publication_type', 'value' => $legacy['type'], 'type' => 'NUMERIC'],
                ],
            ]);
            if (count($matches) > 1) {
                return Validation::error('noticeboard_legacy_conflict', 'Multiple legacy notices match this identity; resolve them before retrying.', 409);
            }
            $postId = $matches ? (int) $matches[0] : 0;
        }
        $post = $postId ? get_post($postId) : null;
        if ($postId && (!$post || $post->post_type !== Posttype::NOTICE_POST_TYPE)) {
            return Validation::error('noticeboard_removed', 'The imported notice was removed; use a new external ID.', 409);
        }
        if ($post && get_post_meta($postId, self::SOURCE_META, true) !== ''
            && get_post_meta($postId, self::SOURCE_META, true) !== $source) {
            return Validation::error('noticeboard_owner_conflict', 'Notice ownership does not match this integration.', 409);
        }
        if (!$legacy && $post && ($post->post_status === 'trash' || ($row && $row['state'] === 'active'
            && !in_array($post->post_status, ['publish', 'future'], true)))) {
            return Validation::error('noticeboard_closed', 'The notice was unpublished locally; use a new external ID.', 409);
        }
        $oldArchive = $postId ? NoticeLifecycle::archiveTimestamp($postId) : null;
        if (!$legacy && $post && (!$row || $row['state'] === 'active') && $oldArchive !== null && $oldArchive <= time()) {
            if (!Storage::saveIdentity($key, $source, $id, $postId, 'expired')) {
                return $this->saveError();
            }
            $saved = wp_update_post(['ID' => $postId, 'post_status' => 'draft'], true);
            if (is_wp_error($saved) || !$saved) {
                return $this->saveError();
            }
            return Validation::error('noticeboard_closed', 'The existing notice has expired; use a new external ID.', 409);
        }
        $created = !$postId;
        $publish = (new \DateTimeImmutable('@' . $data['publish_at']))->setTimezone(wp_timezone());
        $postData = [
            'post_type' => Posttype::NOTICE_POST_TYPE,
            'post_status' => $legacy ? ($data['publish_at'] > time() ? 'future' : 'publish') : 'draft',
            'post_title' => $data['title'], 'post_content' => $data['content'],
            'post_date' => $publish->format('Y-m-d H:i:s'),
            'post_date_gmt' => gmdate('Y-m-d H:i:s', $data['publish_at']),
        ];
        if ($postId) {
            // Record pending before changing an active notice, so failed writes can be retried.
            if (!Storage::saveIdentity($key, $source, $id, $postId, $creationPending ? 'creating' : 'pending')) {
                return $this->saveError();
            }
            $postData['ID'] = $postId;
            $saved = wp_update_post(wp_slash($postData), true);
        } else {
            $saved = wp_insert_post(wp_slash($postData), true);
        }
        if (is_wp_error($saved) || !$saved) {
            return $this->saveError();
        }
        $postId = (int) $saved;
        if (!Storage::saveIdentity($key, $source, $id, $postId, $creationPending ? 'creating' : 'pending')) {
            if ($created) {
                wp_delete_post($postId, true);
            }
            return $this->saveError();
        }
        foreach ([self::SOURCE_META => $source, self::ID_META => $id] as $name => $value) {
            if (!$this->meta($postId, $name, $value)) {
                return $this->saveError();
            }
        }
        foreach ([Posttype::NOTICE_TAXONOMY => $data['type_ids'], Posttype::NOTICE_GROUP_TAXONOMY => $data['group_ids']] as $taxonomy => $ids) {
            if ($legacy && $taxonomy === Posttype::NOTICE_GROUP_TAXONOMY) {
                continue; // Nova's existing contract does not own locally assigned groups.
            }
            $terms = wp_set_object_terms($postId, $ids, $taxonomy, false);
            $actual = wp_get_object_terms($postId, $taxonomy, ['fields' => 'ids']);
            if (is_wp_error($terms) || is_wp_error($actual) || array_diff($ids, $actual) || array_diff($actual, $ids)) {
                return $this->saveError();
            }
        }
        $archive = $data['archive_at'] === null ? null : (new \DateTimeImmutable('@' . $data['archive_at']))->setTimezone(wp_timezone());
        foreach (['archive_date' => $archive ? $archive->format('Ymd') : '',
            'archive_time' => $archive ? $archive->format('H:i') : '',
            'pdf_file' => $data['document_url'] === '' ? '' : ['title' => '', 'url' => $data['document_url'], 'target' => '']] as $name => $value) {
            if ($legacy && $name === 'pdf_file') {
                continue; // Preserve locally attached documents during Nova updates/migration.
            }
            if (function_exists('update_field')) {
                update_field(self::FIELDS[$name], $value, $postId);
            } else {
                // ACF stores dates as Ymd and links as arrays, even when return_format is URL.
                update_post_meta($postId, $name, $value);
                update_post_meta($postId, '_' . $name, self::FIELDS[$name]);
            }
            if (get_post_meta($postId, $name, true) != $value
                || get_post_meta($postId, '_' . $name, true) !== self::FIELDS[$name]) {
                return $this->saveError();
            }
        }
        if ($legacy) {
            foreach (['_pitea_nova_publication_id' => $legacy['id'], '_pitea_nova_publication_type' => (string) $legacy['type']] as $name => $value) {
                if (!$this->meta($postId, $name, $value)) {
                    return $this->saveError();
                }
            }
        }
        $archiveSeconds = $data['archive_at'] === null ? '' : (string) (intdiv($data['archive_at'], 60) * 60);
        if (!$this->meta($postId, '_noticeboard_archive_at', $archiveSeconds)) {
            return $this->saveError();
        }
        // Match the existing minute-resolution archive fields, conservatively rounding down.
        $expired = !$legacy && $data['archive_at'] !== null && (intdiv($data['archive_at'], 60) * 60) <= time();
        $state = $expired ? 'expired' : 'active';
        if (!Storage::saveIdentity($key, $source, $id, $postId, $state)) {
            return $this->saveError();
        }
        $status = $expired ? 'draft' : ($data['publish_at'] > time() ? 'future' : 'publish');
        // Nova already saved its requested status above, matching its original flow.
        $result = $legacy ? $postId : wp_update_post(['ID' => $postId, 'post_status' => $status], true);
        if (is_wp_error($result) || !$result) {
            Storage::saveIdentity($key, $source, $id, $postId, $creationPending ? 'creating' : 'pending');
            return $this->saveError();
        }
        return ['post_id' => $postId, 'created' => $created, 'status' => get_post_status($postId),
            'url' => get_permalink($postId)];
    }

    /** @return array|WP_Error Persist a tombstone even for an unknown ID. */
    public function withdraw(string $source, string $id)
    {
        $key = Storage::key($source, $id);
        if (!Storage::lock($key)) {
            return Validation::error('noticeboard_busy', 'Publication is busy; retry the request.', 503);
        }
        try {
            $row = Storage::identity($key);
            $postId = $row ? (int) $row['post_id'] : 0;
            if (!Storage::saveIdentity($key, $source, $id, $postId, 'withdrawn')) {
                return $this->saveError();
            }
            $post = $postId ? get_post($postId) : null;
            if ($post && $post->post_type === Posttype::NOTICE_POST_TYPE && $post->post_status !== 'trash') {
                $saved = wp_update_post(['ID' => $postId, 'post_status' => 'draft'], true);
                if (is_wp_error($saved) || !$saved) {
                    return $this->saveError();
                }
            }
            return ['success' => true, 'post_id' => $postId ?: null, 'status' => 'withdrawn'];
        } finally {
            Storage::unlock($key);
        }
    }

    private function meta(int $postId, string $name, string $value): bool
    {
        update_post_meta($postId, $name, wp_slash($value));
        return get_post_meta($postId, $name, true) === $value;
    }

    private function saveError(): WP_Error
    {
        return Validation::error('noticeboard_save_failed', 'Publication could not be saved completely; inspect the notice and retry or contact the administrator.', 500);
    }
}
