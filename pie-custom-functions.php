<?php
/**
 * Plugin Name: Pie Custom Functions
 * Description: Custom functions for Pie Hosting
 * Author: Pie Hosting
 * Author URI: https://pie.co.de
 * Version: 1.2.1
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

// Hookups
register_activation_hook(__FILE__ , __NAMESPACE__ . '\pie_custom_functions_init');
add_action('plugins_loaded', __NAMESPACE__ . '\pie_custom_functions_load_composer');
add_action('plugins_loaded', __NAMESPACE__ . '\update_check');

/**
 * Load Composer autoloader
 *
 * @return void
 */
function pie_custom_functions_load_composer(){
    require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

    $update_checker = PucFactory::buildUpdateChecker(
        'https://pie.github.io/pie-custom-functions/update.json',
        __FILE__,
        'pie-custom-functions'
    );
}

/**
 * This function handles copying the MU plugin file to the correct location and updating the version number
 * in the database.
 *
 * @return void
 */
function pie_custom_functions_init(){

    if(!function_exists('get_plugin_data')){
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    update_option('pie_custom_functions_version', get_plugin_data(__FILE__)['Version']);

    $local_mu_plugin_file = plugin_dir_path(__FILE__) . 'pie-custom-functions-mu.php';

    // Set the path for the MU plugin file

    if(defined('WPMU_PLUGIN_DIR')){
        $mu_plugin_destination_file = WPMU_PLUGIN_DIR . '/pie-custom-functions-mu.php';
    }


    rename($local_mu_plugin_file, $mu_plugin_destination_file);
}

/**
 * After plugins have loaded check if the plugin has been updated
 * If it has been updated then run the init function
 * 
 * @return void
 */
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

