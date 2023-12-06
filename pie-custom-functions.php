<?php

/**
 * Plugin Name: Pie Custom Functions
 * Description: Custom functions for Pie Hosting
 * Author: Pie Hosting
 * Author URI: https://pie.co.de
 * Version: 2.0.0
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

    $mu_plugin_content = file_get_contents(plugin_dir_path(__FILE__) . 'pie_mu_custom_functions.php');

    // Set the path for the MU plugin file

    if(defined('WPMU_PLUGIN_DIR')){
        $mu_plugin_path = WPMU_PLUGIN_DIR . '/pie_mu_custom_functions.php';
    }


    // Check if the MU plugin file already exists
    if (!file_exists($mu_plugin_path)) {
        // Create the MU plugin file
        file_put_contents($mu_plugin_path, $mu_plugin_content);
    }
    
    unlink(plugin_dir_path(__FILE__) . 'pie_mu_custom_functions.php');
}


