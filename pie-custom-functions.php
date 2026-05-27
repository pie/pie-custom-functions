<?php
/**
 * Plugin Name: PIE Hosting Companion
 * Description: Required Functionality for PIE Hosting
 * Author: The team at PIE
 * Author URI: https://pie.co.de
 * Version: 1.5.0
 * Requires PHP: 8.0
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
 * Load Composer autoloader and initialize update checker
 * 
 * Sets up the Composer autoloader and configures the plugin update checker
 * to monitor for new versions from the PIE GitHub repository.
 *
 * @since 1.0.0
 * @return void
 */
function pie_custom_functions_load_composer(): void {
    require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

    $update_checker = PucFactory::buildUpdateChecker(
        'https://pie.github.io/pie-custom-functions/update.json',
        __FILE__,
        'pie-custom-functions'
    );
}

/**
 * Initialize and activate the PIE Custom Functions plugin
 * 
 * Handles copying the MU plugin file and pie directory to the correct locations
 * during plugin activation. Updates the version number in the database and
 * performs comprehensive error handling with cleanup on failure.
 *
 * @since 1.0.0
 * @throws WP_Error If file operations fail
 * @return void
 */
function pie_custom_functions_init(): void {

    if ( ! function_exists( 'get_plugin_data' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    update_option( 'pie_custom_functions_version', get_plugin_data( __FILE__ )['Version'] );

    $local_mu_plugin_file = plugin_dir_path( __FILE__ ) . 'pie-custom-functions-mu.php';
    $local_mu_plugin_directory = plugin_dir_path( __FILE__ ) . 'pie';

    // Ensure MU plugin directory is available
    if ( ! defined( 'WPMU_PLUGIN_DIR' ) ) {
        error_log( '[PIE Custom Functions] WPMU_PLUGIN_DIR not defined - MU plugins not supported' );
        wp_die( 
            __( 'PIE Hosting Companion activation failed: MU plugins directory not available. Please contact your hosting provider.', 'pie-custom-functions' ),
            __( 'Plugin Activation Error', 'pie-custom-functions' ),
            array( 'back_link' => true )
        );
    }

    // Set the paths for the MU plugin file and pie directory
    $destination_mu_plugin_file = WPMU_PLUGIN_DIR . '/pie-custom-functions-mu.php';
    $destination_mu_plugin_directory = WPMU_PLUGIN_DIR . '/pie';

    // Copy the MU plugin file (overwrites existing)
    if ( ! copy( $local_mu_plugin_file, $destination_mu_plugin_file ) ) {
        error_log( '[PIE Custom Functions] Failed to copy MU plugin file to: ' . $destination_mu_plugin_file );
        wp_die( 
            __( 'PIE Hosting Companion activation failed: Could not copy MU plugin file. Please check file permissions.', 'pie-custom-functions' ),
            __( 'Plugin Activation Error', 'pie-custom-functions' ),
            array( 'back_link' => true )
        );
    }
    
    // Copy the pie directory (WordPress core function handles overwriting)
    if ( ! function_exists( 'copy_dir' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
    }
    
    $copy_result = copy_dir( $local_mu_plugin_directory, $destination_mu_plugin_directory );
    if ( is_wp_error( $copy_result ) || ! $copy_result ) {
        // Clean up the MU plugin file if directory copy failed
        if ( file_exists( $destination_mu_plugin_file ) ) {
            unlink( $destination_mu_plugin_file );
        }
        
        $error_message = is_wp_error( $copy_result ) ? $copy_result->get_error_message() : 'Unknown error';
        error_log( '[PIE Custom Functions] Failed to copy pie directory: ' . $error_message );
        wp_die( 
            __( 'PIE Hosting Companion activation failed: Could not copy pie directory. Please check file permissions.', 'pie-custom-functions' ) . ' ' . $error_message,
            __( 'Plugin Activation Error', 'pie-custom-functions' ),
            array( 'back_link' => true )
        );
    }
}

/**
 * Check for plugin updates and reinitialize if necessary
 * 
 * Compares the stored version number with the current plugin version.
 * If an update is detected, triggers the initialization process to
 * ensure MU plugin files are updated.
 * 
 * @since 1.0.0
 * @return void
 */
function update_check(): void {
    
    if ( ! function_exists( 'get_plugin_data' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $current_version = get_option( 'pie_custom_functions_version' );
    $new_version = get_plugin_data( __FILE__ )['Version'];

    if ( version_compare( $current_version, $new_version, '<' ) ) {
        pie_custom_functions_init();
    }
}

/**
 * Hide the PIE Custom Functions MU plugin from the plugins list
 * 
 * Removes the MU plugin from the plugins page to prevent user confusion
 * and accidental activation. The MU plugin should remain hidden from
 * all users regardless of their role.
 * 
 * @since 1.0.0
 * @param array $plugins Array of all installed plugins
 * @return array Modified plugins array with MU plugin removed
 */
function hide_pie_custom_functions_mu_plugin_from_plugins_list( array $plugins ): array {
    // Hide the MU plugin file if it shows up in the list
    if ( isset( $plugins['pie-custom-functions/pie-custom-functions-mu.php'] ) ) {
        unset( $plugins['pie-custom-functions/pie-custom-functions-mu.php'] );
    }
    return $plugins;
}