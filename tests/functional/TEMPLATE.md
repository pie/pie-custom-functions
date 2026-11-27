# <Behaviour, in plain words>

- Area: <number and name from .claude/team/TEST-AREAS.md>
- Asana: <task URL that introduced or changed this behaviour>
- Automate as: none | unit
- Last run: <date, result in a few words, where the detail is (e.g. PR #123)>

## Precondition

The plan state, product, dates and clock needed. How to get there from a fresh local site.

## Steps

1. Exact action, including what to click or which URL to open.

## Expected

What should be seen, with the source of the expectation (the brief, or the `SYSTEM.md` table),
not what the code happens to do.

## Observe at

Where to look (the full list is in `.claude/team/TEST-AREAS.md`), e.g. the page, the browser's
Network tab and console, an admin screen, order notes, WooCommerce → Status → Logs (source),
Tools → Scheduled Actions, `debug.log`.

## Regression

What next to this must not change, and how to check it.
