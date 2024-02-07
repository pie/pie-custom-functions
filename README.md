# PIE Hosting Companion

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
    1. `plugin-slug.zip` - the uploadable plugin for manual installation
2. Updates should then show in wp-admin for any users of the plugin

