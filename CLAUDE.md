# PIE Hosting Companion

PIE's own hosting plugin, maintained by PIE. It deploys itself into `mu-plugins` on every
PIE-hosted client site, and a release reaches all of them. This file loads into every Claude
session and every subagent, so it stays short (about 50 lines at most); the detail lives in
`.claude/team/` and is read on demand.

## Layout

| Path | What |
|---|---|
| `plugins/pie-custom-functions/` | The plugin, exactly as shipped. Only this folder goes into the release zip. |
| `update.json`, `assets/` | Served from `main` by GitHub Pages to the update checker. They stay at the repo root. |
| `.claude/team/` | The project knowledge: `PROJECT.md` first, then `SYSTEM.md`, `API.md`, `STANDARDS.md`, `TEST-AREAS.md`. |
| `knowledge-base/` | Past support issues, added to as support cards are worked. |
| `tools/`, `phpcs.xml.dist`, `phpunit.xml.dist` | PHPCS and PHPUnit. `composer install -d tools`. |
| `tests/` | Unit tests and functional scripts. `tests/README.md`. |

## The team

This repo uses the `ada` plugin from PIE. Type `/ada:begin` to start a piece of work,
`/ada:pickup` to carry on from a checkpoint, or `/ada:bughunt` for Grace to investigate a reported problem.
If you have not been given a role, you are Ada.

## Rules for everyone

1. Branch, name and open PRs as `.claude/team/PROJECT.md` says. **Nobody on the team merges.**
2. The PIE standards (P rules, in the plugin) and `.claude/team/STANDARDS.md` (S rules) apply.
   Reviews cite them by number.
3. Everything under `plugins/pie-custom-functions/pie/` runs from `mu-plugins` on client sites and
   must not depend on the normal plugin or `vendor/` (S100). Never edit version numbers: the
   release workflow sets them.
4. Production is read-only to Claude: `prod-wp`, or viewing in Chrome, never a write.
5. Every PR supplies tests for what it changes (P29).
6. No secrets in source.
