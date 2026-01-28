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
     * Get the post type slug from ACF options
     *
     * @return string|null
     */
    public static function getPostTypeSlug()
    {
        if (!function_exists('get_field')) {
            return null;
        }

        $postTypeSlug = get_field('post_type_slug', self::OPTION_PAGE_SLUG);

        if (empty($postTypeSlug)) {
            $postTypeSlug = 'notice';
        }

        return $postTypeSlug;
    }

    /**
     * Get the Noticeboard main page ID from ACF options
     * 
     * @return int|null
     */
    public static function getMainPageId(): ?int
    {
        if (!function_exists('get_field')) {
            return null;
        }

        $useCustomArchivePage = get_field('custom_archive_page', self::OPTION_PAGE_SLUG);

        if (!isset($useCustomArchivePage) || $useCustomArchivePage !== true) {
            return null;
        }

        $page = get_field('noticeboard_main_page', self::OPTION_PAGE_SLUG);

        if (!$page || !is_numeric($page)) {
            return null;
        }

        return intval($page);
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

        $page = self::getMainPageId();

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

    /**
     * Get the breadcrumb title from ACF options
     *
     * @return string
     */
    public static function getBreadcumbTitle(): string
    {
        if (!function_exists('get_field')) {
            return __('Notices', 'modularity-noticeboard');
        }

        $customTitle = get_field('breadcrumb_title', self::OPTION_PAGE_SLUG);

        if (empty($customTitle)) {
            return __('Notices', 'modularity-noticeboard');
        }

        return $customTitle;
    }

    /**
     * Get the archival action from ACF options
     *
     * @return string
     */
    public static function getArchivalAction(): string
    {
        if (!function_exists('get_field')) {
            return 'unpublish';
        }

        $action = get_field('archival_action', self::OPTION_PAGE_SLUG);

        if (empty($action)) {
            return 'unpublish';
        }

        return $action;
    }
}
