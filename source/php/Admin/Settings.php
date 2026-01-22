<?php

namespace ModularityNoticeboard\Admin;

use ModularityNoticeboard\Data\Posttype;

class Settings
{
    public const OPTION_PAGE_SLUG = 'modularity_noticeboard_settings';

    public function __construct()
    {
        add_action('acf/init', array($this, 'registerSettingsPage'));
    }

    /**
     * Register ACF settings page under the Noticeboard post type menu
     */
    public function registerSettingsPage()
    {
        if (!function_exists('acf_add_options_page')) {
            return;
        }

        acf_add_options_sub_page(array(
            'page_title'  => 'Noticeboard Settings',
            'menu_title'  => 'Settings',
            'parent_slug' => 'edit.php?post_type=noticeboard_notice',
            'post_id'     => self::OPTION_PAGE_SLUG,
            'capability'  => 'edit_posts',
        ));
    }

    /**
     * Get the Noticeboard main page as a WP_Post from ACF options
     *
     * If the stored value is an ID (int or numeric string) this will return
     * the corresponding `WP_Post`. If already a post object it will be
     * returned as-is. Returns null when not set or when post cannot be found.
     *
     * @return \WP_Post|null
     */
    public static function getMainPageUrl()
    {
        if (!function_exists('get_field')) {
            return null;
        }

        $useCustomArchivePage = get_field('custom_archive_page', self::OPTION_PAGE_SLUG);

        if (!isset($useCustomArchivePage) || $useCustomArchivePage !== true) {
            return get_post_type_archive_link(Posttype::NOTICE_POST_TYPE);
        }

        $page = get_field('noticeboard_main_page', self::OPTION_PAGE_SLUG);

        if (!$page || !is_numeric($page)) {
            return get_post_type_archive_link(Posttype::NOTICE_POST_TYPE);
        }

        $post = get_post(intval($page));
        
        return get_permalink($post) ?: get_post_type_archive_link(Posttype::NOTICE_POST_TYPE);
    }

    /**
     * Check if a custom archive page is set to be used
     *
     * @return bool
     */
    public static function useCustomArchivePage(): bool
    {
        if (!function_exists('get_field')) {
            return false;
        }

        $useCustomArchivePage = get_field('custom_archive_page', self::OPTION_PAGE_SLUG);

        return isset($useCustomArchivePage) && $useCustomArchivePage === true;
    }
}
