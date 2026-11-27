# Bug-hunt inspectors

Read-only PHP scripts that `prod-wp inspect <name> [args]` runs on production through
`wp eval-file`, with the arguments in `$args`. Each must say `READ-ONLY` in its first five lines,
or `prod-wp` refuses it.

Rules for a script here:
- Read only: no `save()`, `update_*()`, `add_*()`, `delete_*()`, `set_*()`, `wp_insert_*()`,
  `wc_create_*()`, `do_action()`, file writes or shell commands. `prod-wp` refuses a script whose
  code (not its comments) calls one; `add_query_arg()`, `add_filter()`, `add_action()`,
  `set_time_limit()` and `wp_set_current_user()` are allowed. The full list is `WRITE_CALLS` in
  the plugin's `bin/prod-wp`.
- On production the script runs after a guard: the database session is read-only, any query
  that isn't SELECT, SHOW, DESCRIBE or EXPLAIN is dropped, and no email or HTTP request goes
  out. Each is reported as `prod-wp: blocked …`. A read that WordPress turns into a write (an
  expired transient being deleted) is blocked the same way and is harmless.
- The guard can't stop object cache writes (`wp_cache_set()`, transients on a site with Redis or
  Memcached) or file writes: those are the scan's job, so don't get round it.
- Don't construct classes whose constructors write (`PROJECT.md` lists them). Read raw data.
- Print IDs and states, not customer personal data.
