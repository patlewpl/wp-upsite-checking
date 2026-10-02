=== WP Upsite Checking ===
Contributors: patlew
Donate link: https://patlew.pl/
Tags: uptime, monitoring, downtime, notifications, cron
Requires at least: 5.1
Tested up to: 6.6
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Watches your clients' sites on a schedule and e-mails you when one goes down.

== Description ==

Install this on one WordPress site — your own — and list the sites you look after.
Each one is requested on its own schedule, and when it stops answering an e-mail
goes out.

Per client you configure:

* the address to request,
* the e-mail addresses to notify,
* how often to check it,
* how long to wait for a response,
* extra request headers, for sites behind a firewall or CDN.

A separate global address can be notified about every client, so your clients hear
about their own site while you hear about all of them.

A site counts as up when it answers with an HTTP status between 200 and 399.
A refused connection, a DNS failure, a timeout, or any other status — a 500 from a
PHP fatal error, a 403 from a firewall, an expired certificate — counts as down.

Notifications are sent when the status *changes*: one message when a site goes
down and one when it comes back, not one every time it is checked.

= What this plugin cannot do =

Checks run through WP-Cron on the site the plugin is installed on. WP-Cron fires on
incoming traffic, so if that site is quiet the checks run late, and if that site is
itself down no check runs at all. This is why the plugin is built to watch *other*
sites rather than the one it lives on.

For a schedule you can rely on, turn WP-Cron off and drive it from a real cron job,
ideally on a different machine:

`define( 'DISABLE_WP_CRON', true );` in `wp-config.php`, then:

`* * * * * curl -s https://your-site.example/wp-cron.php?doing_wp_cron >/dev/null`

== Installation ==

1. Upload the `wp-upsite-checking` folder to `/wp-content/plugins/`.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Open *Uptime* in the admin menu and add your first client.
1. Optionally set a global notification address under *Uptime > Settings*.

== Frequently Asked Questions ==

= Can it monitor the site it is installed on? =

It can, but it should not be relied on for that: if the site goes down, the cron
that would send the notification goes down with it. Use it to watch other sites.

= Will I get an e-mail every five minutes while a site is down? =

No. Mail is sent only when the status changes — once when the site goes down, and
once when it comes back up.

= How many sites can it handle? =

The client list is stored in a single option, which is suited to tens of sites. The
first check of each client is staggered so that they do not all fire at once.

= Why is a site reported as down when it opens fine in my browser? =

The check follows up to five redirects and verifies SSL. A firewall or bot
protection answering the request with a 403, or an expired or misconfigured
certificate, will be reported as down even though a browser may get through.

A 403 usually means the firewall in front of the site is blocking the server this
plugin runs on, rather than the site being broken. Hosting IP ranges are a common
target for such rules. The fix belongs on that firewall: add a rule that lets your
monitoring through, keyed either on your server's IP address or on a header only
you send, and put that header in the client's *Extra request headers* field.

== Changelog ==

= 1.0.0 =
* First release: per-client address, recipients, interval and timeout; e-mail on
  status change; manual "Check now" per client and for all clients at once.
