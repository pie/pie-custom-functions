<?php

namespace Pie\BrandaPieHostingConfig;

use function Pie\Utilities\pie_hide_plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Hide specific plugins from non-Pie admin users.
// List the main page slug and any standalone pages that use a different slug.
pie_hide_plugin( 'ultimate-branding/ultimate-branding.php', array( 'branding', 'edit.php?post_type=admin_panel_tip' ) );
pie_hide_plugin( 'wp-hummingbird/wp-hummingbird.php', array( 'wphb' ) );
