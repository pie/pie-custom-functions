<?php
/**
 * Email template: stuck update alert.
 *
 * Variables available in scope (set by send_stuck_alert()):
 *
 * @var string $site_name   Human-readable site name.
 * @var string $site_url    Site URL.
 * @var int    $count       Number of stuck updates.
 * @var array  $stuck       Map of extension key => watchlist entry.
 * @var string $upgrade_dir Absolute path to the wp-content/upgrade directory.
 *
 * @package pie-custom-functions
 */

defined( 'ABSPATH' ) || exit;
?>
<p>
<?php
printf(
	/* translators: 1: plural suffix, 2: site name, 3: site URL */
	esc_html__( 'The following update%1$s on %2$s (%3$s) appear to have stalled:', 'pie-custom-functions' ),
	$count > 1 ? 's' : '',
	esc_html( $site_name ),
	esc_url( $site_url )
);
?>
</p>

<?php foreach ( $stuck as $key => $entry ) : ?>
<table>
	<tr><td><strong><?php echo esc_html( sprintf( '%s (%s)', $entry['name'], ucfirst( $entry['type'] ) ) ); ?></strong></td></tr>
	<tr>
		<td><?php esc_html_e( 'Slug:', 'pie-custom-functions' ); ?></td>
		<td><?php echo esc_html( $key ); ?></td>
	</tr>
	<tr>
		<td><?php esc_html_e( 'Started:', 'pie-custom-functions' ); ?></td>
		<td><?php echo esc_html( wp_date( 'Y-m-d H:i:s T', $entry['started_at'] ) ); ?></td>
	</tr>
	<tr>
		<td><?php esc_html_e( 'Duration:', 'pie-custom-functions' ); ?></td>
		<td><?php echo esc_html( human_time_diff( $entry['started_at'] ) ); ?></td>
	</tr>
</table>
<?php endforeach; ?>

<h2><?php esc_html_e( 'What likely happened', 'pie-custom-functions' ); ?></h2>
<p><?php esc_html_e( 'The server may have crashed or timed out mid-update, leaving the extension in a partial or broken state.', 'pie-custom-functions' ); ?></p>

<h2><?php esc_html_e( 'How to resolve', 'pie-custom-functions' ); ?></h2>

<h3><?php esc_html_e( '1. Clear partial update files', 'pie-custom-functions' ); ?></h3>
<p>
<?php
printf(
	/* translators: %s: path to the WordPress upgrade directory */
	esc_html__( 'Check %s for any leftover temp directories and delete them. These are safe to remove — WordPress recreates them as needed.', 'pie-custom-functions' ),
	'<code>' . esc_html( $upgrade_dir ) . '</code>'
);
?>
</p>

<h3><?php esc_html_e( '2. Verify the extension directory', 'pie-custom-functions' ); ?></h3>
<p><?php esc_html_e( 'If the plugin or theme directory is corrupted or missing files, reinstall it:', 'pie-custom-functions' ); ?></p>
<ul>
	<li><?php esc_html_e( 'Via wp-admin: Plugins > Add New > upload a fresh copy', 'pie-custom-functions' ); ?></li>
	<li><code>wp plugin install &lt;slug&gt; --force</code></li>
</ul>

<h3><?php esc_html_e( '3. Clear the WordPress update lock', 'pie-custom-functions' ); ?></h3>
<p><?php esc_html_e( 'If the update still shows as pending or unavailable, clear the update transients:', 'pie-custom-functions' ); ?></p>
<ul>
	<li><code>wp transient delete --network update_plugins &amp;&amp; wp transient delete --network auto_updater.lock</code></li>
	<li><?php esc_html_e( 'Via wp-admin: Dashboard > Updates > Check Again', 'pie-custom-functions' ); ?></li>
</ul>

<h3><?php esc_html_e( '4. Dismiss this alert', 'pie-custom-functions' ); ?></h3>
<p><?php esc_html_e( 'Once resolved, remove the stale watchdog entry so future alerts are not suppressed:', 'pie-custom-functions' ); ?></p>
<ul>
	<li><code>wp option delete pie_update_watchdog</code></li>
	<li><?php esc_html_e( "Via database: delete the row with option_name = 'pie_update_watchdog' from wp_options", 'pie-custom-functions' ); ?></li>
</ul>

<p><a href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( 'Go to admin area', 'pie-custom-functions' ); ?></a></p>
