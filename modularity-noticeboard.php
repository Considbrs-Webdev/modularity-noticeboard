<?php

/**
 * Plugin Name:       Modularity Noticeboard
 * Plugin URI:        https://github.com/considbrs-webdev/modularity-noticeboard.git
 * Description:       A Modularity module that implements a municipal digital noticeboard for publishing legally binding public notices—meeting summons, agendas, adjusted minutes and decisions—replacing the physical noticeboard, managing appeal deadlines under municipal law, and providing accessible, auditable, and integratable publication workflows.
 * Version: 1.0.0
 * Author:            Consid Borås AB
 * Author URI:        https://github.com/considbrs-webdev
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       modularity-noticeboard
 * Domain Path:       /languages
 */

// Protect agains direct file access
if (!defined('WPINC')) {
    die;
}

define('MODULARITY_NOTICEBOARD_PATH', plugin_dir_path(__FILE__));
define('MODULARITY_NOTICEBOARD_URL', plugins_url('', __FILE__));
define('MODULARITY_NOTICEBOARD_VIEW_PATH', MODULARITY_NOTICEBOARD_PATH . 'views/');
define('MODULARITY_NOTICEBOARD_MODULE_VIEW_PATH', plugin_dir_path(__FILE__) . 'source/php/Module/views');
define('MODULARITY_NOTICEBOARD_MODULE_PATH', MODULARITY_NOTICEBOARD_PATH . 'source/php/Module/');
    
add_action('init', function() {
    load_plugin_textdomain('modularity-noticeboard', false, plugin_basename(dirname(__FILE__)) . '/languages');
}); 

// Autoload from plugin
if (file_exists(MODULARITY_NOTICEBOARD_PATH . 'vendor/autoload.php')) {
    require_once MODULARITY_NOTICEBOARD_PATH . 'vendor/autoload.php';
}
require_once MODULARITY_NOTICEBOARD_PATH . 'Public.php';

// Acf auto import and export
add_action('acf/init', function () {
    $acfExportManager = new \AcfExportManager\AcfExportManager();
    $acfExportManager->setTextdomain('modularity-toc');
    $acfExportManager->setExportFolder(MODULARITY_NOTICEBOARD_PATH . 'source/php/AcfFields/');
    $acfExportManager->autoExport(array(
        'notice-general-settings' => 'group_69720e679fc2b',
        'notice-type-settings' => 'group_696798e2dc065',
        'notice-module-settings' => 'group_6967a7194079d',
        'notice-settings' => 'group_69679a7feea9d',
    ));
    $acfExportManager->import();
}); 

// Modularity 3.0 ready - ViewPath for Component library
add_filter('/Modularity/externalViewPath', function ($arr) {
    $arr['mod-noticeboard'] = MODULARITY_NOTICEBOARD_MODULE_VIEW_PATH;
    return $arr;
}, 10, 3);

// Register WP-CLI commands
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('noticeboard', \ModularityNoticeboard\CLI\ArchiveNoticesCommand::class);
}

// Start application
new ModularityNoticeboard\App();
