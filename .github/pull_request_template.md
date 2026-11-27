Asana:
Brief: <`.claude/briefs/<task id>.md`, approved by @login | approval not needed, and why>
Plan: <none | unit <ID> of `.claude/briefs/<Epic task id>-plan.md`; ships <independent | after #PR>>
Design: <the Design artifact link | text only, in the brief | none>

<!-- A feature's last PR (feature branch into the release branch): list its parts' PRs here,
with the smoke-test result under Tested. It is checked like a release, by a smoke test, not a
code review. -->

## For the reviewer

Review: <about N minutes (small | medium | large), from `ada-size`; raised for a fragile area, and why>
Actual review time: <the reviewer fills this in, so the estimates can be tuned>

Three to five lines: where the review is (the changed logic, and any fragile area, by file and
function), what is cheap to skim, and what wasn't verified.

## Squash commit

Paste these into the squash-merge dialog. The title is what goes on the production branch.

**Title**

```text
<imperative title> (#<PR number>)
```

**Message**

```text
<what the change does and why, wrapped at 72>

Asana: <task URL>

Co-authored-by: Bob (Ada and friends, Claude Code) <bob@ada.pie.co.de>
Co-authored-by: Tess (Ada and friends, Claude Code) <tess@ada.pie.co.de>
Reviewed-by: Claudia (Ada and friends, Claude Code) <claudia@ada.pie.co.de>
```

## What changed and why

## Built

Files touched, and any decision that wasn't in the brief.

Knowledge base: the entry written or updated (`issues/<file>`), or why none was needed. Only for a product with a knowledge base (`PROJECT.md` `Kind:` `product`, `Knowledge base:` a folder); delete it otherwise.

## Reviewed

Rules checked (STANDARDS.md numbers), what was found, how it was resolved.

## Tested

- Tests supplied (P29), by Definition of Done item: unit / PressTest / functional script, with
  paths. Any item without one, and why:
- Verified (with the clock time for anything date-related):
- Not verified, and why:
- Failed and left: none
- Run record (copied from TEST-REPORT.md: clock time, site, IDs used, commits before and after, row counts, checks not run and why):

## Manual test steps

Numbered steps a person can follow in the browser to check the change by hand: where to go,
what to do, what they should see. Say when a step only works at certain times (e.g. after 12:00
on a Friday, or on a Sunday). "None" if there is nothing to check by hand, and why.

1.

## Surfaces checked

- [ ] Checkout
- [ ] Emails
- [ ] Admin
- [ ] Exports / production sheets
- [ ] Other plugins' hooks
- [ ] Template `@version` headers

## Known gaps

Out-of-scope items found and logged, not fixed.

---

Built by Bob, reviewed by Claudia, tested by Tess. Ada and friends, Claude Code.
