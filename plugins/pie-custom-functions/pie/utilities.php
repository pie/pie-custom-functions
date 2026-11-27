<?php

namespace Pie\Utilities;

use function Pie\CustomFunctionsMUPlugin\is_pie_admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugins to hide from non-Pie admin users.
 *
 * @var array<string, string[]>
 */
$GLOBALS['pie_hidden_plugins'] = array();

/**
 * Hide a plugin from the plugins page and optionally the admin sidebar.
 *
 * @param string   $plugin_file Plugin basename, e.g. 'my-plugin/my-plugin.php'.
 * @param string[] $menu_slugs  Admin menu page slugs to remove from the sidebar.
 */
function pie_hide_plugin( string $plugin_file, array $menu_slugs = array() ): void {
	$GLOBALS['pie_hidden_plugins'][ $plugin_file ] = $menu_slugs;
}

/**
 * Remove hidden plugins from the plugins page for non-Pie admin users.
 *
 * @param array<string, array<string, mixed>> $plugins Plugins list.
 * @return array<string, array<string, mixed>>
 */
function pie_filter_hidden_plugins( array $plugins ): array {
	if ( is_pie_admin() ) {
		return $plugins;
	}

	foreach ( array_keys( $GLOBALS['pie_hidden_plugins'] ) as $plugin_file ) {
		unset( $plugins[ $plugin_file ] );
	}

	return $plugins;
}

/**
 * Remove hidden plugin menu pages for non-Pie admin users.
 */
function pie_remove_hidden_plugin_menu_pages(): void {
	if ( is_pie_admin() ) {
		return;
	}

	foreach ( $GLOBALS['pie_hidden_plugins'] as $menu_slugs ) {
		foreach ( $menu_slugs as $slug ) {
			remove_menu_page( $slug );
		}
	}
}

/**
 * Register the hidden plugins filter on the plugins admin screen only.
 *
 * Restricting to load-plugins.php prevents the filter from affecting non-UI
 * contexts such as background update checks or the REST API.
 */
function pie_register_hidden_plugins_filter(): void {
	add_filter( 'all_plugins', __NAMESPACE__ . '\\pie_filter_hidden_plugins' );
}
add_action( 'load-plugins.php', __NAMESPACE__ . '\\pie_register_hidden_plugins_filter' );
add_action( 'load-plugins-network.php', __NAMESPACE__ . '\\pie_register_hidden_plugins_filter' );
/**
 * Block direct URL access to hidden plugin admin pages for non-Pie admin users.
 */
function pie_block_hidden_plugin_pages(): void {
	if ( is_pie_admin() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$current_page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';

	if ( ! $current_page ) {
		return;
	}

	foreach ( $GLOBALS['pie_hidden_plugins'] as $menu_slugs ) {
		if ( in_array( $current_page, $menu_slugs, true ) ) {
			wp_die(
				esc_html__( 'You do not have sufficient permissions to access this page.', 'pie-custom-functions' ),
				'',
				array( 'response' => 403 )
			);
		}
	}
}
add_action( 'admin_init', __NAMESPACE__ . '\\pie_block_hidden_plugin_pages' );
add_action( 'admin_menu', __NAMESPACE__ . '\\pie_remove_hidden_plugin_menu_pages', 999 );
add_action( 'network_admin_menu', __NAMESPACE__ . '\\pie_remove_hidden_plugin_menu_pages', 999 );