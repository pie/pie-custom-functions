<?php
/**
 * Plugin Name: Pie Custom Functions
 * Description: Custom functions for Pie Hosting
 * Author: Pie Hosting
 * Author URI: https://pie.co.de
 * Version: 1.3.0
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pie-custom-functions
 * Domain Path: /languages
 *  
 */

namespace PIE\CustomFunctions;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Load Composer autoloader
 *
 * @return void
 */
function load_composer(){
    require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

    PucFactory::buildUpdateChecker(
        'https://pie.github.io/pie-custom-functions/update.json',
        __FILE__,
        'pie-custom-functions'
    );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\load_composer' );

/**
 * After plugins have loaded check if the plugin has been updated
 * If it has been updated then run the init function
 * 
 * @return void
 */
function update_check(){
    if ( ! function_exists( 'get_plugin_data' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $current_version = get_option( 'pie_custom_functions_version' );
    $new_version     = get_plugin_data( __FILE__ )['Version'];

    if ( version_compare( $current_version, $new_version, '<' ) ) {
        update_option( 'pie_custom_functions_version', $new_version );
        install_mu_plugin();
    }
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\update_check' );

/**
 * Activation hook
 * Updates plugin version number in the options table
 * Installs the MU plugin
 * Adds the 'Pie Admin' role
 * Adds the 'Pie Admin' role to any existing user that has an email address ending in '@pie.co.de'
 *
 * @return void
 */
function on_activate_plugin() {
    if( ! function_exists( 'get_plugin_data' ) ){
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    update_option( 'pie_custom_functions_version', get_plugin_data( __FILE__ )['Version'] );
    install_mu_plugin();
}
register_activation_hook( __FILE__ , __NAMESPACE__ . '\on_activate_plugin' );

/**
 * Copy the MU plugin file to the mu-plugins directory
 * 
 * @todo if there is not a mu-plugins directory then create one
 *
 * @return void
 */
function install_mu_plugin(){
    $local_mu_plugin_file = plugin_dir_path( __FILE__ ) . 'pie-custom-functions-mu.php';

    // Set the path for the MU plugin file
    if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
        $mu_plugin_destination_file = WPMU_PLUGIN_DIR . '/pie-custom-functions-mu.php';
    }

    rename( $local_mu_plugin_file, $mu_plugin_destination_file );
}
