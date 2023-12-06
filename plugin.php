<?php

/**
 * Plugin Name: Pie Custom Functions
 * Description: Custom functions for Pie Hosting
 * Author: Pie Hosting
 * Author URI: https://pie.co.de
 * Version: 1.0.0
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pie-custom-functions
 * Domain Path: /languages
 *  
 */

namespace PIE\CustomFunctions;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load Composer autoloader
 */
add_action('plugins_loaded', __NAMESPACE__ . '\pie_custom_functions_load_composer');
function pie_custom_functions_load_composer(){
    require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

    $update_checker = PucFactory::buildUpdateChecker(
        'https://pie.github.io/pie-custom-functions/update.json',
        __FILE__,
        'pie-custom-functions'
    );
}

register_activation_hook(__FILE__ , __NAMESPACE__ . '\pie_custom_functions_init');

function pie_custom_functions_init(){

    if(!function_exists('get_plugin_data')){
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    update_option('pie_custom_functions_version', get_plugin_data(__FILE__)['Version']);

    $local_mu_plugin_file = plugin_dir_path(__FILE__) . 'pie_mu_custom_functions.php';

    // Set the path for the MU plugin file

    if(defined('WPMU_PLUGIN_DIR')){
        $mu_plugin_destination_file = WPMU_PLUGIN_DIR . '/pie_mu_custom_functions.php';
    }


    rename($local_mu_plugin_file, $mu_plugin_destination_file);
}

add_action('plugins_loaded', __NAMESPACE__ . '\update_check');

function update_check(){
    
    if(!function_exists('get_plugin_data')){
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    $current_version = get_option('pie_custom_functions_version');
    $new_version = get_plugin_data(__FILE__)['Version'];

    if(version_compare($current_version, $new_version, '<')){
        pie_custom_functions_init();
    }
}


