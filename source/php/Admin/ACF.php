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
        
        // Admin scripts for auto-filling unarchive date
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
        add_action('wp_ajax_modularity_noticeboard_get_term_archiving', [$this, 'ajax_get_term_archiving']);
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
     * Enqueue admin JS for notice editing screens
     */
    public function enqueueAdminScripts()
    {
        if (!is_admin()) {
            return;
        }

        if (!function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || empty($screen->post_type)) {
            return;
        }

        if ($screen->post_type !== Posttype::NOTICE_POST_TYPE) {
            return;
        }

        $handle = 'modularity-noticeboard-admin-archiving';
        $src = MODULARITY_NOTICEBOARD_URL . '/source/js/admin-archiving.js';
        $deps = ['jquery', 'acf-input'];
        $ver = file_exists(MODULARITY_NOTICEBOARD_PATH . 'source/js/admin-archiving.js') ? filemtime(MODULARITY_NOTICEBOARD_PATH . 'source/js/admin-archiving.js') : false;
        wp_enqueue_script($handle, $src, $deps, $ver, true);
        wp_localize_script($handle, 'modularityNoticeboard', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('modularity_noticeboard_archiving'),
        ]);
    }

    /**
     * AJAX endpoint to return archiving settings for a taxonomy term
     */
    public function ajax_get_term_archiving()
    {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('forbidden', 403);
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'modularity_noticeboard_archiving')) {
            wp_send_json_error('invalid_nonce', 403);
        }

        $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
        if (!$term_id) {
            wp_send_json_error('invalid_term', 400);
        }

        $taxonomy = Posttype::NOTICE_TAXONOMY;

        $automatic = get_field('automatic_archiving', $taxonomy . '_' . $term_id);
        $days = intval(get_field('archiving_days', $taxonomy . '_' . $term_id));

        $unarchive_date = null;
        if ($automatic && $days > 0) {
            $ts = current_time('timestamp');
            $unarchive_date = date('Y-m-d', $ts + ($days * DAY_IN_SECONDS));
        }

        wp_send_json_success([
            'automatic' => (bool) $automatic,
            'days' => $days,
            'unarchive_date' => $unarchive_date,
        ]);
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
