# Tests

Every PR supplies tests for what it changes (P29): PHPUnit first, then PressTest, then a committed
functional script. Tess writes them from the brief; Bob builds until they pass and never edits
them.

```
tests/
  unit/              PHPUnit, one directory per plugin or module
  presstest/         Playwright, run by PressTest against the local site
    shared/          code the tests share
  functional/
    TEMPLATE.md
    01-<area>/       one folder per area, numbered as in .claude/team/TEST-AREAS.md
```

## Unit tests

PHPUnit with Brain Monkey to stub WordPress and WooCommerce, so no WordPress install or database
is needed.

```sh
composer install -d tools
tools/vendor/bin/phpunit                          # everything
tools/vendor/bin/phpunit --filter SomethingTest   # one class or test
```

- Tests live in `tests/unit/<plugin or module>/`, one `*Test.php` per unit under test, extending
  the `TestCase` in `tests/unit/TestCase.php`.
- `TestCase` stubs `wp_timezone()`, the WooCommerce price-precision helpers and `__()`. Stub
  anything else with `Brain\Monkey\Functions\when()`.
- **Fix the clock with `$this->set_now( '2026-10-09 15:00' )`.** Code that reads
  `current_datetime()` then sees that moment. Code that reads the system clock directly can't be
  unit-tested until it's changed to `current_datetime()`.
- Expected values come from the brief or `SYSTEM.md`, never from running the code. Before handing
  over, break the code on purpose and check a test fails.
- `tests/unit/bootstrap.php` loads the files under test. Only files that just define functions,
  classes and constants load cleanly.

## PressTest

Playwright tests of what a visitor, customer or admin sees and does on the site, run with
`presstest run` against the local site (or `--env staging`) in the PressTest runner image. The
image version and the shared suites that run with these tests are in `.claude/team/PROJECT.md`
(Tests); the settings are in `.claude/team/local.env`.

```sh
presstest list                       # the test files
presstest run                        # everything, with the shared suites
presstest run checkout_test.js       # one file
```

A step runs all the unit tests and only the PressTest files for what it changes. The release run
before the release PR into main runs everything, with the shared suites.

- One `*_test.js` file per area or flow, named in the PressTest column of
  `.claude/team/TEST-AREAS.md`. Code the tests share goes in `shared/`, imported by relative path
  (`require( './shared/checkout' )`).
- Tests import Playwright and the PressTest helpers from the image, with
  `const { test, expect } = require( 'presstest' );`. The `presstest` fixture creates test data
  (users, orders) through the site's test session, which removes it when the run ends.
- Find elements by role, label or text, or by a `data-test` attribute where none of those is
  stable. Never by CSS classes or layout.
- Expected values come from the brief or `SYSTEM.md`, never from running the code. Before handing
  over, break the behaviour on purpose and check a test fails.
- The report and the artifacts of failed tests (screenshots, traces) are in `.claude/presstest/`
  (git-ignored), kept until the next run.
- A secret the tests need (a test helper's, say) goes in `.claude/team/local.env` as
  `PRESSTEST_ENV_<NAME>=value`; a local run passes it to the tests as `process.env.<NAME>`
  (capitals, digits and underscores; not a name `presstest` or bash uses, such as `URL`, `PATH`,
  `BASH*` or `UID`). Not passed to staging. Tests must never print it: it would land in the
  report.

## Functional scripts

Checks a person runs where neither a unit test nor PressTest reaches: admin screens, emails,
scheduled actions, exports. They're committed, so they build up into the regression checks for
each area.

- One file per behaviour, in the folder for its area. Add to an existing script for the same
  behaviour rather than writing a near-copy.
- Start from `functional/TEMPLATE.md`. Steps are exact enough that someone who has never seen the
  change gets the same answer.
- `Last run` is one line: the date, the result in a few words, and where the detail is (the PR).
  It is overwritten each run, never appended to. The run record (clock time, site, commits, what
  wasn't checked) goes in the PR description, not in the script, and nothing in the script names a
  commit or the state of the working tree.
- `Automate as: unit` marks a script that should become a unit test once the code allows it.
  What PressTest can check is written as a PressTest test, not a script.
