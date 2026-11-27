# Support knowledge base

Past support issues for this product: what caused them and how they were resolved. They come from
the support cards in Asana. Use it to answer new tickets faster, to spot problems that keep
coming back, and, when investigating a bug, to check whether it has been seen before.

This folder is for the team and is never part of a release, like everything else Ada adds to a
repo (`.claude/`, `tests/`). Keeping it out of the release is the job of the project's build.

## What's here

```
README.md          This file
INDEX.md           One line per entry, grouped by area
config.json        The Asana project the support cards are in
issues/            One Markdown file per issue
```

## Finding an answer

1. Scan [INDEX.md](INDEX.md): one line per entry, grouped by area, with its status.
2. Or search the entries, e.g. `grep -ril "nonce" issues/` from this folder. Error messages are
   quoted exactly, so search for the customer's wording.
3. Each entry gives the symptoms, the cause, the solution, how to confirm it's the same issue, and
   the source tickets.

An entry describes the code on the development branch when it was last checked. Before giving a
customer a snippet or a code explanation, check it against the version they run.

## Entry format

File name: `issues/<short-kebab-case-summary>.md`.

```markdown
---
title: Guests get "security verification was not successful" when joining
category: configuration    # bug | conflict | configuration | how-to | feature | licensing | support-process | development
status: resolved           # resolved | workaround | fixed-in-release | open-bug | unresolved
tags: [nonce, caching, guests]
plugin_versions: [2.6.2]   # versions the issue was reported on
fixed_in:                  # a version in the product's changelog, when status is fixed-in-release
first_seen: 2026-09-13
last_seen: 2026-09-25
sources:
  - asana:1218435735798081
---

## Symptoms
What the customer sees, including exact error messages (they are what people search for).

## Cause
Why it happens. Say so if it is suspected rather than confirmed. "n/a: <why>" when there is no fault (a how-to).

## Solution
What fixed it, or the recommended fix. Include code or CSS snippets where relevant.

## How to diagnose
Steps and questions to confirm this is the issue.

## Notes
Optional: related entries, environment details that mattered, reply wording that worked.
```

- **Required:** the front matter fields `title`, `category`, `status`, `tags` and `sources`, with
  `category` and `status` from the lists above, and the four sections Symptoms, Cause, Solution
  and How to diagnose, none of them empty. Notes is optional. `###` sub-headings inside a section
  are fine.
- **Categories:** `bug`, `conflict` (with another plugin or theme), `configuration`, `how-to`,
  `feature`, `licensing`, `support-process` and `development`.
- **Statuses:** `resolved` (the customer confirmed the fix, or it's a how-to that works);
  `workaround` (a way round it, no fix in the product); `fixed-in-release` (fixed in a released
  version of the product, named in `fixed_in`); `open-bug` (a confirmed bug, not fixed in a
  released version yet); `unresolved` (no confirmed cause or fix).
- **Sources** are `asana:<task id>`: IDs only, never links or content from the ticket. Entries
  written before this folder stopped using Gmail may also have `gmail:<thread id>`.
- **Code references:** a file in this repo is cited in backticks by its full repo path, with the
  function or method named beside it and no line numbers (they move with every release). A link
  to code is relative to the entry. Paths on a customer's site (`wp-content/themes/...`,
  `functions.php`) are written as site paths.

## No customer data

Entries are read by the whole team and by Claude. They hold no customer data: no names, email
addresses (other than `@pie.co.de` or `example.com`), phone numbers, order numbers, site URLs or
licence keys. Describe the setup instead ("site using Super Page Cache", "Woodmart theme").

Customer tickets stay in Asana. Nothing from them is saved in this repo except the anonymised
entry and the task ID in its `sources`. An entry is written from what the ticket showed, in our
own words.

## When to write an entry

- Every bughunt and every support card worked by the team records its card in an entry, new or
  updated, so the knowledge base keeps a record of all of them and can be used for reporting.
  That includes a card that turned out not to be a bug (`configuration` or `how-to`) and one
  whose cause isn't found yet (`unresolved`).
- Before creating an entry, check whether the knowledge already exists. If it does, update the
  existing entry rather than creating a duplicate.
- Capture the problem, root cause (if known), investigation steps, resolution or workaround,
  and any relevant technical context.

To write one:

1. Search `issues/` for an entry on the same problem. To update it, add the source, widen
   `plugin_versions` and `last_seen`, and refine the cause or solution. Otherwise create
   `issues/<short-kebab-case-summary>.md` in the format above.
2. Check the code and the changelog on the development branch: is the bug still there, or fixed?
   Name the file and function. Set `status: fixed-in-release` and `fixed_in` only once the fix's
   version is in the product's changelog. A fix on the development branch that isn't released
   yet stays `open-bug`, with the fix described in Solution.
3. Be honest about the status: if the ticket closed without the customer confirming, it's
   `unresolved` and the entry records what was suggested. Don't present a guess as the fix.
4. Add one line for a new entry to [INDEX.md](INDEX.md), under its area, with the status icon.
   Each entry is linked from the index exactly once.
5. Leave the entry and `INDEX.md` uncommitted; the developer commits them together.

What doesn't get an entry: routine work (compatibility updates, review checks, coding-standards
chores), auto-replies and spam. Feature requests go into one `issues/feature-support-faq.md`
rather than an entry each; add each card to that entry's sources. A problem that reached us in
more than one place is one entry with every source.
