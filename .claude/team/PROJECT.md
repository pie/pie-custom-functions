# PIE Hosting Companion

The project profile. Every role reads this first: it is what makes the shared team specific to
this project. Keep it short and factual; the detail lives in `SYSTEM.md`, `API.md`,
`STANDARDS.md` and `TEST-AREAS.md`.

## The project

- **What it is:** PIE's own hosting companion plugin. It deploys itself into `mu-plugins` on every
  PIE-hosted client site so it can't be deactivated. It grants PIE staff admin rights, hides PIE's
  tooling from clients, sends a CSP header, handles redirects, detects staging clones and alerts
  on stalled updates.
- **Client:** PIE (internal). Approves: PIE. Releases: PIE. A release is
  merging into `main` with a `release:*` label.
- **People:** Neil Pie, Joe Worthington, Neil Miller, Joey Gilham, Anna Werno (PIE). No client
  developers.
- **Known issues live in:** Internal Development Projects
  (https://app.asana.com/1/100438094841948/project/1206474484537567). Search it before
  investigating a bug.
- **Kind:** `product`
- **Knowledge base:** `knowledge-base/`
- **Design guide:** `none`
- **Budget:** standard

## Code and branches

- **Code:** `plugins/pie-custom-functions/`. `update.json` and `assets/` stay at the repo root:
  GitHub Pages serves them from `main` to the update checker on client sites.
- **Branch from:** `main`. **PR into:** `next-release`. **Release:** `next-release` into `main`
  with a `release:major|minor|patch` label (`.github/workflows/release.yml`). There is no
  `production` or `review` branch.
- **Branch names:** `<type>/<slug>/<Asana task URL without https://>`
- **Versioning:** `semver at release`. The release workflow bumps the version in `package.json`,
  `update.json`, both plugin headers and `changelog.md` (`release.yml:63-68`). Never bump a
  version by hand (P31).

## Brief approval

Whose briefs need approving before anything is built, and which amendments need approving again.
`ada-brief` reads the first two lines; keep the three lines as they are and change only the
values.

- **Approvers:** none
- **Trusted:** none
- **Re-approval triggers:** none (the defaults in /ada:begin apply)

Approvers never need approval. When Approvers is `none`, no brief needs approval. Otherwise
anyone on neither list does: a new developer is covered without anyone remembering to add them. Approval is the `brief-approved` label on the brief's draft PR,
added by an approver. Once a brief is approved, Ada checks each amendment against the default
triggers in `/ada:begin` and any this project adds above. Only an amendment that hits one needs
approving again.

## Environments

| Branch | Environment | Host / install |
|---|---|---|
| `main` | GitHub release, picked up by the update checker on every PIE-hosted site | `https://pie.github.io/pie-custom-functions/update.json` |
| `next-release` | Integration branch; no install of its own | none |

## Fragile areas

The areas where a change needs the most care. Ada escalates changes here to the developer; Bob
reads their `SYSTEM.md` sections before touching them; Tess tests them as sequences of events, not
single events.

1. MU self-deploy and auto-update: `SYSTEM.md`, Fragile areas, "1. MU self-deploy"
2. PIE admin capabilities: `SYSTEM.md`, Fragile areas, "2. PIE admin capabilities"
3. CSP header: `SYSTEM.md`, Fragile areas, "3. CSP header"
4. Staging detection: `SYSTEM.md`, Fragile areas, "4. Staging detection"
5. Update watchdog: `SYSTEM.md`, Fragile areas, "5. Update watchdog"

## Review checks (Claudia's Must Fix list)

Project-specific checks on top of the P rules, each pointing at its S rule:

- A file under `pie/` that is not loaded by the MU loader, or that depends on the normal plugin or
  its Composer autoloader (S100).
- A change to the deploy path that could leave `mu-plugins` half-updated, or that records the new
  version before the copy succeeded (S101).
- Any new way to become a PIE admin, or any change to `is_pie_admin()`, that isn't called out in
  the PR (S102).
- Code that runs at MU load (top level of `pie-custom-functions-mu.php` or a `pie/` file) doing a
  query or a write that could run on a hook instead (S103).

## Tests

- **Unit tests:** `tools/vendor/bin/phpunit` (`tests/README.md`).
- **PressTest tests:** `tests/presstest/`, run with `presstest run` (`tests/README.md`).
- **PressTest image:** `1.0.0`. `presstest run` reads the first `code` span here (or the
  first word), so keep the version first. Moved up with each new image, and the tests run again.
- **PressTest shared suites:** `wordpress-core`
- **PressTest staging:** none
- **Clock-sensitive behaviour:** no. The only time logic is the watchdog's 15-minute single
  cron event (`SYSTEM.md`, Fragile areas, "5. Update watchdog").

## Production access (for `/ada:bughunt`)

Read-only, through `prod-wp`. Never write. Its settings (SSH, table prefix, logs path, never-query
list) are in `.claude/team/prod.env`; `prod-wp config` shows them.

There is no production access set up: the plugin runs on many client sites, not one, so
`prod-wp` says so. Investigate a client site's problem through that site's own access.

- **Never construct on production:** none (the plugin has no classes).
- **Inspectors:** none yet.

## Local copies

A local copy is a WordPress install (here `pie-hosting-companion` in Local,
`http://pie-hosting-companion.local`) with this checkout linked in. It is made safe by the Disable
Emails plugin, so nothing is sent (including watchdog alerts), and it has User Switching active for
checking non-PIE-admin views. A check verifies both are active (`wp plugin list --status=active`)
before anything runs.

- **The local site runs the code in:** this checkout, cloned into the site's `app/public/` and
  symlinked: `wp-content/plugins/pie-custom-functions` →
  `../../pie-custom-functions/plugins/pie-custom-functions`. The features run from
  `wp-content/mu-plugins/`, which is a deployed copy unless the MU file there is a symlink to the
  plugin's. So an edit under `pie/` may not be live (`SYSTEM.md`, Known gotchas, "Locally, edits
  under `pie/` may not be live"). Testing on the local site means testing whatever this checkout has
  checked out, so a branch switched here changes the site for everyone using it. To test a
  worktree, repoint both links at it and point them back afterwards.
- **Running WP-CLI on the local site:** Local's "Open site shell", or `wp --path=<the site's
  app/public>` with Local's PHP and MySQL socket. Each developer's own paths go in
  `.claude/team/local.env` (git-ignored), never in this file.
- **The local site's URL:** `LOCAL_URL` in `.claude/team/local.env`, set by each developer. It is
  the only host Tess opens in Chrome, and the only site `presstest run` tests. The developer logs in to its admin before Tess checks
  anything in the browser. Tess sees a customer's view through the User Switching plugin's
  "Switch To", so the local site needs User Switching active; without it those checks stay manual.
  Checking non-PIE-admin behaviour needs a user whose email isn't `@pie.co.de` and who has no
  `pie_admin_override` meta.
- **Running the PressTest tests locally:** `presstest run` (`presstest config` shows the settings
  in use). Each developer adds to `.claude/team/local.env`: `PRESSTEST_SECRET` (the site's report
  token, Presstest > Settings, with "Allow Presstest to create test data" on), and
  `PRESSTEST_CUSTOMER_USERNAME` / `PRESSTEST_CUSTOMER_PASSWORD` if a test needs a shop customer.
  For staging (`--env staging`), the same with `PRESSTEST_STAGING_` in front: local values are
  never sent to staging. Needs Docker, curl and jq, and the Presstest Companion plugin (1.3.0 or
  later) on the local site. The runner image is private: each developer logs Docker in to
  ghcr.io once, as the "PressTest tests" section of the ada README says
  (https://github.com/pie/ada-and-friends#presstest-tests), which also says how to read the
  report token.
  No test needs a customer yet.
- **Also checked before anything runs on the local site:** the background runners (Action
  Scheduler, WP-Cron) are frozen; the time WordPress reports from a web request is the intended
  time; nothing from an earlier run is left over (`.claude/handoff/CHECKPOINT.md`).
