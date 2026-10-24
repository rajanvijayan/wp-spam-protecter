# ShieldCraft 2.0 roadmap (revamp of WP Spam Protecter)

## Context
WP Spam Protecter 1.0.0 (WordPress.org slug `wp-spam-protecter`, 2018, 0 installs, tested up to 4.9) is a Plugin Boilerplate shell with ~30 lines of logic. It adds a hidden "year" input and a `type="hidden"` "honeypot" in `comment_form_after_fields`, then marks a comment as spam in `comment_post` when the year is wrong or missing.
- **Bug:** `comment_form_after_fields` never fires for logged-in users, so **every comment by a logged-in user (admins included) is marked spam**.
- A hidden-type honeypot catches almost nothing. There is no sanitizing, no settings and no data.
- The GitHub repo `rajanvijayan/wp-spam-protecter` is **empty**; the code only exists in SVN (`trunk`, `tags/1.0.0`, `assets/` with icon and banner).

Decisions: scope **"Core + forms + API"**, name **ShieldCraft**, **no external services** (no CAPTCHA providers), version **2.0.0**, same repo and release flow as the other plugins.

Goal: a privacy-first, invisible anti-spam plugin. It covers comments, core forms, WooCommerce and the main form plugins, with a spam log, a React admin, and a PHP/JS/REST API for custom and headless forms.

## Constraints
- Main file stays **`wp-spam-protecter.php`** (WordPress stores active plugins by path).
- Naming:
  - text domain `wp-spam-protecter`
  - namespace `ShieldCraft`
  - prefixes `shieldcraft_` / `SHIELDCRAFT_`
- Requirements: PHP 7.4+, WP 6.5+. WooCommerce and form plugins are optional (no `Requires Plugins`).
- Nothing leaves the site: no remote calls, no CDN assets.
- Never push tags: the user tags the release PR head `2.0.0`.
- **One-time user step:** add `SVN_USERNAME` / `SVN_PASSWORD` secrets to this repo before the deploy.

## Detection engine (`src/engine/`)
- Each protected form gets fields from `Fields::render( $context )`. On submit, `Engine::check( $context, Submission )` runs the checks and returns a `Verdict`: `allow | suspicious | spam`, plus reasons.
- The verdict is logged. Each integration then applies the action configured for its area.

**Built-in checks** (each can be switched off; thresholds in settings):
1. **Honeypot:** a visually hidden *text* input with a plausible name, `tabindex=-1`, `autocomplete=off` and `aria-hidden`. Filled → spam.
2. **Signed time trap:** a token holding an HMAC (`wp_salt`) of context + timestamp. Faster than N seconds (default 3) → spam. A forged or invalid token → spam.
3. **JavaScript proof:** the front script writes a value derived from the token. Missing → suspicious (no JS, or a simple bot). Configurable: allow / hold / block.
4. **Cache-safe tokens:** on the first interaction with a form, the script fetches a fresh token from `GET shieldcraft/v1/token`. Cached pages therefore never carry stale tokens. A stale server-rendered token without JS is only *suspicious*.
5. **Content rules:**
   - Link count limit (default 2 for comments; also counts `[url=` BBCode).
   - Blocked words, merged with core `disallowed_keys`.
   - Blocked emails and domains.
   - Blocked IPs and CIDR ranges.
   - Optional "block messages written only in these scripts" (Cyrillic, CJK, Arabic…).
   - Duplicate message from the same sender within N minutes.
6. **Rate limit:** at most N submissions per IP (hashed) per minute per context, stored in transients.

- **Allowlists:** IPs, emails and roles. Users who can `moderate_comments` are always allowed.
- **Emergency off switch:** `define( 'SHIELDCRAFT_DISABLE', true )`.
- **Filters:** `shieldcraft_checks`, `shieldcraft_verdict`. **Action:** `shieldcraft_blocked`.

## Protected areas and actions
- **Comments, including WooCommerce product reviews:**
  - Fields go in `comment_form`, which fires for logged-in users too (fixes the 1.0 bug).
  - The check runs in `pre_comment_approved`.
  - Spam → `spam` status (reviewable); suspicious → held for moderation; or reject (setting).
  - Trackbacks and pingbacks: check or block all.
- **Registration and lost password** (default on): `register_form` / `registration_errors`, `lostpassword_form` / `lostpassword_post`. Multisite signup: `signup_extra_fields` / `wpmu_validate_user_signup`.
- **Login** (opt-in, with a lockout warning): `login_form`, the `login_form_middle` filter for `wp_login_form()`, and `authenticate`. XML-RPC, REST and application passwords are untouched.
- **WooCommerce:**
  - My Account registration and login.
  - Classic checkout: `woocommerce_after_checkout_billing_form` / `woocommerce_after_checkout_validation`.
  - Block checkout: rate limit only (documented).
- **Form plugins:** inject the fields and use each plugin's own spam or validation hook.

  | Plugin | Inject | Check |
  |---|---|---|
  | Contact Form 7 | `wpcf7_form_elements` | `wpcf7_spam` |
  | WPForms | `wpforms_display_submit_before` | `wpforms_process_before` (form error) |
  | Gravity Forms | `gform_submit_button` | `gform_entry_is_spam` |
  | Fluent Forms | `fluentform/render_item_submit_button` | `fluentform/before_insert_submission` / validation errors |
  | Elementor Pro Forms | `elementor_pro/forms/render/item` | `elementor_pro/forms/validation` |

  AJAX submits send our hidden inputs with the form data.

## API (`src/api/`)
- **PHP:**
  - `shieldcraft_fields( $context )` echoes the fields.
  - `shieldcraft_check( $context, array $data )` returns `[ verdict, reasons ]`.
  - `shieldcraft_is_spam()` is a shortcut.
- **JS:** `window.shieldcraft.token( form )` and `window.shieldcraft.protect( form )` for forms rendered later.
- **REST** (`shieldcraft/v1`):
  - `GET /token?context=`
  - `POST /check { context, token, js, fields… }` for headless forms. Rate-limited, and every call is logged.
  - `GET|POST /settings` and `GET /log` + `POST /log/{id}/action` (shop/site managers: `manage_options`).

## Log and privacy
- **Table:** `{prefix}shieldcraft_log`, created with dbDelta on activation and versioned. Columns: time, context, form id, verdict, reasons, keyed IP hash, email hash, a 200-char excerpt and a user-agent family.
- **Retention:** 30 days by default; a daily cron purges older rows.
- **Full IPs:** stored only if the site owner turns that on.
- **Privacy tools:** suggested privacy policy text, plus a personal-data exporter and eraser keyed by email hash.
- **Uninstall:** removes the options, the table and the cron.

## Admin (React, `@wordpress/components`, Settings → ShieldCraft)
- **Dashboard:** blocked counts per area for 7/30 days (CSS bars), top reasons, recent blocks.
- **Protection:** areas on or off, with the action for spam and for suspicious.
- **Rules:** checks, thresholds, words, IP/CIDR, emails, scripts, rate limit, allowlists.
- **Log:** filters, detail view, plus:
  - "Not spam" approves held or spam comments and allowlists the sender
  - "Block sender"
  - "Delete"
- **Integrations:** detected form plugins and their status.
- **Also:**
  - a dashboard widget with the 7-day count
  - a "Blocked by ShieldCraft: reason" note in the comment row actions

## Upgrade from 1.0
1.0 stored nothing. Comments posted from cached 1.0 markup (old `wp-spam-protecter-year` fields, no tokens) count as *suspicious* and are held, never silently lost. The 1.0 loader, i18n and boilerplate files are removed.

## Architecture (mirrors SwatchCraft, `wp-smart-variations`)
```
wp-spam-protecter.php     bootstrap (header, constants, vendor check, Plugin::boot)
src/class-plugin.php, class-settings.php, class-installer.php (table, cron, upgrades)
src/engine/               class-engine.php, class-fields.php, class-token.php, class-verdict.php, class-submission.php, checks/*
src/areas/                class-comments.php, class-registration.php, class-login.php, class-lost-password.php, class-multisite.php
src/integrations/         class-woocommerce.php, class-cf7.php, class-wpforms.php, class-gravity-forms.php, class-fluent-forms.php, class-elementor.php
src/log/                  class-log.php, class-privacy.php
src/rest/                 class-token-controller.php, class-check-controller.php, class-settings-controller.php, class-log-controller.php
src/admin/                class-settings-page.php, class-dashboard-widget.php, class-comment-notes.php
src/api/functions.php     public PHP API
assets/src/{frontend,settings}   wp-scripts entries; front script has no jQuery
tests/                    PHPUnit
```
- **Reuse from SwatchCraft:**
  - config files: `composer.json`, `phpcs.xml.dist`, `phpunit.xml.dist`, `.distignore`, `.gitignore`
  - workflows: `ci.yml` / `deploy.yml` / `assets.yml` with SLUG `wp-spam-protecter`, the main file name, and built-file and smoke-class lists swapped
  - `RELEASING.md`, `bin/install-wp-tests.sh`
  - code: the `Assets::asset()` helper, and the settings app pattern from `assets/src/settings/` with the `Settings` sanitize-against-defaults class
- **Tests:** `tests/bootstrap.php` optionally loads WooCommerce, CF7, WPForms Lite and Fluent Forms from `*_DIR` env vars. CI downloads the free ones. Gravity Forms and Elementor Pro (paid) are tested by calling their hooks with stub objects.

## Branches and milestones
- **`master`:** first commit imports SVN `tags/1.0.0` as is (Rajan V, "Import 1.0.0 from WordPress.org"), plus the SVN `assets/` into `.wordpress-org/`.
- **`plan/2.0`:** project rules + `docs/ROADMAP-2.0.md` (this plan).
- **`release/2.0`**, one commit per milestone, pushed as it goes:
  - **M1 foundations:** tooling and CI; bootstrap, settings and installer; engine with all checks; tokens and the REST token route; log table, retention and privacy; comments and reviews; uninstall; tests.
  - **M2 core forms and WooCommerce:** registration, lost password, opt-in login, multisite signup, WooCommerce account, checkout and reviews; front script (token refresh, `protect()`, no-JS behaviour); tests.
  - **M3 form plugins and API:** CF7, WPForms, Gravity Forms, Fluent Forms, Elementor; PHP/JS API, REST check route, developer docs; tests.
  - **M4 admin and release:** React app (dashboard, protection, rules, log actions, integrations), dashboard widget, comment notes, `readme.txt` and `README.md`, new icon/banner/screenshots, version 2.0.0, Plugin Check, release PR to `master`.

## Verification
- PHPCS, ESLint and Stylelint clean. PHPUnit on WP 6.5 and latest, with WooCommerce and the free form plugins loaded. CI green on the PR.
- **Browser (Playwright) on a test site** (`shield-site`, port 8910, Twenty Twenty-Five and Twenty Twenty-One):
  - **Real submissions pass:** a guest comment with JS; a logged-in admin comment (the 1.0 regression); WooCommerce registration and checkout; CF7, WPForms Lite and Fluent Forms submits.
  - **Bot-like submissions are blocked or held, each with a log row:** a curl POST without tokens; a filled honeypot; a submit faster than 3 s; too many links; a blocked word or IP; a rate-limit burst; a no-JS submission.
  - **Edge cases:**
    - a cached page served an hour later still submits (token refresh)
    - opt-in login never locks out the admin, and `SHIELDCRAFT_DISABLE` works
    - 390px width
    - no console errors
- **Admin:** settings save, log "Not spam" approves the comment, dashboard counts match the log.
- Plugin Check on the release build: 0 errors.
- **Release:** the user adds SVN secrets and tags the PR head `2.0.0`; the deploy succeeds; SVN `tags/2.0.0` and trunk Stable tag are verified; then merge.
