# Testing PIE Hosting Companion

What Tess verifies, how, and the areas the site is divided into for coverage. Numbers are stable
identifiers; refer to areas by number.

## What can be run

- **Static:** `php -l` and `tools/vendor/bin/phpcs` on changed files only.
- **Unit:** `tools/vendor/bin/phpunit` (`tests/README.md`).
- **Local site:**
  - the PHP error log (`error_log` lines prefixed `[PIE Custom Functions]` / `[PIE Redirections]`);
  - the options in the `SYSTEM.md` model table (`wp option get`);
  - `wp cron event list` for `pie_update_watchdog_check`;
  - response headers (`curl -I`) for CSP and redirects;
  - the plugins screen and admin menu, as a PIE user and as a plain administrator (User
    Switching);
  - the contents of `wp-content/mu-plugins/`.
- **PressTest:** `presstest run`, the project's Playwright tests in `tests/presstest/` and the
  shared suites in `PROJECT.md` (Tests). The PressTest column of the areas below names each
  area's test files.

## Dates and the clock

none

## The areas

Each area's PressTest files are under `tests/presstest/` (`presstest run <file>` runs one), or
"none" where nothing covers it yet.

| # | Area | Scope | PressTest |
|---|---|---|---|
| 01 | MU self-deploy and update check | Activation, redeploy on version change, failure and rollback, update checker | none |
| 02 | PIE admin capabilities | `@pie.co.de` users, override meta, the profile checkbox, WPMU DEV limit | none |
| 03 | Plugin hiding | Plugins list, menus and blocked pages for non-PIE admins | none |
| 04 | CSP header | Front end, admin and login headers; constant override; settings page | none |
| 05 | Redirections | Rule filter, conditions, status codes, path handling | none |
| 06 | Staging detection | `pcf_siteurl`, clone detection, CF7, multisite domain mapping | none |
| 07 | Update watchdog | Start and complete, stuck alert, cron event | none |
| 08 | Site still works | The whole site loads with the plugin active | `site_test.js` |
