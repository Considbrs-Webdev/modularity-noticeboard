<?php

namespace ModularityNoticeboard\Data;

class Posttype {

    public const NOTICE_POST_TYPE = 'noticeboard_notice';
    public const NOTICE_TAXONOMY = 'noticeboard_notice_type';

    public function __construct()
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomy']);
        add_action('add_meta_boxes', [$this, 'remove_taxonomy_meta_box'], 11);
    }

    /**
     * Register the Notices post type.
     * Labels are translatable using the plugin textdomain.
     */
    public function register_post_type()
    {
        $labels = [
            'name'                  => _x('Notices', 'Post type general name', 'modularity-noticeboard'),
            'singular_name'         => _x('Notice', 'Post type singular name', 'modularity-noticeboard'),
            'menu_name'             => _x('Notices', 'Admin Menu name', 'modularity-noticeboard'),
            'name_admin_bar'        => _x('Notice', 'Add New on Toolbar', 'modularity-noticeboard'),
            'add_new'               => __('Add New', 'modularity-noticeboard'),
            'add_new_item'          => __('Add New Notice', 'modularity-noticeboard'),
            'new_item'              => __('New Notice', 'modularity-noticeboard'),
            'edit_item'             => __('Edit Notice', 'modularity-noticeboard'),
            'view_item'             => __('View Notice', 'modularity-noticeboard'),
            'all_items'             => __('All Notices', 'modularity-noticeboard'),
            'search_items'          => __('Search Notices', 'modularity-noticeboard'),
            'parent_item_colon'     => __('Parent Notices:', 'modularity-noticeboard'),
            'not_found'             => __('No notices found.', 'modularity-noticeboard'),
            'not_found_in_trash'    => __('No notices found in Trash.', 'modularity-noticeboard'),
            'archives'              => __('Notice archives', 'modularity-noticeboard'),
        ];

        $args = [
            'labels'                => $labels,
            'public'                => true,
            'publicly_queryable'    => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'query_var'             => true,
            'rewrite'               => ['slug' => 'notice'],
            'capability_type'       => 'post',
            'has_archive'           => true,
            'hierarchical'          => false,
            'menu_position'         => 20,
            'supports'              => ['title', 'editor'],
            'show_in_rest'          => true,
            'menu_icon'             => 'dashicons-admin-post',
        ];

        register_post_type(self::NOTICE_POST_TYPE, $args);
    }

    /**
     * Register the notice_type taxonomy for Notices.
     * Hierarchical (category-like) and translatable labels.
     */
    public function register_taxonomy()
    {
        $labels = [
            'name'                       => _x('Notice types', 'Taxonomy general name', 'modularity-noticeboard'),
            'singular_name'              => _x('Notice type', 'Taxonomy singular name', 'modularity-noticeboard'),
            'search_items'               => __('Search notice types', 'modularity-noticeboard'),
            'all_items'                  => __('All notice types', 'modularity-noticeboard'),
            'parent_item'                => __('Parent notice type', 'modularity-noticeboard'),
            'parent_item_colon'          => __('Parent notice type:', 'modularity-noticeboard'),
            'edit_item'                  => __('Edit notice type', 'modularity-noticeboard'),
            'update_item'                => __('Update notice type', 'modularity-noticeboard'),
            'add_new_item'               => __('Add new notice type', 'modularity-noticeboard'),
            'new_item_name'              => __('New notice type name', 'modularity-noticeboard'),
            'menu_name'                  => __('Notice types', 'modularity-noticeboard'),
        ];

        $args = [
            'hierarchical'          => true,
            'labels'                => $labels,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'query_var'             => true,
            'rewrite'               => ['slug' => 'notice-type'],
            'show_in_rest'          => true,
        ];

        register_taxonomy(self::NOTICE_TAXONOMY, [self::NOTICE_POST_TYPE], $args);
    }

    /**
     * Remove the taxonomy meta box since ACF handles the term selection.
     */
    public function remove_taxonomy_meta_box()
    {
        remove_meta_box(self::NOTICE_TAXONOMY . 'div', self::NOTICE_POST_TYPE, 'side');
    }

}