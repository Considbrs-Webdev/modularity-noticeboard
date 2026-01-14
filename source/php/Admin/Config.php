<?php

namespace ModularityNoticeboard\Admin;

use ModularityNoticeboard\Data\Posttype;

class Config
{
    public function __construct()
    {
        // Filter misc publishing actions output for the notice post type (server-side)
        /* add_action('post_submitbox_misc_actions', [$this, 'startBuffer'], 1);
        add_action('post_submitbox_misc_actions', [$this, 'endBuffer'], 999);

        // Remove unwanted meta values on save
        add_action('save_post', [$this, 'saveCleanup'], 10, 3); */
    }

    /**
     * Enqueue a small inline script to remove UI elements from the misc publishing box
     * Only runs on the notice post type edit screens.
     */
    /**
     * Start output buffering for the publish box so we can strip specific sections server-side.
     */
    public function startBuffer()
    {
        if (!is_admin()) {
            return;
        }

        global $post;

        if (empty($post) || empty($post->post_type)) {
            return;
        }

        if ($post->post_type !== Posttype::NOTICE_POST_TYPE) {
            return;
        }

        ob_start();
    }

    /**
     * End buffering, strip unwanted sections and echo cleaned output.
     */
    public function endBuffer()
    {
        if (!is_admin()) {
            return;
        }

        global $post;

        if (empty($post) || empty($post->post_type)) {
            return;
        }

        if ($post->post_type !== Posttype::NOTICE_POST_TYPE) {
            return;
        }

        $output = (string) ob_get_clean();

        // Remove misc-pub-sticky and unpublish-pub-section blocks
        $pattern = '/<div[^>]*class=["\']([^"\']*\b(?:misc-pub-sticky|unpublish-pub-section)\b[^"\']*)["\'][\s\S]*?<\/div>\s*/i';
        $clean = preg_replace($pattern, '', $output);

        echo $clean;
    }

    /**
     * Remove unwanted meta values so they are not saved for notice posts.
     * Also cleans up $_POST to avoid other save handlers picking them up.
     *
     * @param int $post_id
     * @param \WP_Post $post
     * @param bool $update
     */
    public function saveCleanup($post_id, $post, $update)
    {
        // Only target the notice post type
        if (empty($post) || $post->post_type !== Posttype::NOTICE_POST_TYPE) {
            return;
        }

        // Bail on autosave and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        $metaKeys = [
            // Sticky checkbox input name
            'sticky_post_noticeboard_notice',

            // Possible nonce field used for sticky handling
            '_municipio_sticky_post_nonce',

            // Unpublish fields
            'unpublish-mm',
            'unpublish-jj',
            'unpublish-aa',
            'unpublish-hh',
            'unpublish-mn',
            'unpublish-active',
            'unpublish-action',
        ];

        foreach ($metaKeys as $key) {
            // Remove any stored post meta with this key
            delete_post_meta($post_id, $key);

            // Prevent other save handlers from processing these values
            if (isset($_POST[$key])) {
                unset($_POST[$key]);
            }
        }
    }
}
