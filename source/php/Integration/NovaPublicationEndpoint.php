<?php

namespace ModularityNoticeboard\Integration;

use ModularityNoticeboard\Data\Posttype;
use WP_REST_Request;
use WP_REST_Response;

final class NovaPublicationEndpoint
{
    public const REST_NAMESPACE = 'nova/v1';
    public const REST_ROUTE = '/publish';
    public const USERNAME_CONSTANT = 'SOKIGO_NOVA_PUBLISH_USERNAME';
    public const PASSWORD_CONSTANT = 'SOKIGO_NOVA_PUBLISH_PASSWORD';
    public const ENABLED_OPTION = 'noticeboard_nova_enabled';

    public static function isConfigured(): bool
    {
        return self::credential(self::USERNAME_CONSTANT) !== '' && self::credential(self::PASSWORD_CONSTANT) !== '';
    }

    public static function isEnabled(): bool
    {
        return (bool) get_option(self::ENABLED_OPTION, true);
    }

    public static function getEndpointUrl(): string
    {
        return rest_url(self::REST_NAMESPACE . self::REST_ROUTE);
    }

    /** Stable public interface for client settings panels. Never includes secrets. */
    public static function getStatus(): array
    {
        $routes = rest_get_server()->get_routes();
        $endpoints = $routes['/' . self::REST_NAMESPACE . self::REST_ROUTE] ?? [];
        $owner = 'none';
        foreach ($endpoints as $endpoint) {
            $callback = is_array($endpoint) ? ($endpoint['callback'] ?? null) : null;
            if ($callback !== null) {
                $owner = is_array($callback) && is_object($callback[0]) && $callback[0] instanceof self ? 'noticeboard' : 'external';
                break;
            }
        }
        return ['configured' => self::isConfigured(), 'enabled' => self::isEnabled(),
            'endpoint_url' => self::getEndpointUrl(), 'owner' => $owner,
            'active' => self::isConfigured() && $owner === 'noticeboard'];
    }

    public function registerRoute(): void
    {
        // Priority 99 lets an existing route owner register before this adapter.
        $routes = rest_get_server()->get_routes();
        if (!self::isConfigured() || !self::isEnabled()
            || isset($routes['/' . self::REST_NAMESPACE . self::REST_ROUTE])) {
            return;
        }
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
            'methods' => 'POST', 'callback' => [$this, 'handleRequest'],
            'permission_callback' => [$this, 'authenticateRequest'],
        ]);
    }

    public function authenticateRequest(WP_REST_Request $request)
    {
        if (!self::isConfigured() || !self::isEnabled()) {
            return Validation::error('nova_not_configured', 'Sokigo Nova publication endpoint is not configured.', 401);
        }
        if (!is_ssl()) {
            return Validation::error('noticeboard_https_required', 'Use HTTPS for publication credentials.', 403);
        }
        $header = (string) $request->get_header('authorization');
        if ($header === '') {
            foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
                if (isset($_SERVER[$key]) && is_string($_SERVER[$key])) {
                    $header = $_SERVER[$key];
                    break;
                }
            }
        }
        if (stripos($header, 'Basic ') !== 0) {
            return Validation::error('nova_missing_authorization', 'Missing Authorization header.', 401);
        }
        $decoded = base64_decode(substr($header, 6), true);
        if (!is_string($decoded) || !str_contains($decoded, ':')) {
            return Validation::error('nova_invalid_authorization', 'Invalid Authorization header.', 401);
        }
        [$username, $password] = explode(':', $decoded, 2);
        if (!hash_equals(self::credential(self::USERNAME_CONSTANT), $username)
            || !hash_equals(self::credential(self::PASSWORD_CONSTANT), $password)) {
            return Validation::error('nova_invalid_credentials', 'Invalid credentials.', 401);
        }
        return true;
    }

    public function handleRequest(WP_REST_Request $request)
    {
        $auth = $this->authenticateRequest($request);
        if (is_wp_error($auth)) {
            return $auth;
        }
        $payload = Validation::body($request);
        if (is_wp_error($payload)) {
            return $payload;
        }
        foreach (['type', 'id', 'title', 'content', 'publishDate'] as $field) {
            if (!isset($payload[$field]) || $payload[$field] === '') {
                return Validation::error('nova_missing_required_field', 'Missing required field: ' . $field . '.');
            }
        }
        if (!in_array($payload['type'], [1, 2, 3, '1', '2', '3'], true)) {
            return Validation::error('nova_invalid_type', 'The type field must be 1, 2, or 3.');
        }
        foreach (['id', 'title', 'content', 'publicNotification', 'estate', 'decision', 'decisionNumber'] as $field) {
            if (isset($payload[$field]) && !is_string($payload[$field])
                && !(in_array($field, ['id', 'decisionNumber'], true) && is_int($payload[$field]))) {
                return Validation::error('nova_invalid_field', $field . ' must be a string (id and decisionNumber also accept integers).');
            }
        }
        $id = sanitize_text_field((string) $payload['id']);
        if ($id === '' || strlen($id) > 200) {
            return Validation::error('nova_invalid_id', 'Case ID must be nonempty and at most 200 bytes.');
        }
        foreach (['publishDate', 'publishEndDate', 'decisionDate', 'responseDate'] as $field) {
            if (isset($payload[$field]) && $payload[$field] !== '' && !Validation::timestamp($payload[$field])) {
                return Validation::error('nova_invalid_timestamp', $field . ' must be integral Unix seconds.');
            }
        }
        $type = (int) $payload['type'];
        $data = [
            'title' => $payload['title'], 'content' => $this->content($payload),
            'publish_at' => $payload['publishDate'],
            'archive_at' => isset($payload['publishEndDate']) && $payload['publishEndDate'] !== '' ? $payload['publishEndDate'] : null,
        ];
        $valid = Validation::notice($data);
        if (is_wp_error($valid)) {
            return $valid;
        }
        $names = [1 => ['Kungörelser', 'Bygglov'], 2 => ['Beslut', 'Bygglov'], 3 => ['Bygglov']];
        $names = apply_filters('Modularity/Noticeboard/Nova/TypeNames', $names[$type], $type, $payload);
        if (!is_array($names) || count($names) > 50) {
            return Validation::error('nova_invalid_mapping', 'Nova type mapping is invalid.', 500);
        }
        $ids = [];
        foreach ($names as $name) {
            if (!is_string($name) || trim($name) === '') {
                return Validation::error('nova_invalid_mapping', 'Nova type names must be nonempty strings.', 500);
            }
            $term = term_exists($name, Posttype::NOTICE_TAXONOMY);
            if (!$term) {
                $term = wp_insert_term($name, Posttype::NOTICE_TAXONOMY, ['slug' => sanitize_title($name)]);
                // Concurrent publications of different identities may create the same term.
                if (is_wp_error($term) && $term->get_error_code() === 'term_exists') {
                    $term = ['term_id' => $term->get_error_data()];
                }
            }
            if (is_wp_error($term)) {
                return Validation::error('nova_notice_type_failed', 'Nova notice types could not be saved.', 500);
            }
            $ids[] = (int) (is_array($term) ? $term['term_id'] : $term);
        }
        $valid['type_ids'] = array_values(array_unique($ids));
        $result = (new NoticeWriter())->upsert('nova', $type . ':' . $id, $valid, ['create', 'update'], true);
        return is_wp_error($result) ? $result : new WP_REST_Response(['success' => true, 'post_id' => $result['post_id']], 200);
    }

    private function content(array $payload): string
    {
        $content = wpautop(wp_kses_post($payload['content']));
        if ((int) $payload['type'] === 1 && !empty($payload['publicNotification'])) {
            $content .= wpautop(esc_html(sanitize_text_field($payload['publicNotification'])));
        }
        $details = [];
        foreach (['id' => __('Case ID', 'modularity-noticeboard'), 'estate' => __('Estate', 'modularity-noticeboard'),
            'decision' => __('Decision', 'modularity-noticeboard'), 'decisionNumber' => __('Decision number', 'modularity-noticeboard'),
            'decisionDate' => __('Decision date', 'modularity-noticeboard'), 'responseDate' => __('Response date', 'modularity-noticeboard')] as $field => $label) {
            $value = $payload[$field] ?? '';
            if (in_array($field, ['decisionDate', 'responseDate'], true)) {
                $value = Validation::timestamp($value) ? wp_date(get_option('date_format'), (int) $value, wp_timezone()) : '';
            } else {
                $value = sanitize_text_field((string) $value);
            }
            if ($value !== '') {
                $details[] = '<strong>' . esc_html($label) . ':</strong> ' . esc_html($value);
            }
        }
        if ($details) {
            $content .= '<p>' . implode('<br>', $details) . '</p>';
        }
        return wp_kses_post((string) apply_filters('Modularity/Noticeboard/Nova/Content', $content, $payload));
    }

    /** Nova credentials are not WordPress Application Passwords. Scope the exemption to this route. */
    public static function applicationPasswordRequest(bool $isApi): bool
    {
        global $wp;
        $route = $wp->query_vars['rest_route'] ?? ($_GET['rest_route'] ?? '');
        return is_string($route) && rtrim($route, '/') === '/' . self::REST_NAMESPACE . self::REST_ROUTE ? false : $isApi;
    }

    private static function credential(string $name): string
    {
        return defined($name) ? trim((string) constant($name)) : '';
    }
}
