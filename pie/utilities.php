<?php

namespace Pie\Utilities;

use function Pie\CustomFunctionsMUPlugin\is_pie_admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Internal registry for plugins hidden from non-Pie admin users.
 */
final class Hidden_Plugins_Registry {

	/**
	 * @var array<string, string[]>
	 */
	private static array $plugins = array();

	/**
	 * Register a hidden plugin.
	 *
	 * @param string   $plugin_file Plugin basename.
	 * @param string[] $menu_slugs  Admin menu slugs.
	 */
	public static function add( string $plugin_file, array $menu_slugs ): void {
		self::$plugins[ $plugin_file ] = $menu_slugs;
	}

	/**
	 * Get all registered hidden plugins.
	 *
	 * @return array<string, string[]>
	 */
	public static function all(): array {
		return self::$plugins;
	}
}

/**
 * Hide a plugin from the plugins page, the admin sidebar, and direct URL access
 * for non-Pie admin users.
 *
 * Pass the top-level menu slug as the first entry in $menu_slugs so that
 * prefix-based URL blocking covers all child pages automatically
 * (e.g. 'wphb' blocks 'wphb', 'wphb-caching', 'wphb-dashboard', etc.).
 * Complex slugs like 'edit.php?post_type=foo' are also supported.
 *
 * @param string   $plugin_file Plugin basename, e.g. 'my-plugin/my-plugin.php'.
 * @param string[] $menu_slugs  Admin menu slugs to remove and block.
 */
function pie_hide_plugin( string $plugin_file, array $menu_slugs = array() ): void {
	Hidden_Plugins_Registry::add( $plugin_file, $menu_slugs );
}

/**
 * Check whether the current admin request matches a registered menu slug.
 *
 * Handles three forms:
 *  - Simple slug:  'wphb'                         → exact match on ?page=
 *  - Prefix slug:  'wphb'                         → also matches 'wphb-caching', 'wphb_group_foo'
 *  - Complex slug: 'edit.php?post_type=admin_tip'  → matched against $pagenow + query params
 *
 * @param string $slug    Registered slug.
 * @param string $pagenow Current admin page filename.
 * @return bool
 */
function pie_slug_matches_request( string $slug, string $pagenow ): bool {
	if ( false !== strpos( $slug, '?' ) ) {
		list( $slug_page, $query ) = explode( '?', $slug, 2 );

		if ( $pagenow !== $slug_page ) {
			return false;
		}

		parse_str( $query, $params );

		foreach ( $params as $key => $value ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! isset( $_GET[ $key ] ) || sanitize_key( $_GET[ $key ] ) !== $value ) {
				return false;
			}
		}

		return true;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';

	return $page === $slug
		|| str_starts_with( $page, $slug . '-' )
		|| str_starts_with( $page, $slug . '_' );
}

/**
 * Block direct URL access to hidden plugin admin pages for non-Pie admin users.
 */
function pie_block_hidden_plugin_page_access(): void {
	if ( is_pie_admin() ) {
		return;
	}

	global $pagenow;

	foreach ( Hidden_Plugins_Registry::all() as $menu_slugs ) {
		foreach ( $menu_slugs as $slug ) {
			if ( pie_slug_matches_request( $slug, $pagenow ) ) {
				wp_die(
					esc_html__( 'Sorry, you are not allowed to access this page.', 'pie-custom-functions' ),
					esc_html__( 'Access Denied', 'pie-custom-functions' ),
					array(
						'response'  => 403,
						'back_link' => true,
					)
				);
			}
		}
	}
}
add_action( 'admin_init', __NAMESPACE__ . '\\pie_block_hidden_plugin_page_access' );

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

	foreach ( array_keys( Hidden_Plugins_Registry::all() ) as $plugin_file ) {
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

	foreach ( Hidden_Plugins_Registry::all() as $menu_slugs ) {
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
add_action( 'admin_menu', __NAMESPACE__ . '\\pie_remove_hidden_plugin_menu_pages', 999 );
add_action( 'network_admin_menu', __NAMESPACE__ . '\\pie_remove_hidden_plugin_menu_pages', 999 );
