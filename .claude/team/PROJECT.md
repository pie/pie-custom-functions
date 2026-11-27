# <Project name>

The project profile. Every role reads this first: it is what makes the shared team specific to
this project. Keep it short and factual; the detail lives in `SYSTEM.md`, `API.md`,
`STANDARDS.md` and `TEST-AREAS.md`.

## The project

- **What it is:** <one sentence: the site, what it sells or does, who uses it>
- **Client:** <name>. Approves: <who>. Releases: <who>.
- **People:** <PIE developers>, <client developers>
- **Known issues live in:** <Asana project name and URL>. Search it before investigating a bug.
- **Kind:** <one of `product` (a plugin or theme PIE sells and supports) or `site` (a client's website)>
- **Knowledge base:** `none` <or, for a product, the folder's path, e.g. `knowledge-base/`>
- **Design guide:** `none` <or a path in the repo, a URL, or a design system artifact link>
- **Budget:** standard <`standard`, or `lean`: Bob, Claudia and Tess's Opus work at medium effort
  instead of high, for a project where a slip costs little. `/ada:begin` has the model table>

## Code and branches

- **Code:** <e.g. `themes/<theme>/`, `plugins/`>
- **Branch from:** `production`. **PR into:** `next-release`. **Test on:** merge into `review`.
  (Change if this project differs from the PIE workflow.)
- **Branch names:** `<type>/<slug>/<Asana task URL without https://>`
- **Versioning:** <`semver at release` (used in more than one place: no version numbers until
  next-release goes into main, P31) | `none` (a single-instance project)>

## Brief approval

Whose briefs need approving before anything is built, and which amendments need approving again.
`ada-brief` reads the first two lines; keep the three lines as they are and change only the
values.

- **Approvers:** <`none` if no one outside PIE writes code here; otherwise the GitHub logins who
  can approve, e.g. `@neilpie, @annawerno`>
- **Trusted:** <`none`, or the GitHub logins who don't need approval but can't approve>
- **Re-approval triggers:** none (the defaults in /ada:begin apply)

Approvers never need approval. When Approvers is `none`, no brief needs approval. Otherwise
anyone on neither list does: a new developer is covered without anyone remembering to add them. Approval is the `brief-approved` label on the brief's draft PR,
added by an approver. Once a brief is approved, Ada checks each amendment against the default
triggers in `/ada:begin` and any this project adds above. Only an amendment that hits one needs
approving again.

## Environments

| Branch | Environment | Host / install |
|---|---|---|
| `production` | Production | <install> |
| `review` | Review | <install> |

## Fragile areas

The areas where a change needs the most care. Ada escalates changes here to the developer; Bob
reads their `SYSTEM.md` sections before touching them; Tess tests them as sequences of events, not
single events.

1. <area>: `SYSTEM.md`, <section>
2. <area>: `SYSTEM.md`, <section>

## Review checks (Claudia's Must Fix list)

Project-specific checks on top of the P rules, each pointing at its S rule:

- <e.g. any date arithmetic that isn't `<the one helper>()` (S14)>

## Tests

- **Unit tests:** `tools/vendor/bin/phpunit` (`tests/README.md`).
- **PressTest tests:** `tests/presstest/`, run with `presstest run` (`tests/README.md`).
- **PressTest image:** <`1.0.0`, the version of the runner image, ghcr.io/pie/presstest-runner,
  the tests are pinned to, or "none">. `presstest run` reads the first `code` span here (or the
  first word), so keep the version first. Moved up with each new image, and the tests run again.
- **PressTest shared suites:** <`wordpress-core`, `woocommerce-core`: the suites in the image that
  run with the project's tests, or "none">. Every `code` span here is read.
- **PressTest staging:** <`https://staging.example.com`, the staging site `presstest run --env
  staging` tests, or "none" (e.g. Cloudflare's bot challenge blocks PressTest there)>. Never
  production: the suites place orders.
- **Clock-sensitive behaviour:** <yes/no; if yes, the positions to test, e.g. Friday after noon,
  Sunday>. If yes, the local site's clock is moved with `ada-clock` (`TEST-AREAS.md`, Dates and
  the clock).

## Production access (for `/ada:bughunt`)

Read-only, through `prod-wp`. Never write. Its settings (SSH, table prefix, logs path, never-query
list) are in `.claude/team/prod.env`; `prod-wp config` shows them.

- **Never construct on production:** <classes whose constructors write, if any, and why>
- **Inspectors:** `tools/bughunt/*.php`, each marked `READ-ONLY`. <List them.>

## Local copies

<How a local copy is made and made safe, e.g. pulled from production; Stripe in test mode;
emails disabled. Say what a check should verify.>

- **The local site runs the code in:** <the checkout its theme or plugin folder links to, e.g.
  `~/Repos/<repo>`>. Testing on the local site means testing whatever that checkout has checked
  out, so a branch switched there changes the site for everyone using it. To test a worktree,
  repoint the link at it (`ln -sfn <worktree>/<code dir> <site>/wp-content/themes/<theme>`) and
  point it back afterwards.
- **Running WP-CLI on the local site:** <how: e.g. Local's "Open site shell", or the command line
  with Local's PHP and MySQL socket>. Each developer's own paths go in `.claude/team/local.env`
  (git-ignored), never in this file.
- **The local site's URL:** `LOCAL_URL` in `.claude/team/local.env`, set by each developer. It is
  the only host Tess opens in Chrome, and the only site `presstest run` tests. The developer logs in to its admin before Tess checks
  anything in the browser. Tess sees a customer's view through the User Switching plugin's
  "Switch To", so the local site needs User Switching active; without it those checks stay manual.
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
  <Which tests need the customer, if any.>
- **Also checked before anything runs on the local site:** the background runners (Action
  Scheduler, WP-Cron) are frozen; the time WordPress reports from a web request is the intended
  time; nothing from an earlier run is left over (`.claude/handoff/CHECKPOINT.md`).
