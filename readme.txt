=== ShieldCraft – Invisible Spam Protection ===

Contributors: rajanit2000
Tags: spam, anti-spam, comments, contact form, honeypot
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stops spam in comments, registration, WooCommerce and contact forms. No CAPTCHA, no external services. Formerly WP Spam Protecter. Free.

== Description ==

ShieldCraft stops spam without asking your visitors to solve puzzles. It adds invisible checks to your forms, refuses or holds what looks like spam, and keeps a log so you can see what was stopped and why.

ShieldCraft is the new name and a complete rewrite of **WP Spam Protecter**. The plugin slug is still `wp-spam-protecter`. Everything is free, and nothing is ever sent to another service.

= Invisible checks =

* **Hidden field:** a field people never see or reach, which bots fill in.
* **Time trap:** each form carries a signed token, so forms sent within a few seconds of opening are spam, and tokens cannot be forged, back-dated or reused on another form.
* **JavaScript proof:** a small script adds a code that simple bots cannot. Visitors without JavaScript are only *suspicious*: their comments are held for moderation, not lost.
* **Works with page caching:** the script fetches a fresh token when the page opens, so cached pages keep working.

= Rules you control =

* Limit the number of links in a message.
* Block words (and use WordPress's own "Disallowed Comment Keys"), email addresses and domains, IP addresses and ranges (IPv4 and IPv6).
* Block messages written mostly in a writing system you never receive mail in (Cyrillic, Han, Arabic and more).
* Block the same message sent again, and limit submissions per visitor per minute.
* Allowlist roles, IP addresses and email addresses. Administrators, editors and moderators are never checked.

= Protects =

* **Comments and product reviews**: spam goes to the Spam folder (or is refused), suspicious comments are held. Trackbacks and pingbacks get the content rules.
* **Registration, lost password and network sign-up.**
* **Login** (optional): checked after the password, so a wrong password gets WordPress's usual error, and administrators are always let in.
* **WooCommerce**: My Account registration and login, and the classic checkout. The block checkout gets the content rules and the rate limit, which also slows down card-testing bots.
* **Contact Form 7, WPForms, Gravity Forms, Fluent Forms and Elementor Pro forms**, through each plugin's own spam handling, so refused messages show the form's usual error.
* **Your own forms**, with three PHP functions or the REST API (see the FAQ).

= See what was stopped =

* A **dashboard** with what was stopped, where and why, for the last 7 or 30 days.
* A **log** of every stopped or held submission. Mark a sender **Not spam** (the held comment is approved and the email address passes from now on) or **Block sender** (email and IP address).
* The reason next to each held or spam comment on the Comments screen, and a dashboard widget.

= Privacy =

ShieldCraft never contacts other services. Only stopped and held submissions are logged, for 30 days by default. Email and IP addresses are stored as coded (keyed hash) values that cannot be read back; you can choose to keep IP addresses. ShieldCraft adds suggested text to your privacy policy and works with WordPress's personal data export and erase tools.

== Installation ==

1. Install ShieldCraft from **Plugins → Add New** and activate it.
2. That's it: comments, registration, lost password, WooCommerce and your form plugins are protected straight away.
3. Review the options and the log under **Settings → ShieldCraft**.

== Frequently Asked Questions ==

= I used WP Spam Protecter 1.0. What changes? =

Version 1.0 marked every comment by a logged-in user as spam, and its hidden field rarely caught bots. ShieldCraft replaces it completely. 1.0 stored no data, so there is nothing to move. Comments sent from old cached pages are held for moderation, not lost.

= Do my visitors have to do anything? =

No. The checks are invisible. People who browse without JavaScript can still comment (their comments are held for you to approve) and still log in.

= A real message was stopped. What do I do? =

Open **Settings → ShieldCraft → Log** and choose **Not spam**. A held or spam comment is approved, and that sender's email address passes from now on (IP addresses are often shared, so they are not trusted). The log shows why each message was stopped, so you can adjust the rule.

= Can I protect the login form? Will I be locked out? =

Login protection is off by default. When it is on, administrators and editors are always let in, a login is never refused just for missing JavaScript, and XML-RPC, the REST API and application passwords are not affected. A custom login form must print the `login_form` action or use `wp_login_form()`. In an emergency, add `define( 'SHIELDCRAFT_DISABLE', true );` to `wp-config.php` to switch every check off.

= Does it work with page caching? =

Yes. The script fetches a fresh token from the REST API (never cached) when the page opens.

= My site is behind Cloudflare or a proxy. =

Choose the header that holds the visitor's address under **Settings → ShieldCraft → Allowlist and privacy**. The page shows what each header holds for your own visit. Only change it if your proxy really sends that header; otherwise visitors could fake their address.

= How do I protect my own form? =

Print the fields inside the form and check the submission where you handle it:

`<?php shieldcraft_fields( 'api' ); ?>`

`if ( shieldcraft_is_spam( 'api', array( 'email' => $email, 'message' => $message ) ) ) { /* refuse it */ }`

`shieldcraft_check()` returns the verdict and the reasons. Add your own context with the `shieldcraft_contexts` filter. Headless front ends can get a token from `GET /wp-json/shieldcraft/v1/token?context=api` and ask `POST /wp-json/shieldcraft/v1/check` from the server that accepts the form.

= Can developers change the checks? =

Yes: `shieldcraft_checks` adds checks, `shieldcraft_verdict` changes a verdict, `shieldcraft_blocked` fires for every stopped submission, `shieldcraft_min_seconds` changes the time trap per form, `shieldcraft_client_ip` changes the visitor's address and `shieldcraft_blocked_message` changes the error message.

== Screenshots ==

1. The dashboard: what was stopped, where and why.
2. The log, with Not spam, Block sender and Delete.
3. Which forms are protected and what happens to spam.
4. The invisible checks and the content rules.
5. Allowlist, log retention and privacy options.
6. The reason next to each spam comment.

== Changelog ==

= 2.0.0 =
* New: renamed to ShieldCraft and rewritten from scratch.
* New: hidden field, signed time trap and JavaScript proof, with fresh tokens for cached pages.
* New: rules for links, words, email addresses, IP addresses and ranges, writing systems, repeated messages and submissions per minute; allowlist by role, IP and email.
* New: protection for registration, lost password, network sign-up and (optional) login.
* New: WooCommerce My Account registration and login, classic and block checkout, and product reviews.
* New: Contact Form 7, WPForms, Gravity Forms, Fluent Forms and Elementor Pro forms.
* New: PHP functions and REST routes for custom and headless forms.
* New: dashboard, spam log with Not spam and Block sender, comment notes and a dashboard widget.
* New: privacy policy text, personal data export and erase, and hashed email and IP addresses in the log.
* Fix: version 1.0 marked every comment by a logged-in user as spam.
* Requires WordPress 6.5 and PHP 7.4 or later.

= 1.0.0 =
* Hidden field check for comments.

== Upgrade Notice ==

= 2.0.0 =
WP Spam Protecter is now ShieldCraft: invisible spam protection for comments, accounts, WooCommerce and contact forms, with a spam log. Fixes 1.0 marking logged-in users' comments as spam. Requires WordPress 6.5 and PHP 7.4.
