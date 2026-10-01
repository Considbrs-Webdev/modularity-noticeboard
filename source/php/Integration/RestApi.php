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
        foreach (['type_ids', 'group_ids'] as $field) {
            if (array_diff($data[$field], $auth['policy'][$field])) {
                return Validation::error('noticeboard_forbidden_terms', 'Token does not permit the requested taxonomy terms.', 403);
            }
        }
        // The writer checks scope inside the identity lock, avoiding create/update races.
        $data['_scopes'] = $auth['policy']['scopes'];
        $result = (new NoticeWriter())->upsert($auth['source'], $id, $data);
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

    private function id(WP_REST_Request $request)
    {
        // URL params must win over JSON/query params; decode exactly once.
        $params = $request->get_url_params();
        $id = rawurldecode((string) ($params['external_id'] ?? ''));
        return Validation::externalId($id) ? $id : Validation::error('noticeboard_invalid_id', 'External ID must be 1–200 bytes, without whitespace, slashes, markup, or control characters.');
    }
}
