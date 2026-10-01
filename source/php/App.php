<?php

namespace ModularityNoticeboard;

use ModularityNoticeboard\Data\Posttype;
use ModularityNoticeboard\Helper\CacheBust;

class App
{
    public function __construct()
    {
        // Register module
        add_action('init', array($this, 'registerModule'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueueStyles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueueScripts'));

        // Register post types
        new Data\Posttype();

        // Shared external publication integrations (schema upgrades also cover plugin updates).
        add_action('init', [Integration\Storage::class, 'install'], 20);
        add_action('rest_api_init', [new Integration\RestApi(), 'registerRoute']);
        add_action('rest_api_init', [new Integration\NovaPublicationEndpoint(), 'registerRoute'], 99);
        new Integration\Admin();
        remove_action('publish_future_post', 'check_and_publish_future_post', 10);
        add_action('publish_future_post', [Integration\NoticeLifecycle::class, 'scheduledPublish'], 10);
        add_filter('application_password_is_api_request', [Integration\NovaPublicationEndpoint::class, 'applicationPasswordRequest']);

        // Template data
        new Data\Template();

        // Municipio view paths (for single-noticeboard_notice.blade.php)
        add_filter('Municipio/viewPaths', array($this, 'addViewPaths'), 999);

        // Admin settings
        new Admin\Settings();

        // ACF customisation
        new Admin\ACF();

        // ACF customisation
        new Frontend\Breadcrumbs();
    }

    /**
     * Enqueue styles
     * @return void
     */
    public function enqueueStyles()
    {
        $styleFile = CacheBust::name('css/modularity-noticeboard.css');

        if ($styleFile) {
            wp_enqueue_style(
                'modularity-noticeboard',
                MODULARITY_NOTICEBOARD_URL . '/assets/dist/' . $styleFile,
                array(),
                null
            );
        }
    }

    /**
     * Enqueue scripts
     * @return void
     */
    public function enqueueScripts()
    {
        /* $scriptFile = CacheBust::name('js/modularity-noticeboard.js');

        if ($scriptFile) {
            wp_enqueue_script(
                'modularity-noticeboard',
                MODULARITY_NOTICEBOARD_URL . '/assets/dist/' . $scriptFile,
                array(),
                null,
                true
            );
        } */
    }

    /**
     * Register the module
     * @return void
     */
    public function registerModule()
    {
        if (function_exists('modularity_register_module')) {
            modularity_register_module(
                MODULARITY_NOTICEBOARD_MODULE_PATH,
                'Noticeboard'
            );
        }
    }

    /**
     * Add plugin view paths to Municipio for custom templates (single).
     *
     * @param array<int, string> $paths The existing view paths
     * @return array<int, string>
     */
    public function addViewPaths(array $paths): array
    {
        if (is_singular(Posttype::NOTICE_POST_TYPE)) {
            $paths[] = MODULARITY_NOTICEBOARD_PATH . 'views';
        }

        return $paths;
    }
}
