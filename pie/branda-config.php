<?php

namespace Pie\BrandaPieHostingConfig;

use function Pie\Utilities\pie_hide_plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Hide specific plugins from non-Pie admin users.
// The first slug in each array is the top-level parent — prefix matching in
// pie_slug_matches_request() covers all child pages automatically.
pie_hide_plugin( 'ultimate-branding/ultimate-branding.php', array( 'branding', 'edit.php?post_type=admin_panel_tip', 'wpmudev-videos' ) );
pie_hide_plugin( 'wp-hummingbird/wp-hummingbird.php', array( 'wphb' ) );
