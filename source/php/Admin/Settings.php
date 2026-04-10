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
     * Page used for breadcrumb path (ancestors + label).
     * Prefer the plugin ACF field; if empty, use Municipio/WordPress
     * `page_for_{post_type}` (the page chosen for this CPT under Reading-style settings).
     *
     * @return int|null
     */
    public static function getNoticeboardLandingPageId(): ?int
    {
        if (function_exists('get_field')) {
            $page = get_field('noticeboard_main_page', self::OPTION_PAGE_SLUG);
            if ($page && is_numeric($page)) {
                $id = (int) $page;
                if ($id > 0 && get_post_status($id) !== false) {
                    return $id;
                }
            }
        }

        return self::getLandingPageIdFromReadingOption();
    }

    /**
     * @return int|null
     */
    public static function getLandingPageIdFromReadingOption(): ?int
    {
        $pageId = get_option('page_for_' . Posttype::NOTICE_POST_TYPE);

        if (!is_numeric($pageId)) {
            return null;
        }

        $id = (int) $pageId;

        return $id > 0 && get_post_status($id) !== false ? $id : null;
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
