<?php

namespace ModularityNoticeboard\Data;

use ModularityNoticeboard\Admin\Settings;
class Posttype {

    public const NOTICE_POST_TYPE = 'noticeboard_notice';
    public const NOTICE_TAXONOMY = 'noticeboard_notice_type';
    public const NOTICE_GROUP_TAXONOMY = 'notice_group';

    public function __construct()
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomy']);

        add_action('add_meta_boxes', [$this, 'remove_taxonomy_meta_box'], 11);

        add_filter('post_type_link', [$this, 'filter_post_permalink'], 10, 2);
        add_filter('the_content', [$this, 'append_protocol_link'], 20);
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
            'menu_name'             => _x('Noticeboard', 'Admin Menu name', 'modularity-noticeboard'),
            'name_admin_bar'        => _x('Notice', 'Add New on Toolbar', 'modularity-noticeboard'),
            'add_new'               => __('Add New', 'modularity-noticeboard'),
            'add_new_item'          => __('Add New Notice', 'modularity-noticeboard'),
            'new_item'              => __('New Notice', 'modularity-noticeboard'),
            'edit_item'             => __('Edit Notice', 'modularity-noticeboard'),
            'view_item'             => __('View Notice', 'modularity-noticeboard'),
            'all_items'             => __('All Notices', 'modularity-noticeboard'),
            'not_found'             => __('No notices found.', 'modularity-noticeboard'),
            'not_found_in_trash'    => __('No notices found in Trash.', 'modularity-noticeboard'),
            'archives'              => __('Notice archives', 'modularity-noticeboard'),
            'search_items'          => __('Search notices', 'modularity-noticeboard'),
        ];

        $args = [
            'labels'                => $labels,
            'public'                => true,
            'publicly_queryable'    => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'query_var'             => true,
            'rewrite'               => ['slug' => 'notice', 'with_front' => false],
            'capability_type'       => 'post',
            'has_archive'           => !Settings::useCustomArchivePage(),
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
            'name'                       => _x('Types', 'Taxonomy general name', 'modularity-noticeboard'),
            'singular_name'              => _x('Type', 'Taxonomy singular name', 'modularity-noticeboard'),
            'search_items'               => __('Search types', 'modularity-noticeboard'),
            'all_items'                  => __('All types', 'modularity-noticeboard'),
            'edit_item'                  => __('Edit type', 'modularity-noticeboard'),
            'update_item'                => __('Update type', 'modularity-noticeboard'),
            'add_new_item'               => __('Add new type', 'modularity-noticeboard'),
            'new_item_name'              => __('New type name', 'modularity-noticeboard'),
            'menu_name'                  => __('Types', 'modularity-noticeboard'),
        ];

        $args = [
            'hierarchical'          => false,
            'labels'                => $labels,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'query_var'             => true,
            'rewrite'               => ['slug' => 'notice-type'],
            'show_in_rest'          => true,
        ];

        register_taxonomy(self::NOTICE_TAXONOMY, [self::NOTICE_POST_TYPE], $args);

        $group_labels = [
            'name'                       => _x('Groups', 'Taxonomy general name', 'modularity-noticeboard'),
            'singular_name'              => _x('Group', 'Taxonomy singular name', 'modularity-noticeboard'),
            'search_items'               => __('Search groups', 'modularity-noticeboard'),
            'all_items'                  => __('All groups', 'modularity-noticeboard'),
            'edit_item'                  => __('Edit group', 'modularity-noticeboard'),
            'update_item'                => __('Update group', 'modularity-noticeboard'),
            'add_new_item'               => __('Add new group', 'modularity-noticeboard'),
            'new_item_name'              => __('New group name', 'modularity-noticeboard'),
            'menu_name'                  => __('Groups', 'modularity-noticeboard'),
        ];

        $group_args = [
            'hierarchical'          => false,
            'labels'                => $group_labels,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'query_var'             => true,
            'rewrite'               => ['slug' => 'notice-group'],
            'show_in_rest'          => true,
        ];

        register_taxonomy(self::NOTICE_GROUP_TAXONOMY, [self::NOTICE_POST_TYPE], $group_args);
    }

    /**
     * Remove the taxonomy meta box since ACF handles the term selection.
     */
    public function remove_taxonomy_meta_box()
    {
        remove_meta_box('tags' . 'div' . '-' . self::NOTICE_TAXONOMY, self::NOTICE_POST_TYPE, 'side');
        remove_meta_box('tags' . 'div' . '-' . self::NOTICE_GROUP_TAXONOMY, self::NOTICE_POST_TYPE, 'side');
    }

    /**
     * Filter the permalink for notices.
     * Return the regular permalink if the post has content, otherwise return the pdf file field.
     */
    public function filter_post_permalink($post_link, $post)
    {
        if (is_numeric($post)) {
            $post = get_post($post);
        }

        if (! $post || $post->post_type !== self::NOTICE_POST_TYPE) {
            return $post_link;
        }

        if (isset($post->post_content) && trim((string) $post->post_content) !== '') {
            return $post_link;
        }

        $pdf = function_exists('get_field') ? get_field('pdf_file', $post->ID) : '';

        if (! empty($pdf)) {
            return $pdf;
        }

        return $post_link;
    }

    /**
     * Append a protocol link to the end of the content for notices that have content.
     */
    public function append_protocol_link($content)
    {
        if (is_admin()) {
            return $content;
        }

        global $post;

        if (! $post || $post->post_type !== self::NOTICE_POST_TYPE) {
            return $content;
        }

        if (! isset($post->post_content) || trim((string) $post->post_content) === '') {
            return $content;
        }

        $pdf = function_exists('get_field') ? get_field('pdf_file', $post->ID) : '';

        if (empty($pdf)) {
            return $content;
        }

        $link_text = __('Link to protocol (PDF)', 'modularity-noticeboard');

        $append = sprintf(
            '<p class="notice-protocol"><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
            esc_url($pdf),
            esc_html($link_text)
        );

        return $content . $append;
    }

}