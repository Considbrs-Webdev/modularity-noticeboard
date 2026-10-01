<?php

namespace ModularityNoticeboard\Integration;

/** Register integration hooks without replacing WordPress core callbacks. */
final class Hooks
{
    public function register(): void
    {
        add_action('init', [Storage::class, 'install'], 20);
        add_action('rest_api_init', [new RestApi(), 'registerRoute']);
        add_action('rest_api_init', [new NovaPublicationEndpoint(), 'registerRoute'], 99);
        add_filter('application_password_is_api_request', [NovaPublicationEndpoint::class, 'applicationPasswordRequest']);

        $admin = new Admin();
        add_action('admin_menu', [$admin, 'menu']);
        add_action('admin_enqueue_scripts', [$admin, 'enqueueStyles']);
        add_action('admin_post_noticeboard_integrations', [$admin, 'save']);
    }
}
