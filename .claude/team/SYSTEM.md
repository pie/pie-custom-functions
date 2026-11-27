# How PIE Hosting Companion behaves

Drafted by `/ada:onboard` from the code and its history; **corrected by a developer**. Anything
marked *(unverified)* is a reading of the code nobody has confirmed yet. The team trusts this file,
so a wrong line here becomes wrong code: fix it, don't work round it.

Paths are relative to `plugins/pie-custom-functions/`. **main** is `pie-custom-functions.php`,
**MU** is `pie-custom-functions-mu.php`. The code is plain namespaced functions; there are no
classes.

## The model

Two entry points:

- **The normal plugin** (main) is what WordPress installs and updates. It deploys the MU files,
  runs the update checker and hides the MU entry from the plugins list. It does nothing else.
- **The MU loader** (MU) is the copy in `wp-content/mu-plugins/`, plus the `pie/` folder next to
  it. All the features run from there (MU:42-47). It checks PHP 8.0 first and only shows a notice
  on older PHP (MU:27-40).

What it stores (no custom tables, no transients, no REST routes):

| Key | Kind | Written | Read | What |
|---|---|---|---|---|
| `pie_custom_functions_version` | option | main:138 | main:292 | The version last deployed to `mu-plugins` |
| `pie_admin_role_cleanup_done` | option | MU:74 | MU:61 | One-off migration flag for removing the legacy `pie_admin` role |
| `pcf_siteurl` | option | MU:253 (`add_option`, never overwrites) | MU:292 | The live site URL with `_[pcf_site_url]_` inserted at its midpoint, for staging detection |
| `pie_csp_header` | option | Settings API (sec:112) | sec:30 | The CSP header value |
| `pie_update_watchdog` | option, autoload off | wd:65, 112-114, 138-142 | wd | Map of update key → `{type, name, started_at}` |
| `pie_admin_override` | user meta | adm:63 (set) / adm:65 (delete) | MU:133 | Makes a non-`@pie.co.de` user a PIE admin |

Constants:

- `PIE_CSP_HEADER` in `wp-config.php` overrides the `pie_csp_header` option (sec:26).
- `WPMUDEV_LIMIT_TO_USER` is defined by the plugin (MU:156).
- The watchdog uses namespaced `const`s: `OPTION_KEY`, `STUCK_MINUTES = 15`, `CRON_HOOK`
  (wd:18-20).

Filters the plugin offers:

- `pie_hosting_companion_is_pie_admin` (MU:135)
- `pie_update_watchdog_alert_email` (wd:165)
- `PIE\Redirections\filters\redirect_rules` (redir:82)
- `PIE\Redirections\filters\redirect_url` (redir:325)

## What fires when

| When | Hook → callback | Writes / does |
|---|---|---|
| Activation | `register_activation_hook` → `pie_custom_functions_init(true)` (main:26) | Deploys the MU files; `wp_die()` on failure |
| Every request, `plugins_loaded` | `pie_custom_functions_load_composer` (main:29) | Loads `vendor/autoload.php` and builds the update checker (main:42-50) |
| Every request, `plugins_loaded` | `update_check` (main:30, 286-298) | If the stored version is below the header version, redeploys |
| MU load, top level | `get_users( search '*@pie.co.de' )` (MU:147-156) | Defines `WPMUDEV_LIMIT_TO_USER` |
| MU load, top level | `set_duplicate_site_url_lock()` (MU:170) | Adds `pcf_siteurl` if missing |
| MU load, top level | `is_staging_site()` (MU:172) | On staging, hooks the two staging callbacks below |
| `init` | `migrate_remove_pie_admin_role` (MU:76) | Removes the `pie_admin` role once |
| `user_has_cap` | `grant_pie_admin_caps` (MU:100) | Gives PIE admins every administrator cap |
| `wpcf7_spam`, staging only | `__return_false` (MU:175) | Turns off Contact Form 7's spam check |
| `admin_init`, staging only | `staging_setup` (MU:178) | Multisite domain mapping |
| Profile screens | `render_` / `save_pie_admin_override_field` (adm:30-31, 68-69) | `pie_admin_override` meta |
| `load-plugins.php` | `pie_register_hidden_plugins_filter` (util:70-71) | Adds the `all_plugins` filter |
| `admin_init` | `pie_block_hidden_plugin_pages` (util:97) | 403 for a hidden `?page=` slug |
| `admin_menu`, priority 999 | `pie_remove_hidden_plugin_menu_pages` (util:98) | Removes hidden menus |
| `wp_headers` | `add_csp_to_wp_headers` (sec:54) | CSP on the front end |
| `admin_init`, `login_init` | `send_csp_header` (sec:77-78) | CSP in admin and on login |
| `admin_init`, `admin_menu` | `register_csp_settings`, `register_settings_page` (sec:123, 150) | Settings > Pie Security Headers |
| `parse_request`, priority 1 | `handle_redirects` (redir:411) | Redirects, then `exit` |
| `upgrader_pre_install` | `record_update_start` (wd:22) | Watchlist entry and a single cron event 15 minutes on |
| `upgrader_process_complete` | `record_update_complete` (wd:23) | Clears the entry and the event |
| `pie_update_watchdog_check` (cron) | `check_stuck_update` (wd:24) | Removes the entry and emails the alert |

## Fragile areas

### 1. MU self-deploy

How it works (`pie_custom_functions_init`, main:68-139):

1. It bails if `WPMU_PLUGIN_DIR` isn't defined (main:78).
2. It copies `pie/` to `WPMU_PLUGIN_DIR/pie` atomically (main:95, 197-238). It builds `pie-new`,
   renames the live folder to `pie-old` and `pie-new` to live, then deletes `pie-old`. It rolls
   back if promotion fails, and logs `CRITICAL` if the rollback fails too (main:226).
3. It copies the MU file to `pie-custom-functions-mu.php.new` and `rename()`s it over the live one
   (main:110-135).
4. Only then does it record the version (main:138). On failure the version isn't recorded, so the
   next request tries again.

Traps:

- `update_check` runs on every request (main:30, 295-296). Every failure path returns before
  the version is recorded (main:95-105, 121, 134, 138), so a deploy that keeps failing is retried
  in full on every request.
- There's no lock. Two concurrent requests can both deploy: one can delete `pie-new` while the
  other is copying into it (main:203-205), giving a false failure, a retry, and possibly the
  `CRITICAL` log (main:226). Not reproduced. Even a single deploy has a moment between main:218
  and main:223 when `mu-plugins/pie/` doesn't exist.
- `vendor/autoload.php` is required unconditionally (main:43). A release zip without `vendor/` is
  fatal. `.zipignore` only excludes `composer.*` and `*.log`, so vendor ships.
- There's no deactivation or uninstall hook. The MU files and options stay after the plugin is
  removed.
- History: "hotfix incorrect mu plugin path" (#39) and "Fix error on update" (#65) were both in
  this path.
- The update checker is `yahnis-elsts/plugin-update-checker` v5.7, reading
  `https://pie.github.io/pie-custom-functions/update.json` (main:45-49). It's loaded only by the
  normal plugin.

To test: activate on a site with no MU copy; deploy over an existing MU copy by lowering
`pie_custom_functions_version`; make `mu-plugins` unwritable and check that the version isn't
recorded and the old copy still loads.

### 2. PIE admin capabilities

- `is_pie_admin_email()` is `str_ends_with( $email, '@pie.co.de' )` (MU:109). It is
  case-sensitive, and WordPress doesn't lowercase emails on save (`sanitize_email()` keeps
  `x@PIE.CO.DE` as it is), so a mixed-case PIE address isn't a PIE admin. The WPMU DEV
  `get_users()` search (MU:147) is a case-insensitive SQL `LIKE`, so that same address *is* given
  WPMU DEV access: the two checks disagree.
- `is_pie_admin()` is true for that email or for `pie_admin_override` meta, then filtered
  (MU:124-136).
- `grant_pie_admin_caps` copies every cap from the `administrator` role into `allcaps` on
  `user_has_cap` (MU:86-98). Nothing is stored and no role is assigned. It overwrites caps that
  were denied.
- The override checkbox only renders for a viewer with a PIE email, on a profile without one
  (adm:17-27). Saving checks the nonce `pie_admin_override_{id}`, a PIE-email current user and
  `edit_user` (adm:41-65).
- `WPMU DEV` access goes to `@pie.co.de` users only. Override-meta users aren't included
  (MU:147-156). The `define()` is unguarded: if `wp-config.php` already defines
  `WPMUDEV_LIMIT_TO_USER`, PHP 8 gives a warning ("Constant already defined") and carries on with
  the wp-config value. On multisite, `get_users()` is scoped to the current site
  (`WP_User_Query` defaults `blog_id`, class-wp-user-query.php:94), so a super-admin with no role
  on that site is left out (code only; not tested on a multisite).
- Anyone whose email becomes an `@pie.co.de` address becomes an administrator. A user changing
  their own email must confirm it from the new address first (core
  `send_confirmation_on_profile_email`). An administrator editing another user's email, WP-CLI,
  `wp_update_user()` and `wp_insert_user()` skip that confirmation, so any administrator can make
  any user a PIE admin.

To test: a PIE-email user, an override user and a plain user, each on a capability check, the
plugins screen and the WPMU DEV dashboard.

### 3. CSP header

- The value is the `PIE_CSP_HEADER` constant if it's non-empty, else the option. CR and LF are
  stripped (sec:25-35).
- The front end gets it through `wp_headers`. Admin and login get it through `header()` on
  `admin_init` / `login_init`, guarded by `headers_sent()` and skipped for AJAX (sec:66-78).
- The settings page needs `is_pie_admin()` and `manage_options` (sec:137-165). The sanitiser keeps
  the old value for a non-PIE admin, returns `''` for an array, and otherwise unslashes, strips
  CR/LF and trims (sec:92-105).
- There is no syntax check. A bad policy can break wp-admin; the constant is the way back in.
- The form shows the option, not the constant, when the constant is set (a notice says it's
  active).
- REST responses get no CSP header: `rest_api_loaded` runs on `parse_request` and exits before
  `wp_headers` fires (checked with curl on `/wp-json/`).
- **In wp-admin, core replaces the header.** Core's `send_frame_options_header` also runs on
  `admin_init` at priority 10, after this plugin's MU callback, and its `header()` call replaces
  ours: `admin-post.php` was seen sending `Content-Security-Policy: frame-ancestors 'self';`
  instead of the configured policy. The same hook order applies to every wp-admin page (from the
  code; only `admin-post.php` was checked). `wp-login.php` sends the configured policy.
- The front end may be page-cached, so a `HEAD` request can show an old response without the
  header; check with a cache-busting query string.

To test: an empty value sends no header; a value sends exactly that header on the front end,
admin and login; the constant wins; a non-PIE admin can't see or save the page.

### 4. Staging detection

- On first load, `pcf_siteurl` is set to the site URL with a marker inserted at its midpoint
  (MU:250-277). The marker is there so a search-and-replace on a staging clone doesn't rewrite
  it.
- `is_staging_site()` removes the marker and compares the result with `siteurl`. Any difference
  means staging (MU:288-299).
- On staging, Contact Form 7's spam check is off (MU:175). On a multisite whose `HTTP_HOST`
  contains `staging.tempurl.host`, `update_domain_mapping` rewrites every site but blog 1 to
  `{domain}.{main host}`, with its `siteurl` and `home` (MU:191-238). It runs on every
  `admin_init` while on staging.

Traps:

- A real `siteurl` change on a live site (http to https, a new domain) looks like staging.
- A site whose first load is already a clone stores the clone's URL as live.
- `$_SERVER['HTTP_HOST']` is read without `isset` or sanitising (MU:195).
- `get_sites()` is called with no arguments (MU:214) and `WP_Site_Query` returns 100 sites by
  default, so a network with more than 100 sites is only partly mapped.
- Local copies aren't detected as staging unless their `siteurl` differs from the stored one.
  The Asana card "Set up staging and local sites safely by default" is about this area.

To test: fresh site (live); clone with search-and-replace (staging); `siteurl` change on the same
site; a multisite on a `staging.tempurl.host` host.

### 5. Update watchdog

- `record_update_start` works out plugin or theme from the upgrader context, adds a watchlist
  entry and schedules `pie_update_watchdog_check` at `time() + 15 * MINUTE_IN_SECONDS` (wd:36-75).
  It returns the response it was given, so it never blocks the install.
- `record_update_complete` handles singular and plural keys, removes the entries and clears their
  events (wd:84-124).
- `check_stuck_update` removes the entry if it's still there and calls `send_stuck_alert`
  (wd:132-151). That sends an HTML `wp_mail` to `admin_email` (filterable), from
  `pie/templates/emails/stuck-update-alert.php` (wd:162-183).

Traps:

- It relies on WP-Cron: no alert if cron never runs.
- An update that takes more than 15 minutes raises a false alert.
- An upgrade that fails with an error doesn't: `upgrader_pre_install` fires inside
  `install_package` (class-wp-upgrader.php:556), and `run()` still fires
  `upgrader_process_complete` after a returned error (:988; bulk upgrades always, from
  class-plugin-upgrader.php:413). Failures before the install (download, unpack) never start the
  watchdog. The alert fires only when the request dies between the two: a fatal error, a timeout
  or an `exit`. That's what the watchdog is for.
- Core updates aren't tracked.
- The entry is deleted when the alert fires, yet the email asks the reader to delete it "so
  future alerts are not suppressed" (template:85).

To test: start and complete an update (no alert, no event left); start one and run the event by
hand (one alert, entry gone); bulk updates.

## Environments and flags

- `main` is released by `.github/workflows/release.yml`. A merged PR with a `release:*` label
  bumps the versions with `sed`, commits "Version Numbering" back to `main`, zips
  `plugins/pie-custom-functions/` (less `.zipignore`) and creates the GitHub release. Client sites
  pick it up through `update.json` on GitHub Pages.
- Flags: `PIE_CSP_HEADER`. `WP_DEBUG` and `WP_DEBUG_LOG` turn on redirect debug logging
  (redir:93, 331).
- Local copies: the symlinked checkout described in `PROJECT.md`, Local copies. How they're made
  safe is not known yet.

## Other areas

- **Plugin hiding** (`pie/utilities.php`, `pie/branda-config.php`): `pie_hide_plugin()` registers a
  plugin file and menu slugs to hide from non-PIE admins (util:24). Ultimate Branding (slugs
  `branding`, `edit.php?post_type=admin_panel_tip`) and Hummingbird (`wphb`) are hidden
  (branda-config:13-14). The plugin hides itself (MU:49) and the MU entry (main:311). Hiding is
  UI only: the plugins list, the menu, and a 403 for a matching `?page=`.
- **Redirections** (`pie/redirections.php`): rules come from the `redirect_rules` filter. Each has
  `pattern` and `destination` (required), `condition` (default `not_admin`), `status_code` (301,
  302, 303, 307 or 308; anything else becomes 302 and is logged) and `description`. Skipped in
  admin, AJAX, JSON and WP-CLI requests (redir:51). The first match wins.

## Known gotchas

- **Locally, edits under `pie/` may not be live.** The loader includes `pie/` relative to its
  own path (MU:42-47). If `mu-plugins/pie-custom-functions-mu.php` is a symlink to the plugin's
  file, it loads the checkout's `pie/` and edits are live. A deploy (whenever the stored
  `pie_custom_functions_version` is below the header) copies `pie/` into `mu-plugins/` and
  `rename()`s a real file over the symlink, replacing the link. From then on the site runs the
  copy in `mu-plugins/`, and edits in the checkout don't show until the next deploy. Before
  testing a change locally, check with `ls -l wp-content/mu-plugins/`.
- **Redirect capture groups don't work.** The docblock advertises `$1` (redir:28), but the
  destination is used as it is (redir:298-302).
- **Off-site redirects become the home page.** `wp_validate_redirect( ..., home_url() )` rewrites
  any host not in `allowed_redirect_hosts`.
- Redirect matching ignores the query string and is case-sensitive. `not_admin` means "can't
  `manage_options`". The condition `switch` compares loosely, so `true` matches `'always'`
  (redir:231).
- `edit.php?post_type=admin_panel_tip` can never equal `$_GET['page']`, so that Branda screen is
  hidden from the menu but still reachable (util:81).
- `get_users()` runs on every request at MU load (MU:147).
- Namespaces mix `PIE\` and `Pie\`; PHP treats them the same.
- `Domain Path: /languages` is declared (main:12), but there's no `languages/` folder.
- The changelog says 1.6.0 removed the Pie Admin role, but the code says `@since 1.5.2` (MU:70).
- Logging is `error_log` only, prefixed `[PIE Custom Functions]` or `[PIE Redirections]`.
