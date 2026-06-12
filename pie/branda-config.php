<?php

namespace Pie\BrandaPieHostingConfig;

use function Pie\Utilities\pie_hide_plugin;
use function Pie\CustomFunctionsMUPlugin\is_pie_admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Restrict Branda access to the pie_admin role only.
 *
 * @return array
 */
function filter_branda_allowed_roles(): array {
	return array( 'pie_admin' );
}
add_filter( 'branda_permissions_allowed_roles', __NAMESPACE__ . '\\filter_branda_allowed_roles' );

// Hide specific plugins from non-Pie admin users
pie_hide_plugin( 'ultimate-branding/ultimate-branding.php', array( 'edit.php?post_type=admin_panel_tip', 'wpmudev-videos' ) );
pie_hide_plugin( 'wp-hummingbird/wp-hummingbird.php', array( 'wphb' ) );
