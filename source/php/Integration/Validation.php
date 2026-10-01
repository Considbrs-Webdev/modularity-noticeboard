<?php

namespace ModularityNoticeboard\Integration;

use WP_Error;

final class Validation
{
    public const MAX_BODY_BYTES = 262144;

    public static function error(string $code, string $message, int $status = 400): WP_Error
    {
        return new WP_Error($code, $message, ['status' => $status]);
    }

    /** Positive integral Unix seconds, constrained to years 1970–9999. */
    public static function timestamp($value): bool
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value)))
            && (int) $value > 0 && (float) $value <= 253402300799;
    }

    public static function externalId($value): bool
    {
        return is_string($value) && $value !== '' && strlen($value) <= 200
            && !preg_match('/[\x00-\x20\x7f\/\\\\]/', $value)
            && sanitize_text_field($value) === $value;
    }

    /** @return array|WP_Error */
    public static function body(\WP_REST_Request $request)
    {
        if (strlen($request->get_body()) > self::MAX_BODY_BYTES) {
            return self::error('noticeboard_payload_too_large', 'Request exceeds 256 KiB.', 413);
        }
        $body = $request->get_json_params();
        if (is_wp_error($body)) {
            return $body;
        }
        // JSON must be an object, not an array or scalar.
        if (!is_array($body) || substr(ltrim($request->get_body()), 0, 1) !== '{') {
            return self::error('noticeboard_invalid_json', 'Request body must be a JSON object.');
        }
        return $body;
    }

    /** @return array|WP_Error Sanitised canonical data, also used by the writer. */
    public static function notice(array $data)
    {
        foreach (['title', 'content', 'publish_at'] as $field) {
            if (!array_key_exists($field, $data)) {
                return self::error('noticeboard_missing_field', 'Missing required field: ' . $field . '.');
            }
        }
        if (!is_string($data['title']) || trim(sanitize_text_field($data['title'])) === ''
            || strlen($data['title']) > 500 || !is_string($data['content'])
            || strlen($data['content']) > self::MAX_BODY_BYTES) {
            return self::error('noticeboard_invalid_content', 'Title and content must be strings; title must be nonempty and at most 500 bytes.');
        }
        if (!self::timestamp($data['publish_at'])) {
            return self::error('noticeboard_invalid_timestamp', 'publish_at must be positive integral Unix seconds.');
        }
        $archive = $data['archive_at'] ?? null;
        if ($archive !== null && (!self::timestamp($archive) || (int) $archive <= (int) $data['publish_at'])) {
            return self::error('noticeboard_invalid_archive', 'archive_at must be integral Unix seconds after publish_at, or null.');
        }
        $url = array_key_exists('document_url', $data) ? $data['document_url'] : '';
        if (!is_string($url) || strlen($url) > 2048 || ($url !== '' &&
            (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)))) {
            return self::error('noticeboard_invalid_url', 'document_url must be an HTTP(S) URL or an empty string.');
        }
        foreach (['type_ids', 'group_ids'] as $field) {
            $ids = array_key_exists($field, $data) ? $data[$field] : [];
            if (!is_array($ids) || count($ids) > 50 || array_values($ids) !== $ids) {
                return self::error('noticeboard_invalid_terms', $field . ' must be an array of at most 50 integer term IDs.');
            }
            foreach ($ids as $id) {
                if (!is_int($id) || $id <= 0) {
                    return self::error('noticeboard_invalid_terms', $field . ' must contain positive integer term IDs.');
                }
            }
        }
        return [
            'title' => sanitize_text_field($data['title']),
            'content' => wp_kses_post($data['content']),
            'publish_at' => (int) $data['publish_at'],
            'archive_at' => $archive === null ? null : (int) $archive,
            'document_url' => esc_url_raw($url, ['http', 'https']),
            'type_ids' => array_values(array_unique($data['type_ids'] ?? [])),
            'group_ids' => array_values(array_unique($data['group_ids'] ?? [])),
        ];
    }
}
