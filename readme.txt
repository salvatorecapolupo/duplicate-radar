=== Duplicate Radar ===
Contributors: salvatorecapolupo
Tags: duplicates, cleanup, posts, administration, batch
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Batch scanning for duplicate WordPress posts — lightweight, secure, no external dependencies.

== Description ==

**Duplicate Radar** is a WordPress plugin that scans all published posts and identifies potential duplicates based on three selectable criteria:

* **Identical title** (case-insensitive comparison)
* **Similar permalink** (e.g. `article` vs `article-2`)
* **Content similarity** (with a configurable percentage threshold)

Scanning proceeds post by post via AJAX requests, so it does not block the browser and can be stopped and restarted at any time.

= Technical notes =
* Text comparison uses PHP's `similar_text()` function, capped at 50,000 characters to prevent timeouts.
* Every AJAX request is protected by a WordPress nonce and server-side input sanitization.
* Comparison is asymmetric (A→B but not B→A) to avoid duplicate pairs in the results table.

= Disclaimer =
This plugin is released as a free tool. On certain server configurations — in particular shared or low-cost hosting environments with restricted execution time — the plugin may behave unexpectedly. Always back up your database before performing bulk operations.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/duplicate-radar` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the Tools -> Duplicate Radar screen to start scanning.

== Changelog ==

= 1.1.0 =
* Security enhancements (XSS escaping and POST parameter sanitization).
* DB Query optimizations for better memory management.
* Added support for slug and content similarity checking.
* Added real-time progress bar with percentage and status updates.

= 1.0.0 =
* Initial release.
