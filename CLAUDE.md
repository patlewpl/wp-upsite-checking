# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`wp-upsite-checking` — a WordPress plugin that watches **other people's sites**. It is installed on one site (an agency's own), which then polls each client's address on a schedule and e-mails a notification when one stops answering.

It is deliberately not a self-monitoring plugin: WP-Cron runs inside the site it is installed on, so a site cannot reliably report its own outage. Keep that constraint in mind before adding features that assume the monitored site and the host site are the same.

## No build or test tooling

There is no composer.json, package.json, phpunit config, or linter config. Nothing to build — the directory *is* the plugin. To run it, symlink or copy it into a WordPress install's `wp-content/plugins/` and activate it. There is no PHP binary on this machine; `php -l` only works through a container, and the user does not want Docker started without asking.

## Architecture

Hooks are collected by a loader rather than registered inline:

- `wp-upsite-checking.php` — plugin header, defines `WP_UPSITE_CHECKING_VERSION` and `WP_UPSITE_CHECKING_BASENAME`, registers activation/deactivation, instantiates `Wp_Upsite_Checking` and calls `run()`.
- `includes/class-wp-upsite-checking.php` — the core class. `define_monitor_hooks()`, `define_admin_hooks()` and `define_public_hooks()` are **the only place hooks get wired up**. Never call `add_action()` from a component class; call `$this->loader->add_action( $hook, $component, $callback )` here instead.
- `includes/class-wp-upsite-checking-loader.php` — accumulates the hook entries and flushes them to WordPress in `run()`.
- `includes/class-wp-upsite-checking-clients.php` — the client records (CRUD, validation, per-client state). One client = one monitored site.
- `includes/class-wp-upsite-checking-settings.php` — plugin-wide settings only: the addresses notified about *every* client, and the defaults new clients start with.
- `includes/class-wp-upsite-checking-monitor.php` — owns the WP-Cron events, performs the HTTP check, sends the mail.
- `includes/class-wp-upsite-checking-updater.php` — offers updates from the GitHub repo, since the plugin is not on wordpress.org.
- `admin/` — the menu, the client list, the add/edit form and the settings screen; markup lives in `admin/partials/`.
- `public/` — boilerplate leftovers, **no longer loaded**. The plugin has no front-end behaviour, so the class is not required and its empty CSS/JS are not enqueued. The files are still on disk only because this project is not under version control; they can be deleted. `admin/js/` is dead for the same reason.

### Two things that are easy to get wrong

**Cron events are per client and carry an argument.** Each client has its own recurring `wp_upsite_checking_check` event scheduled with `array( $client_id )`. Every cron call must pass the identical argument array or it silently won't match: `wp_next_scheduled( EVENT, array( (int) $id ) )`, `wp_clear_scheduled_hook( EVENT, array( (int) $id ) )`. To clear *all* events regardless of arguments (deactivation, uninstall), use `wp_unschedule_hook()` — `wp_clear_scheduled_hook()` with no args only matches argument-less events. The callback is registered with `accepted_args = 1`.

**Intervals need a registered schedule.** WordPress ships hourly/twicedaily/daily only, so `Monitor::add_cron_schedules()` registers one schedule per distinct interval in use, named `wp_upsite_checking_{minutes}m`. WP-Cron stores the interval *with* the event, so changing a client's interval requires `reschedule_client()` (clear, then schedule again) — updating the record alone leaves it running at the old interval. Note the ordering in `handle_save_client()`: the client must be saved *before* rescheduling, because the schedule list is built from the stored records.

`reschedule_all()` is a different thing — a repair that only adds missing events and drops a paused client's leftovers. It deliberately does not clear healthy events, so running it does not push back checks that were about to fire.

### Data model

Three options, no custom tables:

- `wp_upsite_checking_clients` — client records keyed by integer ID (`id`, `name`, `url`, `recipients`, `headers`, `interval`, `timeout`, `enabled`). IDs are `max(existing) + 1`.
- `wp_upsite_checking_options` — global settings (`recipients`, `interval`, `timeout`).
- `wp_upsite_checking_state` — last check result per client ID (`status`, `code`, `message`, `checked_at`, `changed_at`), not autoloaded, since it changes on every check.

`headers` holds extra request headers as one `Name: value` per line, for sites whose firewall blocks the monitoring server; `Clients::parse_headers()` validates the name as an HTTP token and strips control characters from the value, so a stray newline cannot inject further headers. A line that does not parse is reported rather than silently dropped, because a mistyped security header would otherwise look like an outage.

A site counts as **up** on HTTP 200-399; a `WP_Error` from `wp_remote_get()` or any other status counts as **down**. Mail is sent on a *status transition* only (up→down, down→up), so an outage produces one message rather than one per check. A first-ever check finding a site up is deliberately silent.

Notification recipients are the client's own addresses **merged with** the global ones, so each client hears about their own site and the agency hears about all of them. A `Reply-To` header points at `Settings::get_reply_to()` (first global address, falling back to `admin_email`), because the From address is whatever the site sends mail as and a client cannot usefully reply to it. Recipients, subject, body and headers all pass through the `wp_upsite_checking_notification` filter.

Deliverability is explicitly out of scope: the plugin calls plain `wp_mail()`, so SMTP transport and SPF/DKIM for the sending domain are the site owner's job. Do not add From name/address settings — an SMTP plugin's "Force From Email" would override them and the fields would mislead.

### Admin screens

A top-level `wp-upsite-checking` menu. The client list and the add/edit form are the same page, switched by `?action=new|edit`. Form submissions go through `admin-post.php` handlers (`handle_save_client`, `handle_delete_client`, `handle_check_now`, `handle_check_all`), each guarded by `verify_request()` (capability + nonce) and ending in a redirect. A rejected submission is stashed in a per-user transient so the form can be redisplayed as it was typed.

Partials are `require`d from inside the admin class's methods, so `$this` is in scope — the views use `$this->page_url()` and `$this->plugin_name` directly.

## Updates

The plugin updates itself from `patlewpl/wp-upsite-checking`, branch `main`. `Updater::check_for_update()` reads the `Version:` header out of the raw plugin file on that branch, compares it with `WP_UPSITE_CHECKING_VERSION`, and injects an entry into the `site_transient_update_plugins` transient pointing at the branch zip.

**Releasing is therefore a version bump.** Merging to `main` without raising the `Version:` header ships nothing — no site will see an update. Bump the header and the constant together, as they already have to be.

Two things here are load-bearing and easy to break:

- `fix_source_dir()` on `upgrader_source_selection` renames the unpacked `wp-upsite-checking-main/` back to `wp-upsite-checking/`. Without it an update installs the plugin at a new path, deactivating it and leaving the old copy behind.
- The lookup is cached in a transient for 6 hours (1 hour after a failure) and skipped when `force-check` is set, so "Check again" is immediate. `flush_cache()` clears it after any plugin update, or the installed version would keep being offered.

## Conventions

- Class names are `Wp_Upsite_Checking_*`, files are `class-wp-upsite-checking-*.php`. The `i18n` class is the one lowercase exception.
- Text domain `wp-upsite-checking`, loaded from `/languages`. `languages/wp-upsite-checking.pot` is **stale** — it still holds only the boilerplate strings and needs regenerating (`wp i18n make-pot . languages/wp-upsite-checking.pot`).
- Tabs for indentation, WordPress spacing style (`function( $arg )`), full DocBlocks with `@since`.
- Every directory keeps an empty `index.php` as a directory-listing guard.
- Bump `WP_UPSITE_CHECKING_VERSION` and the `Version:` header together — the version string is used for asset cache-busting.
- `README.txt` describes the current plugin; keep it in step with behaviour changes, along with the `Description:` header and the `Requires at least` / `Requires PHP` values (5.1 for `WP_Error::has_errors()`, 7.0 in practice).
