<?php
// Run only against a disposable WordPress installation with nbtest_ prefixed tables.
$root = getenv('NOTICEBOARD_TEST_WP_ROOT');
if (!$root || !is_file($root . '/wp-load.php')) {
    fwrite(STDERR, "Set NOTICEBOARD_TEST_WP_ROOT to an isolated WordPress installation.\n");
    exit(1);
}
if (!function_exists('get_option')) {
    require_once $root . '/wp-load.php';
}
require_once ABSPATH . 'wp-admin/includes/template.php';
global $wpdb;
if (strpos($wpdb->prefix, 'nbtest_') !== 0) {
    fwrite(STDERR, "Refusing to run: test table prefix must start with nbtest_.\n");
    exit(1);
}
$plugin = dirname(__DIR__);
spl_autoload_register(static function ($class) use ($plugin): void {
    $prefix = 'ModularityNoticeboard\\';
    if (strpos($class, $prefix) === 0) {
        $path = $plugin . '/source/php/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
});
$testAcfPath = getenv('NOTICEBOARD_TEST_ACF_PLUGIN');
if ($testAcfPath) {
    require_once $testAcfPath;
    if (!function_exists('update_field')) {
        fwrite(STDERR, "Load ACF at normal WordPress bootstrap time before testing.\n");
        exit(1);
    }
    require_once $plugin . '/source/php/AcfFields/php/notice-settings.php';
}
(new ModularityNoticeboard\Data\Posttype())->register_post_type();
(new ModularityNoticeboard\Data\Posttype())->register_taxonomy();
ModularityNoticeboard\Integration\Storage::install();
update_option('timezone_string', 'Europe/Stockholm');
add_action('rest_api_init', [new ModularityNoticeboard\Integration\RestApi(), 'registerRoute']);
add_action('rest_api_init', [new ModularityNoticeboard\Integration\NovaPublicationEndpoint(), 'registerRoute'], 99);
remove_action('publish_future_post', 'check_and_publish_future_post', 10);
add_action('publish_future_post', [ModularityNoticeboard\Integration\NoticeLifecycle::class, 'scheduledPublish'], 10);
add_filter('application_password_is_api_request', [ModularityNoticeboard\Integration\NovaPublicationEndpoint::class, 'applicationPasswordRequest']);
