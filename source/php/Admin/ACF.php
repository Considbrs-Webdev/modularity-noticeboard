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
        ];

        // Target this specific field group
        if (!in_array($field_group['key'], $groupsToRemove)) {
            return $field_group;
        }

        // Only on post type edit screens
        if (!is_admin() || empty($_GET['post_type'])) {
            return $field_group;
        }

        $postType = sanitize_text_field($_GET['post_type']);

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
