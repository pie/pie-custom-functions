# How PIE Hosting Companion behaves

Drafted by `/ada:onboard` from the code and its history; **corrected by a developer**. Anything
marked *(unverified)* is a reading of the code nobody has confirmed yet. The team trusts this file,
so a wrong line here becomes wrong code: fix it, don't work round it.

## The model

<The main things the system stores and how they relate: e.g. orders, subscriptions, bookings,
custom post types, their key meta.>

## What fires when

<The events that change data, in order: checkout, renewals, scheduled actions, webhooks, imports,
cron. For each: the hook, the function, what it writes.>

## Fragile areas

<One section per fragile area named in PROJECT.md: how it works, the traps, what not to touch, how
to test it.>

## Environments and flags

<Branches and installs, feature flags, constants, anything that differs between environments.
Local copies: how they're made and made safe.>

## Known gotchas

<Things that look wrong but are deliberate, or look right but aren't.>
