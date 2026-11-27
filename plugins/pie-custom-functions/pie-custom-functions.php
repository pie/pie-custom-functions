<?php
/**
 * Plugin Name: PIE Hosting Companion
 * Description: Required Functionality for PIE Hosting
 * Author: The team at PIE
 * Author URI: https://pie.co.de
 * Version: 1.7.0
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
register_activation_hook( __FILE__, function() {
    pie_custom_functions_init( true );
} );
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
 * Copies the pie/ directory and MU loader file to the MU plugins directory,
 * then records the current version. The directory is copied atomically via a
 * temp-and-rename strategy so the live directory is never partially updated.
 * The MU loader is copied only after the directory swap succeeds. If either
 * step fails the version option is not bumped, allowing the next request to
 * retry the full update. On the activation path failures call wp_die() to show
 * an error screen; on the auto-update path they log silently and return so
 * requests continue normally.
 *
 * @since 1.0.0
 * @param bool $is_activation True when called from the activation hook, false on auto-update.
 * @return void
 */
function pie_custom_functions_init( bool $is_activation = false ): void {

    if ( ! function_exists( 'get_plugin_data' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $local_mu_plugin_file      = plugin_dir_path( __FILE__ ) . 'pie-custom-functions-mu.php';
    $local_mu_plugin_directory = plugin_dir_path( __FILE__ ) . 'pie';

    // Ensure MU plugin directory is available.
    if ( ! defined( 'WPMU_PLUGIN_DIR' ) ) {
        error_log( '[PIE Custom Functions] WPMU_PLUGIN_DIR not defined - MU plugins not supported' );
        if ( $is_activation ) {
            wp_die(
                __( 'PIE Hosting Companion activation failed: MU plugins directory not available. Please contact your hosting provider.', 'pie-custom-functions' ),
                __( 'Plugin Activation Error', 'pie-custom-functions' ),
                array( 'back_link' => true )
            );
        }
        return;
    }

    $destination_mu_plugin_file      = WPMU_PLUGIN_DIR . '/pie-custom-functions-mu.php';
    $destination_mu_plugin_directory = WPMU_PLUGIN_DIR . '/pie';

    // Copy the pie/ directory atomically (temp copy then rename) so the live
    // directory is never in a partial state. The MU loader is not touched if this fails.
    if ( ! pie_custom_functions_copy_directory_atomic( $local_mu_plugin_directory, $destination_mu_plugin_directory ) ) {
        error_log( '[PIE Custom Functions] Failed to copy pie directory to: ' . $destination_mu_plugin_directory );
        if ( $is_activation ) {
            wp_die(
                __( 'PIE Hosting Companion activation failed: Could not copy pie directory. Please check file permissions.', 'pie-custom-functions' ),
                __( 'Plugin Activation Error', 'pie-custom-functions' ),
                array( 'back_link' => true )
            );
        }
        return;
    }

    // Write the MU loader to a temp file in the same directory, then rename atomically
    // over the live file. Same-directory guarantees the rename is on the same filesystem.
    // If this fails the version option is not bumped, so the next request retries the full update.
    $temp_mu_plugin_file = $destination_mu_plugin_file . '.new';

    if ( ! copy( $local_mu_plugin_file, $temp_mu_plugin_file ) ) {
        error_log( '[PIE Custom Functions] Failed to copy MU plugin file to: ' . $temp_mu_plugin_file );
        if ( $is_activation ) {
            wp_die(
                __( 'PIE Hosting Companion activation failed: Could not copy MU plugin file. Please check file permissions.', 'pie-custom-functions' ),
                __( 'Plugin Activation Error', 'pie-custom-functions' ),
                array( 'back_link' => true )
            );
        }
        return;
    }

    if ( ! rename( $temp_mu_plugin_file, $destination_mu_plugin_file ) ) {
        unlink( $temp_mu_plugin_file );
        error_log( '[PIE Custom Functions] Failed to promote MU plugin file to: ' . $destination_mu_plugin_file );
        if ( $is_activation ) {
            wp_die(
                __( 'PIE Hosting Companion activation failed: Could not install MU plugin file. Please check file permissions.', 'pie-custom-functions' ),
                __( 'Plugin Activation Error', 'pie-custom-functions' ),
                array( 'back_link' => true )
            );
        }
        return;
    }

    // pie/ swap and MU loader promotion both succeeded — safe to record the new version.
    update_option( 'pie_custom_functions_version', get_plugin_data( __FILE__ )['Version'] );
}

/**
 * Recursively copy a directory using native PHP filesystem functions.
 *
 * @since 1.5.1
 * @param string $source      Absolute path to the source directory.
 * @param string $destination Absolute path to the destination directory.
 * @return bool True on success, false on any failure.
 */
function pie_custom_functions_copy_directory_recursive( string $source, string $destination ): bool {
    if ( ! is_dir( $source ) ) {
        return false;
    }

    if ( ! is_dir( $destination ) && ! mkdir( $destination, 0755, true ) ) {
        return false;
    }

    $entries = scandir( $source );
    if ( false === $entries ) {
        return false;
    }

    foreach ( $entries as $entry ) {
        if ( '.' === $entry || '..' === $entry ) {
            continue;
        }

        $source_path      = $source . DIRECTORY_SEPARATOR . $entry;
        $destination_path = $destination . DIRECTORY_SEPARATOR . $entry;

        if ( is_dir( $source_path ) ) {
            if ( ! pie_custom_functions_copy_directory_recursive( $source_path, $destination_path ) ) {
                return false;
            }
        } elseif ( ! copy( $source_path, $destination_path ) ) {
            return false;
        }
    }

    return true;
}

/**
 * Copy a directory atomically by writing to a temporary location first.
 *
 * Copies $source to $destination-new, then renames the existing $destination
 * to $destination-old and promotes $destination-new to $destination. The live
 * directory is replaced in a single rename, so it is never partially updated.
 * Cleans up temp directories on any failure and attempts to restore the
 * previous directory if the promotion rename fails.
 *
 * @since 1.5.1
 * @param string $source      Absolute path to the source directory.
 * @param string $destination Absolute path to the destination directory.
 * @return bool True on success, false on any failure.
 */
function pie_custom_functions_copy_directory_atomic( string $source, string $destination ): bool {
    $temp = $destination . '-new';
    $old  = $destination . '-old';

    // Clean up any leftover temp directories from previous failed attempts.
    // If either cannot be fully removed the rename steps that follow would fail anyway.
    if ( is_dir( $temp ) && ! pie_custom_functions_delete_directory_recursive( $temp ) ) {
        return false;
    }

    if ( is_dir( $old ) && ! pie_custom_functions_delete_directory_recursive( $old ) ) {
        return false;
    }

    // Copy into the temporary location — live directory is untouched until success.
    if ( ! pie_custom_functions_copy_directory_recursive( $source, $temp ) ) {
        pie_custom_functions_delete_directory_recursive( $temp );
        return false;
    }

    // Move the current directory aside, then promote the new one.
    if ( is_dir( $destination ) && ! rename( $destination, $old ) ) {
        pie_custom_functions_delete_directory_recursive( $temp );
        return false;
    }

    if ( ! rename( $temp, $destination ) ) {
        // Promotion failed — attempt to restore the previous directory.
        if ( is_dir( $old ) && ! rename( $old, $destination ) ) {
            error_log( '[PIE Custom Functions] CRITICAL: promotion and rollback both failed. The live pie/ directory may be missing at: ' . $destination );
        }
        pie_custom_functions_delete_directory_recursive( $temp );
        return false;
    }

    // New directory is live — remove the old one.
    if ( is_dir( $old ) ) {
        pie_custom_functions_delete_directory_recursive( $old );
    }

    return true;
}

/**
 * Recursively delete a directory and all its contents.
 *
 * @since 1.5.1
 * @param string $path Absolute path to the directory to delete.
 * @return bool True on success, false if the path is not a directory, cannot be scanned, or any file or subdirectory cannot be deleted.
 */
function pie_custom_functions_delete_directory_recursive( string $path ): bool {
    if ( ! is_dir( $path ) ) {
        return false;
    }

    $entries = scandir( $path );
    if ( false === $entries ) {
        return false;
    }

    foreach ( $entries as $entry ) {
        if ( '.' === $entry || '..' === $entry ) {
            continue;
        }

        $entry_path = $path . DIRECTORY_SEPARATOR . $entry;

        if ( is_dir( $entry_path ) ) {
            if ( ! pie_custom_functions_delete_directory_recursive( $entry_path ) ) {
                return false;
            }
        } elseif ( ! unlink( $entry_path ) ) {
            return false;
        }
    }

    return rmdir( $path );
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