# PIE Hosting Companion standards (S rules)

The PIE standards (P1 onwards) come with the `ada` plugin and apply here. This file holds only
this project's own rules, numbered S100 onwards so they never clash with a P rule. A project rule
may narrow or override a P rule; when it does, it says which and why.

The repo's `.github/copilot-instructions.md` applies too: no `empty()` in PHP, strict equality
and `const`/`let` in JavaScript.

## Logging sources (P15)

The plugin logs with `error_log()` only; there is no WooCommerce logger.

| Source | Module |
|---|---|
| `[PIE Custom Functions]` | Deploy and update check (`pie-custom-functions.php`) |
| `[PIE Redirections]` | Redirections (`pie/redirections.php`) |

## Owning functions (P25)

There are no classes. Each option is owned by one file:

- `pie_custom_functions_version`: `pie-custom-functions.php` (deploy).
- `pie_admin_role_cleanup_done`, `pcf_siteurl`: `pie-custom-functions-mu.php`.
- `pie_admin_override` user meta: `pie/pie-admin-access.php` writes it; `is_pie_admin()` reads it.
- `pie_csp_header`: `pie/security-headers.php`, read through `get_csp_header()`.
- `pie_update_watchdog`: `pie/update-watchdog.php`.

## Project rules

- **S100. `pie/` stands alone.** Everything under `pie/` runs from `mu-plugins` on a client site,
  loaded by the MU loader. It must not depend on the normal plugin, its Composer autoloader or
  `vendor/`. A new file in `pie/` is included from the MU loader (MU:42-47).
- **S101. Deploys are all or nothing.** Changes to the deploy path keep the atomic swap and
  record `pie_custom_functions_version` only after every copy has succeeded.
- **S102. PIE admin is a security boundary.** A change to who counts as a PIE admin, or what they
  can do, says so in the PR description and is tested with a PIE user, an override user and a
  plain administrator.
- **S103. Keep MU load light.** Code at the top level of the MU loader or a `pie/` file runs on
  every request of every client site. New work hooks in instead, and doesn't query or write at
  load.
- **S104. PHP 8.0.** The code must run on PHP 8.0, the plugin's `Requires PHP`.
