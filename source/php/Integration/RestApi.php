<?php

namespace ModularityNoticeboard\Integration;

use ModularityNoticeboard\Data\Posttype;
use WP_REST_Request;
use WP_REST_Response;

final class RestApi
{
    public const REST_NAMESPACE = 'noticeboard/v1';

    public function registerRoute(): void
    {
        register_rest_route(self::REST_NAMESPACE, '/terms', [
            'methods' => 'GET', 'callback' => [$this, 'terms'],
            'permission_callback' => [$this, 'permission'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/notices/(?P<external_id>[^/]+)', [
            [
                'methods' => 'PUT', 'callback' => [$this, 'put'],
                'permission_callback' => [$this, 'permission'],
            ],
            [
                'methods' => 'DELETE', 'callback' => [$this, 'delete'],
                'permission_callback' => [$this, 'permission'],
            ],
        ]);
    }

    public function permission(WP_REST_Request $request)
    {
        $auth = (new Tokens())->authenticate($request);
        return is_wp_error($auth) ? $auth : true;
    }

    public function terms(WP_REST_Request $request)
    {
        $auth = (new Tokens())->authenticate($request);
        if (is_wp_error($auth)) {
            return $auth;
        }
        $data = ['notice_types' => [], 'groups' => []];
        foreach ([
            'notice_types' => [Posttype::NOTICE_TAXONOMY, 'type_ids'],
            'groups' => [Posttype::NOTICE_GROUP_TAXONOMY, 'group_ids'],
        ] as $key => [$taxonomy, $policyField]) {
            $ids = $auth['policy'][$policyField];
            // An empty include list would return every term in WordPress.
            if (!$ids) {
                continue;
            }
            $terms = get_terms(['taxonomy' => $taxonomy, 'include' => $ids,
                'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);
            if (is_wp_error($terms)) {
                return Validation::error('noticeboard_terms_failed', 'Noticeboard terms could not be retrieved.', 500);
            }
            foreach ($terms as $term) {
                $data[$key][] = ['id' => (int) $term->term_id, 'name' => $term->name, 'slug' => $term->slug];
            }
        }
        $response = new WP_REST_Response($data, 200);
        // Results depend on token permissions and must not be shared by caches.
        $response->header('Cache-Control', 'private, no-store');
        return $response;
    }

    public function put(WP_REST_Request $request)
    {
        // Reauthenticate in the callback rather than trusting request-controlled attributes.
        $auth = (new Tokens())->authenticate($request);
        if (is_wp_error($auth)) {
            return $auth;
        }
        $id = $this->id($request);
        if (is_wp_error($id)) {
            return $id;
        }
        $body = Validation::body($request);
        if (is_wp_error($body)) {
            return $body;
        }
        $unknown = array_diff(array_keys($body), ['title', 'content', 'publish_at', 'archive_at', 'document_url', 'type_ids', 'group_ids']);
        if ($unknown) {
            return Validation::error('noticeboard_unknown_field', 'Unknown fields: ' . implode(', ', $unknown) . '.');
        }
        $data = Validation::notice($body);
        if (is_wp_error($data)) {
            return $data;
        }
        // A notice type is required, so the type policy limits every notice a token can publish.
        if (!$data['type_ids']) {
            return Validation::error('noticeboard_missing_type', 'type_ids must contain at least one permitted notice type ID.');
        }
        foreach (['type_ids', 'group_ids'] as $field) {
            if (array_diff($data[$field], $auth['policy'][$field])) {
                return Validation::error('noticeboard_forbidden_terms', 'Token does not permit the requested taxonomy terms.', 403);
            }
        }
        // The writer checks scope inside the identity lock, avoiding create/update races.
        $result = (new NoticeWriter())->upsert($auth['source'], $id, $data, $auth['policy']['scopes']);
        return is_wp_error($result) ? $result : new WP_REST_Response(['success' => true] + $result, $result['created'] ? 201 : 200);
    }

    public function delete(WP_REST_Request $request)
    {
        $auth = (new Tokens())->authenticate($request);
        if (is_wp_error($auth)) {
            return $auth;
        }
        if (!in_array('withdraw', $auth['policy']['scopes'], true)) {
            return Validation::error('noticeboard_forbidden_scope', 'Token does not permit withdrawal.', 403);
        }
        $id = $this->id($request);
        if (is_wp_error($id)) {
            return $id;
        }
        $result = (new NoticeWriter())->withdraw($auth['source'], $id);
        return is_wp_error($result) ? $result : new WP_REST_Response($result, 200);
    }

    private function routeFromQuery(WP_REST_Request $request): bool
    {
        // WordPress reads rest_route from POST, then GET, before permalink matches.
        foreach ([$_POST, $_GET] as $input) {
            if (isset($input['rest_route']) && is_string($input['rest_route'])) {
                return untrailingslashit(wp_unslash($input['rest_route'])) === untrailingslashit($request->get_route());
            }
        }
        return false;
    }

    private function id(WP_REST_Request $request)
    {
        // URL params must win over JSON/query params; decode exactly once.
        $params = $request->get_url_params();
        $id = (string) ($params['external_id'] ?? '');
        // Pretty permalinks pass the route still percent-encoded; ?rest_route= is already decoded by PHP.
        if (!$this->routeFromQuery($request)) {
            $id = rawurldecode($id);
        }
        return Validation::externalId($id) ? $id : Validation::error('noticeboard_invalid_id', 'External ID must be 1–200 bytes, without whitespace, slashes, markup, or control characters.');
    }
}
