<?php
/**
 * Plugin Name: PIE Hosting Companion
 * Description: Required Functionality for PIE Hosting
 * Author: The team at PIE
 * Author URI: https://pie.co.de
 * Version: 1.3.3
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

// Hookups
register_activation_hook( __FILE__ , __NAMESPACE__ . '\pie_custom_functions_init' );
add_action( 'plugins_loaded', __NAMESPACE__ . '\pie_custom_functions_load_composer' );
add_action( 'plugins_loaded', __NAMESPACE__ . '\update_check' );
add_filter( 'all_plugins', __NAMESPACE__ . '\hide_pie_custom_functions_mu_plugin_from_plugins_list' );

/**
 * Load Composer autoloader
 *
 * @return void
 */
function pie_custom_functions_load_composer(){
    require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

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

    if( ! function_exists( 'get_plugin_data' ) ){
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    update_option( 'pie_custom_functions_version', get_plugin_data( __FILE__ )['Version'] );

    $local_mu_plugin_file = plugin_dir_path( __FILE__ ) . 'pie-custom-functions-mu.php';
    $local_mu_plugin_directory = plugin_dir_path( __FILE__ ) . 'pie';

    // Set the paths for the MU plugin file and pie directory

    if( defined( 'WPMU_PLUGIN_DIR' ) ){
        $destination_mu_plugin_file = WPMU_PLUGIN_DIR . '/pie-custom-functions-mu.php';
        $destination_mu_plugin_directory = WPMU_PLUGIN_DIR . '/pie';
    }

    // Copy the MU plugin file
    copy( $local_mu_plugin_file, $destination_mu_plugin_file );
    
    // Copy the pie directory
    pie_copy_directory_recursive( $local_mu_plugin_directory, $destination_mu_plugin_directory );
}

/**
 * Recursively copy a directory and all its contents
 *
 * @param string $source The source directory to copy from
 * @param string $destination The destination directory to copy to
 * @return bool True on success, false on failure
 */
function pie_copy_directory_recursive( $source, $destination ) {
    if ( ! is_dir( $source ) ) {
        return false;
    }
    
    if ( ! mkdir( $destination, 0755, true ) && ! is_dir( $destination ) ) {
        return false;
    }
    
    $files = array_diff( scandir( $source ), array( '.', '..' ) );
    
    foreach ( $files as $file ) {
        $source_path = $source . '/' . $file;
        $dest_path = $destination . '/' . $file;
        
        if ( is_dir( $source_path ) ) {
            pie_copy_directory_recursive( $source_path, $dest_path );
        } else {
            copy( $source_path, $dest_path );
        }
    }
    
    return true;
}

/**
 * After plugins have loaded check if the plugin has been updated
 * If it has been updated then run the init function
 * 
 * @return void
 */
function update_check(){
    
    if( ! function_exists( 'get_plugin_data' ) ){
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $current_version = get_option( 'pie_custom_functions_version' );
    $new_version = get_plugin_data( __FILE__ )['Version'];

    if( version_compare( $current_version, $new_version, '<' ) ){
        pie_custom_functions_init();
    }
}

/**
 * Hide the PIE Custom Functions MU plugin from the plugins list for all users
 * 
 * @param array $plugins
 * @return array $plugins
 */
function hide_pie_custom_functions_mu_plugin_from_plugins_list( $plugins ) {
    // Hide the MU plugin file if it shows up in the list
    if ( isset( $plugins['pie-custom-functions/pie-custom-functions-mu.php'] ) ) {
        unset( $plugins['pie-custom-functions/pie-custom-functions-mu.php'] );
    }
    return $plugins;
}