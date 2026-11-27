# Testing PIE Hosting Companion

What Tess verifies, how, and the areas the site is divided into for coverage. Numbers are stable
identifiers; refer to areas by number.

## What can be run

- **Static:** `php -l` and `tools/vendor/bin/phpcs` on changed files only.
- **Unit:** `tools/vendor/bin/phpunit` (`tests/README.md`).
- **Local site:** <where to observe results: order notes, logs, reports, Scheduled Actions>.
- **PressTest:** `presstest run`, the project's Playwright tests in `tests/presstest/` and the
  shared suites in `PROJECT.md` (Tests). The PressTest column of the areas below names each
  area's test files.

## Dates and the clock

<If behaviour depends on time: the clock positions to test, e.g. Friday after noon, Sunday, and
keep the rest of this section. Otherwise write "none" and delete the rest of this section.>

The local site's clock is moved with `ada-clock`. Each developer's setup is in
`.claude/team/local.env` (`ADA_CLOCK_ADAPTER`), which `/ada:onboard` writes. Under `local-linux`
only the site's clock moves. Under `manual`, `ada-clock` prints the steps to change the
computer's clock, and the developer follows them.

1. Freeze the background runners.
2. `ada-clock set "YYYY-MM-DD HH:MM"`, in the site's timezone. Under `manual`, the developer
   then follows the steps it prints.
3. `ada-clock status` before each date check. Go on only when it exits 0.
4. Run WP-CLI through `ada-clock wp`, so it sees the same time as the site.
5. `ada-clock reset` afterwards, always, then `ada-clock status`: its first line is
   `ada-clock: real`.

The rules, whoever moves the clock:

- Freeze the background runners first, before the clock moves.
- Run scheduled actions one at a time, by ID. Never `wp action-scheduler run`: it runs every
  action due at the fake time.
- Payment tests run on real time. Payment webhooks fail their signature checks while the clock
  is moved.

## The areas

Each area's PressTest files are under `tests/presstest/` (`presstest run <file>` runs one), or
"none" where nothing covers it yet.

| # | Area | Scope | PressTest |
|---|---|---|---|
| 01 | | | |
