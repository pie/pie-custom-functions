# What to call for what

The functions that already exist. Use these before writing anything new (P6).
Drafted by `/ada:onboard`; *(unverified)* marks entries nobody has checked yet. There are no
classes: everything is a namespaced function. Paths are relative to `plugins/pie-custom-functions/`.

## PIE admins (`Pie\CustomFunctionsMUPlugin`, `pie-custom-functions-mu.php`, `pie/pie-admin-access.php`)

| Function | Returns / does |
|---|---|
| `is_pie_admin( int $user_id = 0 ): bool` | Whether the user (default: current) is a PIE admin: `@pie.co.de` email or `pie_admin_override` meta, filtered by `pie_hosting_companion_is_pie_admin`. **Use this for every PIE-only check.** |
| `is_pie_admin_email( string $email ): bool` | Whether the email ends in `@pie.co.de` |
| `grant_pie_admin_caps( array $allcaps, array $_caps, array $_args, \WP_User $user ): array` | `user_has_cap` callback |
| `migrate_remove_pie_admin_role(): void` | One-off migration on `init` |
| `is_staging_site(): bool` | Whether this site is a clone of the one it was first loaded on |
| `set_duplicate_site_url_lock(): void` | Adds `pcf_siteurl` if missing |
| `get_duplicate_site_lock_key(): string` | The site URL with the marker inserted |
| `staging_setup(): void`, `update_domain_mapping(): void` | Multisite staging domain rewrite |
| `render_pie_admin_override_field( \WP_User $user ): void`, `save_pie_admin_override_field( int $user_id ): void` | The profile checkbox |

## Hiding plugins (`Pie\Utilities`, `pie/utilities.php`)

| Function | Use it for |
|---|---|
| `pie_hide_plugin( string $plugin_file, array $menu_slugs = array() ): void` | Hide a plugin and its admin menus from non-PIE admins. **The way to hide anything new.** |
| `pie_filter_hidden_plugins( array $plugins ): array` | `all_plugins` callback |
| `pie_register_hidden_plugins_filter(): void`, `pie_remove_hidden_plugin_menu_pages(): void`, `pie_block_hidden_plugin_pages(): void` | Hook callbacks |

## Security headers (`Pie\SecurityHeaders`, `pie/security-headers.php`)

| Function | Returns / does |
|---|---|
| `get_csp_header(): string` | The CSP value: the constant, else the option, CR/LF stripped. **Read the policy only through this.** |
| `add_csp_to_wp_headers( array $headers ): array`, `send_csp_header(): void` | Send it |
| `sanitize_csp_option( mixed $value ): string` | Settings sanitiser |
| `register_csp_settings(): void`, `register_settings_page(): void`, `render_settings_page(): void` | The settings page |

## Redirections (`PIE\Redirections`, `pie/redirections.php`)

Add rules with the `PIE\Redirections\filters\redirect_rules` filter, never by editing the file
(README).

| Function | Returns / does |
|---|---|
| `handle_redirects(): void` | `parse_request` callback |
| `get_request_path(): string` | The request path without the home subdirectory or a trailing slash |
| `path_matches_pattern( string $path, string $pattern ): bool` | Regex match, with a validity probe |
| `should_redirect_user( array $rule ): bool` | Evaluates `condition` |
| `perform_redirect( array $rule, string $request_path ): void` | Validates and redirects, then exits |
| `log_redirect( string $from_path, string $to_url, array $rule ): void`, `debug_redirect_attempts( string $path, array $rules ): void` | Debug logging |

## Update watchdog (`PIE\UpdateWatchdog`, `pie/update-watchdog.php`)

| Function | Returns / does |
|---|---|
| `record_update_start( bool\|\WP_Error $response, array $upgrade_context ): bool\|\WP_Error` | Starts watching; returns `$response` unchanged |
| `record_update_complete( \WP_Upgrader $_upgrader, array $upgrade_context ): void` | Stops watching |
| `check_stuck_update( string $key ): void` | Cron callback |
| `send_stuck_alert( array $stuck ): void` | The email |
| `resolve_plugin_name( string $plugin_file ): string`, `resolve_theme_name( string $theme_slug ): string` | Display names |

## Deploy (`PIE\CustomFunctions`, `pie-custom-functions.php`)

| Function | Returns / does |
|---|---|
| `pie_custom_functions_init( bool $is_activation = false ): void` | Deploys the MU files |
| `pie_custom_functions_copy_directory_atomic( string $source, string $destination ): bool` | Atomic folder swap. **Use for any folder deployed into `mu-plugins`.** |
| `pie_custom_functions_copy_directory_recursive( string $source, string $destination ): bool` | Plain recursive copy |
| `pie_custom_functions_delete_directory_recursive( string $path ): bool` | Recursive delete |
| `update_check(): void` | Redeploys when the version changes |
| `pie_custom_functions_load_composer(): void` | Autoloader and update checker |
| `hide_pie_custom_functions_mu_plugin_from_plugins_list( array $plugins ): array` | `all_plugins` callback |

## Logging

`error_log()` with a `[PIE Custom Functions]` or `[PIE Redirections]` prefix. Sources are listed
in `STANDARDS.md`.

## Never

- Check for PIE staff with your own email test: call `is_pie_admin()`.
- Read `pie_csp_header` directly: call `get_csp_header()`, which respects the constant.
- Call anything in `PIE\CustomFunctions` (the normal plugin) from `pie/`: on a client site, `pie/`
  runs from `mu-plugins` and the normal plugin may not be loaded yet (S100).
- `update_option( 'pie_custom_functions_version', ... )` anywhere but after a successful deploy.
- Write to `pcf_siteurl` other than through `set_duplicate_site_url_lock()`.
