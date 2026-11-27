# PIE Hosting Companion

<One or two sentences: what the site is and who maintains it.> This file loads into every Claude
session and every subagent, so it stays short (about 50 lines at most); the detail lives in
`.claude/team/` and is read on demand.

## Layout

| Path | What |
|---|---|
| `plugins/pie-custom-functions/` | <the theme or plugin> |
| `.claude/team/` | The project knowledge: `PROJECT.md` first, then `SYSTEM.md`, `API.md`, `STANDARDS.md`, `TEST-AREAS.md`. |
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
3. <The project's one or two hard rules that must hold even where no path rule loads.>
4. Production is read-only to Claude: `prod-wp`, or viewing in Chrome, never a write.
5. Every PR supplies tests for what it changes (P29).
6. No secrets in source.
