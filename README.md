# PIE Hosting Companion

## What it does

A PIE-internal hosting companion that loads as a Must-Use plugin so it can't be accidentally deactivated by clients. Requires PHP 8.0+.

- **PIE admin access** — Treats any user with an `@pie.co.de` email address (or `pie_admin_override` user meta) as a PIE admin, granting administrator capabilities.
- **Plugin hiding** — Hides Ultimate Branding, WP Hummingbird, and this plugin itself from non-PIE admin users, while keeping them fully functional.
- **WPMU DEV restriction** — Limits WPMU DEV suite access to PIE admin users via `WPMUDEV_LIMIT_TO_USER`.
- **Branda restriction** — Restricts Ultimate Branding access to PIE admin users via its permissions filter.
- **URL redirections** — Regex-based redirect system with capture group support, conditions (`always`, `not_logged_in`, `logged_in`, `not_admin`, or a custom callable), and standard HTTP status codes. Rules added via `PIE\Redirections\filters\redirect_rules`.
- **CSP header management** — Settings > Pie Security Headers page (pie_admin only) for configuring a Content-Security-Policy header across frontend, admin, and login. Overridable via `PIE_CSP_HEADER` constant in `wp-config.php`.
- **Staging detection** — Detects staging clones by comparing the live URL to a protected option (resilient to search-and-replace). On staging: disables CF7 spam checks and updates Multisite domain mappings for `*.staging.tempurl.host`.
- **Auto-updates** — Deploys MU plugin files atomically on activation and after updates, sourcing releases from GitHub via `plugin-update-checker`.
- **Update watchdog** — Tracks plugin/theme updates and sends an alert email if an update appears to stall mid-process.

---

## Layout

| Path | What |
|---|---|
| `plugins/pie-custom-functions/` | The plugin, exactly as shipped. Only this folder goes into the release zip. |
| `update.json`, `assets/` | Served from `main` by GitHub Pages to the update checker on client sites. They must stay at the repo root. |
| `.github/` | The release workflow and coding instructions. |

## Local setup

Clone the repo into the site's webroot, install the plugin's Composer dependencies, and symlink the plugin into `wp-content/plugins`:

```
cd app/public
git clone git@github.com:pie/pie-custom-functions.git
composer install -d pie-custom-functions/plugins/pie-custom-functions
ln -s ../../pie-custom-functions/plugins/pie-custom-functions wp-content/plugins/pie-custom-functions
```

## Installation:

1. Download the latest copy of pie-custom-functions.zip from https://github.com/pie/pie-custom-functions/releases
1. Upload via wp-admin
1. Activate & enjoy

## Deploying updates:

This plugin template is set up to work with integrated WordPress updates through the use of
[yahnis-elsts/plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) and 
[rymndhng/release-on-push-action](https://github.com/rymndhng/release-on-push-action)

In order to deploy an update:

1. (Once) Enable Github Pages on your repository (Settings > Pages) so that a `update.json` can be read by the Update Checker in production sites.
1. Create a pull request to merge your branch into `main` and add the appropriate label:
    * `release:major`
    * `release:minor`
    * `release:patch`
1. When merged, the `release.yml` workflow will update all of your version numbers and commit them back into main and create a github release with an extra artifact:
    1. `plugin-slug.zip` - the uploadable plugin for manual installation, built from `plugins/pie-custom-functions/` only
2. Updates should then show in wp-admin for any users of the plugin

