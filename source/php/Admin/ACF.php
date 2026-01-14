<?php

namespace ModularityNoticeboard\Admin;

use ModularityNoticeboard\Data\Posttype;

class ACF
{
    public function __construct()
    {
        // Remove field groups from post types
        add_filter('acf/load_field_group', [$this, 'removePosttypeFieldgroups'], 10, 1);

        // Remove field groups from taxonomies
        add_filter('acf/load_field_group', [$this, 'removeTaxonomyFieldgroups'], 10, 1);
    }

    public function removePosttypeFieldgroups($field_group) {
        $groupsToRemove = [
            'group_56c33cf1470dc', // Display settings
            'group_646c5d26e3359', // Google Translate
            'group_56d83cff12bb3', // Navigation settings
            'group_6784bb5c51d70', // Post icon
            'group_64227d79a7f57', // Quick links
            'group_591c10ab88d77', // Feedback forwarding
        ];

        // Target this specific field group
        if (!in_array($field_group['key'], $groupsToRemove)) {
            return $field_group;
        }

        // Only on post type edit screens or during ACF AJAX loads
        if (!is_admin() && !(defined('DOING_AJAX') && DOING_AJAX)) {
            return $field_group;
        }

        $postType = '';

        // Priority sources for post type
        if (!empty($_REQUEST['post_type'])) {
            $postType = sanitize_text_field($_REQUEST['post_type']);
        } elseif (!empty($_GET['post_type'])) {
            $postType = sanitize_text_field($_GET['post_type']);
        } elseif (!empty($_GET['post'])) {
            $post_id = intval($_GET['post']);
            $postType = get_post_type($post_id) ?: '';
        } elseif (!empty($_REQUEST['post_id'])) {
            $raw = $_REQUEST['post_id'];
            if (is_string($raw) && strpos($raw, 'post_') === 0) {
                $post_id = intval(substr($raw, 5));
            } else {
                $post_id = intval($raw);
            }
            if ($post_id) {
                $postType = get_post_type($post_id) ?: '';
            }
        } elseif (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && !empty($screen->post_type)) {
                $postType = sanitize_text_field($screen->post_type);
            }
        }

        if (empty($postType)) {
            return $field_group;
        }

        // Disable for specific post type
        if ($postType === Posttype::NOTICE_POST_TYPE) {
            return false; // ← removes the field group completely
        }

        return $field_group;
    }

    /**
     * Remove advanced term settings ACF field group for specific taxonomy
     *
     * @param array $field_group
     * @return array|false
     */
    public function removeTaxonomyFieldgroups($field_group) {
        $groupsToRemove = [
            'group_63e6002cc129c', // Advanced term settings
        ];

        // Target this specific field group
        if (!in_array($field_group['key'], $groupsToRemove)) {
            return $field_group;
        }

        // Only on taxonomy edit screens
        if (!is_admin() || empty($_GET['taxonomy'])) {
            return $field_group;
        }

        $taxonomy = sanitize_text_field($_GET['taxonomy']);

        // Disable for specific taxonomy
        if ($taxonomy === Posttype::NOTICE_TAXONOMY) {
            return false; // ← removes the field group completely
        }

        return $field_group;
    }
}
