<?php

namespace ModularityNoticeboard\Integration;

use ModularityNoticeboard\Admin\Settings;
use WP_REST_Request;

/**
 * Optional request log for the integration endpoints, enabled in the Noticeboard settings.
 * Writes one JSON line per event to a protected file in the uploads directory.
 * Authorization headers and credentials are never logged; request bodies are.
 */
final class RequestLogger
{
    public const FIELD = 'enable_request_logging';
    private const MAX_BODY_BYTES = 65536;
    private const MAX_FILE_BYTES = 5242880;
    private const LOG_HEADERS = ['content-type', 'content-length', 'user-agent', 'x-forwarded-for'];

    public function register(): void
    {
        add_filter('rest_request_before_callbacks', [$this, 'logRequest'], 10, 3);
        add_filter('rest_request_after_callbacks', [$this, 'logResponse'], 10, 3);
    }

    public static function isEnabled(): bool
    {
        return function_exists('get_field') && (bool) get_field(self::FIELD, Settings::OPTION_PAGE_SLUG);
    }

    public function logRequest($response, $handler, $request)
    {
        if ($request instanceof WP_REST_Request && self::shouldLog($request)) {
            $id = bin2hex(random_bytes(4));
            $request->set_attributes(['noticeboard_log_id' => $id] + $request->get_attributes());
            $body = $request->get_body();
            $headers = [];
            foreach (self::LOG_HEADERS as $name) {
                $value = $request->get_header($name);
                if ($value !== null && $value !== '') {
                    $headers[$name] = $value;
                }
            }
            self::write([
                'event' => 'request', 'id' => $id, 'method' => $request->get_method(),
                'route' => $request->get_route(), 'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'headers' => $headers, 'has_authorization' => (string) $request->get_header('authorization') !== '',
                'body_bytes' => strlen($body),
                'body' => strlen($body) > self::MAX_BODY_BYTES ? substr($body, 0, self::MAX_BODY_BYTES) . '…[truncated]' : $body,
            ]);
        }
        return $response;
    }

    public function logResponse($response, $handler, $request)
    {
        $id = $request instanceof WP_REST_Request ? $request->get_attributes()['noticeboard_log_id'] ?? null : null;
        if ($id !== null) {
            $entry = ['event' => 'response', 'id' => $id];
            if (is_wp_error($response)) {
                $data = $response->get_error_data();
                $entry += ['status' => is_array($data) ? ($data['status'] ?? 500) : 500,
                    'error_code' => $response->get_error_code(), 'error' => $response->get_error_message()];
            } elseif ($response instanceof \WP_HTTP_Response) {
                $entry += ['status' => $response->get_status(), 'data' => $response->get_data()];
            }
            self::write($entry);
        }
        return $response;
    }

    public static function logPath(): string
    {
        $dir = trailingslashit(wp_upload_dir(null, false)['basedir']) . 'noticeboard-logs';
        return $dir . '/integrations-' . substr(wp_hash('noticeboard-integration-log'), 0, 16) . '.log';
    }

    private static function shouldLog(WP_REST_Request $request): bool
    {
        $route = $request->get_route();
        return (str_starts_with($route, '/' . RestApi::REST_NAMESPACE . '/')
            || $route === '/' . NovaPublicationEndpoint::REST_NAMESPACE . NovaPublicationEndpoint::REST_ROUTE)
            && self::isEnabled();
    }

    private static function write(array $entry): void
    {
        $path = self::logPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!wp_mkdir_p($dir)) {
                return;
            }
            // Deny direct access on Apache and prevent directory listings elsewhere.
            @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
            @file_put_contents($dir . '/index.php', "<?php\n// Silence is golden.\n");
        }
        if (is_file($path) && filesize($path) > self::MAX_FILE_BYTES) {
            @rename($path, $path . '.1');
        }
        $line = wp_json_encode(['time' => gmdate('c')] + $entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        @file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
    }
}
