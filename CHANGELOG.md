# Changelog

All notable changes to SynaptikCMS are documented here.  

## [1.3.4.4] — 2026-08-12

### Added
- **Markdown footnotes** — `[^1]` in content now renders as a superscript link (`<sup>[1]</sup>`) that jumps to the matching definition at the bottom of the article. Definitions (`[^1]: Source text`) are collected before parsing, removed from the body, and appended as a numbered `<ol class="md-footnotes">` list with a back-link (↩) per entry. Also added inline superscript syntax: `^text^` → `<sup>text</sup>`.

### Fixed
- **synaptik-docs theme v1.1.4** — TOC scroll-spy broken in three ways: (1) `docs_extract_toc()` only matched headings that already had an `id`, so articles without the `[toc]` shortcode had no ids on their headings and the scroll-spy tracked nothing — fixed by passing `$renderedContent` by reference and injecting ids on all headings. (2) Duplicate heading titles (e.g. two "Plugins" sections in the same article) generated identical ids; `getElementById` always returned the first one, making the second and any sections between them unhighlightable — fixed by deduplicating with a `-2`, `-3` suffix. (3) `offsetTop` was used for position detection (relative to nearest positioned parent), replaced with `getBoundingClientRect().top` (relative to viewport).
- **Booking plugin** — PDF recap: long field values now wrap correctly; character-width estimate raised from 0.52 to 0.60 to match real Helvetica metrics, and words longer than the line width are now hard-broken instead of overflowing the margin.
	- PDF recap: title and submission date were overlapping; cursor now drops `fontSize + gap` after the title instead of a fixed 6pt.
	- HTTP 500 on form submission: `formbuilder-submit.php` was requiring `functions.php` at the CMS root, which no longer exists since the v1.3.3 core restructure (moved to `/core/`). Removed the core dependency entirely; `config.json` is now read directly via a new `fb_core_config()` helper in `formbuilder-functions.php`, matching the pattern used by all other plugins. Also cleaned up `fb_notify_submission()` in `formbuilder-mail.php` which was using the same broken `function_exists('loadConfig')` guard.
- **Booking plugin** — Cancellation link in the "request received" email (plain-text path, no custom template) was appearing on the same line as the label text. The URL is now on its own line.
	- `[button url="..." label="..."]` shortcodes in custom email templates are now converted to inline-styled `<a>` tags compatible with all major email clients.
	- Default (no custom template) pending and confirmed client emails now send as HTML instead of plain-text, so the cancellation link renders as a proper hyperlink instead of a raw URL.
	- Dates in client emails are now formatted in the site's active language using the existing translated month names. Previously always rendered in English via PHP's `date()`.
	- `[button]` shortcode in email templates now accepts an optional `color="#hex"` attribute to set the button background color.
- **Search overlay** — `getBaseUrl()` in `main.js` was reconstructing the CMS root from `window.location.pathname` using a pattern-matching heuristic that failed on themes with arbitrary page slugs (no `/article/`, `/page/` prefix). Replaced with a direct read of `window.CMS_BASE_URL`, now injected by `render_header_scripts()` for all themes automatically. Fixes 404 errors on `core/search.php` requests.
- **main.js global scope** — `const t` and `const highlighters` were declared at the top level of `main.js`, causing a `SyntaxError: Identifier 't' has already been declared` crash when any other script on the page declared the same variable name. The entire file is now wrapped in an IIFE.
- **Markdown editor** - now includes all available shortcodes, similar to the WYSIWYG editor.

## [1.3.4.3] — 2026-08-11

### Fixed
- **Installer** — `/core/.htaccess` now correctly allows direct HTTP access to `search.php`, `feed.php`, and `contact-process.php` while blocking all other PHP files. Previously, the installer wrote a blanket deny-all `.htaccess` to `/core/`, which broke front-end search and the RSS feed on every fresh install.
	- Language dropdown now correctly lists all available locales (EN/FR/ES). The scanner was pointing to `lang/admin/` instead of `lang/front/`, causing only English to appear.
	- Selected language is now written to both `active_language` and `admin_language` in `config.json`, so front-end and admin panel start in the same language chosen at install time.

## [1.3.4.2] — 2026-08-10

### Changed
- **Analytics plugin v1.0.2** — The overview chart can now switch between Page Views and Unique Visitors. The selected time range is remembered across all analytics tabs.
- **Installer** — Font switched from (Google Fonts) to Inter (Bunny Fonts).
    - Password fields now have an eye icon toggle to reveal/hide the value.
	- Footer and post-install notes now explain that `install.php` self-deletes and advise manual removal only as a fallback, rather than telling users to delete it themselves unconditionally.

### Added
- **Markdown nested lists** — Lists in Markdown content now support arbitrary nesting. Indent child items by 2 or more spaces to create sub-levels. Mixed ordered/unordered nesting is supported.

### Security
- Hardened `.htaccess` protection across the CMS core and all plugins. Several internal directories (`/cache/`, `/lang/`, `/core/`, admin sub-directories) were accessible over HTTP and are now deny-all protected. All plugin `data/` and `private/` directories now unconditionally rewrite their `.htaccess` on every activation rather than skipping the write when the file already existed — previously, manually deleting and recreating a data directory left it unprotected until the plugin was deactivated and reactivated. `install.php` updated to provision the correct `.htaccess` for all sensitive directories from the first install.

### Fixed
- **Dashboard** — Media file count now correctly excludes `.htaccess` and dotfiles from `/files/`. Previously, `.htaccess` was counted as a file (showing "1 file" on empty installs), and any stale cache generated in that state would persist for up to 5 minutes. Stale cache cleared on deploy.
- Minor admin CSS adjustments: corrected a `background` shorthand in the base form styles that was interfering with custom `select` chevron rendering, and cleaned up sidebar panel form rules.
- Other CSS improvements in the admin.

---

## [1.3.4.1] — 2026-08-09

### Added
- **New Ink Theme** - Dark editorial theme for writers and bloggers. Big type, clean masonry card grid, burger menu, and nothing to distract from the content.
- **Plugin API improvements** — Plugins can now register hooks with an execution priority (lower number runs first), so two plugins hooking the same event no longer depend on load order to behave predictably. A new filter system (`pl_add_filter` / `pl_apply_filter`) lets plugins transform data in a pipeline rather than just react to events. A new shared options API (`pl_get_option` / `pl_set_option` / `pl_delete_option`) gives plugins a standardized, zero-boilerplate way to store and retrieve their settings. All changes are fully backward compatible — existing plugins require no modification.

### Changed
- All themes updated to benefit from the latest features, CSS adjustments to adapt new footer info.
- Merged `plugin-upload.php` and `theme-upload.php` into a single `extension-upload.php` endpoint — same security pipeline, one file to maintain.

### Fixed
- Fixed the admin bar "Edit" link pointing to the wrong item when drafts were present in the index — the link now reads the raw index directly instead of the filtered front-end index, which excluded drafts and caused position offsets.
- Fixed topnav theme search in Synaptik-docs and Vanta themes, returning no results after `search.php` was moved to `/core/`.
- Fixed admin flash error messages not auto-dismissing after 5 seconds, unlike success messages.

---

## [1.3.4] — 2026-08-08

### Added
- **Automatic theme and plugin updates** — Admin → Appearance → Themes and Admin → Extensions now check for newer versions of installed themes and plugins against a public registry, the same way the core CMS already checks for new releases. An "Update available" badge appears on any theme or plugin with a newer version, with a one-click Update button that downloads, safety-backs-up, and replaces the extension's files. Plugin `data/` and `private/` folders (subscriber lists, bookings, CSRF secrets) are always preserved during a plugin update, never overwritten.
- Update checks are cached for 24 hours to avoid repeated remote lookups, matching the existing core-update check behaviour.
- **New Vitae Theme** — A CV/portfolio theme with identity card hero, animated skill bars, experience timeline, masonry project grid, dark/light toggle and styled contact form. All CV data is managed from a single admin page and the theme uses custom fields to display data. Detailed instructions on how to use and set up inside the package.
- **New Analytics Plugin** — Server-side traffic analytics with no external scripts, no cookies, and no database. Tracks page views, unique visitors, referrers, and device types automatically once activated. The admin panel shows a views-per-day line chart, device and traffic-source breakdowns, a top-pages ranking, and a referrer domain table — all filterable by 7, 30, 90, or 365 days. Bots and logged-in admins are excluded from tracking. IPs are never stored: each visitor is identified by a daily-rotating hash for the single-day "today" count, and a monthly-rotating hash for accurate unique-visitor counts over longer periods. Log retention is configurable and data can be purged at any time from the Settings tab. GDPR-friendly by design.

### Changed
- Extension update downloads now explicitly detect HTTP error responses (e.g. a renamed or missing file on the server) instead of only failing later at the "invalid package" step, giving a clearer error message when a download link is broken.
- **Cleaner root directory** — `search.php` and `feed.php` moved from the CMS root into `/core/`, leaving only `index.php` and `install.php` as root-level entry points. All internal references (JS search endpoint, RSS auto-discovery link) updated accordingly.
- `getBaseUrl()` now derives the CMS sub-directory from `CMS_ROOT` instead of `$_SERVER['SCRIPT_NAME']`, making it reliable regardless of which script handles the request.

### Fixed
- Fixed search (`Ctrl+K` and the search page) returning no results on any site that had already run the v1.3.3 migration. `search.php` still required `data-layer.php` from its old root-level path, which no longer exists after the `/core/` restructure.
- Fixed the content editor preview button opening a blank or broken page.

---

## [1.3.3] — 2026-08-06

### Added
- **New Cookie Consent Plugin** - GDPR-friendly cookie consent banner with per-category opt-in (analytics, marketing), stateless customizable UI, and a public JS API so themes and other plugins can defer their tracking scripts until consent is given. [Download it here](https://synaptikcms.com/files/plugins/cookie-consent-plugin-synaptikcms.zip)

### Changed
- **Cleaner project structure** — core PHP files (data layer, rendering engine, plugin API, i18n cache) have been moved out of the root into a dedicated `/core/` directory, and template rendering modules into `/core/render/`. The plugin registry now lives at `/plugins/plugins.json` instead of the root, and `settings.json` has been renamed to `config.json` for clarity. The result is a much tidier root directory with only the actual entry points (`index.php`, `search.php`, `feed.php`, `install.php`) remaining. A one-time migration script runs automatically on the first page load after update to clean up legacy files — no manual action required.
- **All default themes now use Bunny Fonts instead of Google Fonts** — GDPR-friendly out of the box, no personal data or logs collected, no configuration required. Bunny Fonts is a privacy-first drop-in replacement for Google Fonts, so all font families and weights are visually identical. Themes migrated: Mono (default), Axion, Blueish, Darkish, Folio, Myelin, Natura, Nihonium, Nova, Portfolio, Prism, SynaptikDocs, and Vanta. Thanks to [Erik (@akvariefisk)](https://github.com/akvariefisk) for the suggestion.
- **SEO** — archive pages (tag pages, and content-type list pages like `/articles/`, `/projects/`, `/pages/`) are now excluded from search engine indexing. Only real content (individual articles, pages, and projects) appears in Google, keeping the search index focused on what actually matters and avoiding thin, duplicate archive pages diluting results. Category pages remain indexed since they often carry unique intro text.
- **Sitemap generator** — no longer includes content-type archive pages, matching the new noindex behavior so Google gets consistent signals.

### Fixed
- Fixed a crash on the Nova, Natura, and Prism themes caused by an outdated internal file path after the v1.3.3 project restructure.
- Fixed the contact form failing silently on submission due to a broken security token path.
- Fixed scheduled and draft content appearing in theme footers, shortcode blocks, and navigation menus before their publication date. Any call to the content index on the front-end now automatically excludes unpublished items, instead of relying on each individual call site to filter them.
- Fixed leftover `tmp-update-*` folders piling up in `/bckps/` after every update, whether it succeeded or was interrupted partway through. Any orphaned folder from a previous update is now cleaned up automatically the next time the update page loads.
- **SynaptikDocs theme** — improved contrast and accessibility across both light and dark modes to meet WCAG AA requirements. Swapped sidebar and content background tones so the sidebar is visually recessed in both modes. Inline code snippets now use a teal accent instead of green to stand out from the primary color. Links inside article content are now underlined for accessibility compliance.

---

## [1.3.2] — 2026-08-03

### Added
- **Markdown editor**: images can now be resized inline with a size suffix, e.g. `![alt](img.png =300x)` for width, `=x200` for height, `=300x200` for both. Percentages are supported (`=50%x`).
- **New Comments plugin**: let visitors comment on articles, pages, and projects, with one level of replies, manual or automatic approval, and spam protection.
- **New Form Builder plugin**: build custom forms with a dynamic field editor (text, long text, email, phone, number, dropdown, checkbox). Submissions are validated, saved, and emailed as a PDF recap. Past submissions are browsable and re-downloadable from the admin.
- **New Reviews plugin**: admin-managed customer reviews, displayed as a carousel or grid via shortcode.
- **Booking plugin**: added SMTP mailer, required notes field, optional callback-hours restriction, custom sender address/name, customizable client emails with a one-click cancellation link, customizable calendar invites, and various admin polish.
- **Newsletter plugin**: added SMTP mailer. Article digest emails now show proper article cards (image, title, summary) instead of a plain link list, with a fully customizable digest template. Unsubscribe now asks for confirmation before removing a subscriber, and the unsubscribe link in every email is now a discreet inline link instead of a raw URL.
- **Maintenance plugin**: customizable page title, colors, and an optional logo.

### Changed
- **Date formatting**: the site's configured date format is now applied consistently everywhere a date is displayed, across the Reviews, Newsletter, and Booking plugins.
- **Admin checkboxes and dropdowns**: Booking, Form Builder, Redirects, Reviews, and Newsletter now use the same styled checkboxes and dropdown arrows as the rest of the admin, instead of each plugin's own inconsistent styling.

### Fixed
- **Booking plugin**: fixed appointments not moving from Requests to History at the right time.
- **Booking plugin**: fixed the client cancellation link sometimes 404ing due to a stray space appearing in the URL.
- **Newsletter plugin**: fixed the article digest always reporting "no new articles" when sent from the admin, regardless of what was actually published.
- **Core**: fixed a display-date fallback that could have caused a fatal error, and cleaned up duplicated date-formatting logic in the front-end card renderers.
- **Front-end admin bar**: fixed the bar being hidden when `settings.json`'s `admin_dir` did not match the actual admin folder on disk (e.g. after moving the site between environments, or renaming the folder without saving the new name). The front-end now falls back to a filesystem scan to keep the admin session cookie aligned.
- **Maintenance plugin**: fixed a redirect pointing to the wrong admin folder on installs where it had been renamed.
- **All plugins (Maintenance, Redirects, Newsletter, Booking)**: fixed a session-hardening step being silently skipped on installs with a renamed admin folder. Every plugin admin page now also follows the panel's light/dark toggle automatically.
- **Extensions page** (Tools → Extensions): rebuilt to match the Theme Manager's look and behaviour exactly, with active plugins now sorted first.
- **Markdown editor**: fixed images with underscores in their filename (e.g. `my_image_file.webp`) being broken on the front end because the underscores were mistakenly rendered as italics.

---

## [1.3.1] — 2026-07-19

### Added
- **Booking plugin**: new "History" tab listing all past appointments (any status), separate from Requests, which now only shows upcoming ones.
- **New Maintenance plugin**: puts up a maintenance page for visitors (HTTP 503, custom message) while a logged-in admin keeps browsing the site normally.

### Changed
- **Plugin system**: replaced the core-file-specific hooks used by the Maintenance and Redirects plugins (`index.php` calling `mt_maybe_block()` / `rd_maybe_redirect()` by name) with two generic hook points any plugin can use without ever touching a core file: `early_request` (fired right after `functions.php` loads, before routing or output — for blocking/intercepting the whole request) and `after_routing` (fired once the route is resolved, with whether the request is a genuine 404 — for redirect-style logic). `index.php` no longer references any plugin by name. See the updated Plugin System documentation for details.
- Confirmation messages in the Maintenance, Booking, Newsletter, and Redirects plugin admin pages now use the same auto-dismissing message style as the rest of the admin panel, instead of a plugin-specific banner that stayed on screen until the page was reloaded.
[Download Plugins](https://synaptikcms.com/plugins/)

### Fixed
- Fixed admin sessions being shared between separate SynaptikCMS installs living under the same domain (e.g. a demo site in a sub-folder) — each install's admin login now uses its own uniquely-named session cookie, so logging into one no longer silently logs you into another with the wrong account name displayed.
- Fixed active plugins' own admin pages incorrectly highlighting the "Tools" sidebar section instead of the plugin's own top-level sidebar entry.
- Fixed active plugins' sidebar links reordering themselves depending on which plugin's admin page was currently open, instead of keeping a stable order.
- **Redirects plugin**: fixed the admin sidebar link and post-save redirects pointing to a broken URL (e.g. `/plugins/admin/index.php?...` or a doubled `/admin/admin/...`) on any install not living at the domain root — admin-side URL building now resolves the CMS root from the plugin's filesystem path instead of the request-dependent `getBaseUrl()`.
- **Redirects plugin**: fixed redirect destinations entered as a relative path (e.g. `/nouvel-article/`) sending visitors to the domain root instead of the CMS's own sub-directory — the stored destination is now resolved against the site's base URL before being sent in the `Location` header. Full external URLs (`https://...`) are unaffected.

---

## [1.3] — 2026-07-17

### Added
- **Plugin system** — SynaptikCMS now supports standalone plugins: self-contained folders in `/plugins` (each with a `plugin.json` manifest) that extend the site without modifying core files. Plugins hook into the existing theme API (`add_theme_action`, `apply_theme_filters`) for front-end behaviour, and into a new lightweight hook system for admin integration.
- **Extensions page** (Tools → Extensions) — lists every plugin detected at the CMS root, with one-click activate/deactivate. Activation state is stored in `plugins.json`.
- Active plugins can register their own entry in the admin sidebar, and render a full page inside the standard admin layout (sidebar, top bar, footer) via a new generic plugin page router — no plugin needs to reimplement the admin chrome.
- First plugin built on this system: **Booking**, a standalone appointment-booking module (separate download, not bundled with core) — public calendar with weekly recurring availability and date exceptions, per-type appointment durations, admin approval workflow (pending/confirmed/refused/cancelled), and automatic email notifications with `.ics` calendar attachments for both the client and the site admin, including optional phone-callback reminders.

### Changed
- Admin WYSIWYG editor: added a delete button to collapsible sections and tab-group tabs (hover the section header), retroactively applied to existing content on load
- Admin WYSIWYG editor: added a colour-picker button next to the delete button on collapsible sections and tab-group tabs, letting you change a section's accent colour instantly without reopening the source view

### New Plugins
- **Booking** - Standalone appointment booking module with calendar availability, admin approval workflow, and ICS calendar invites.
- **Newsletter** - Email newsletter signup with double opt-in and a manual article digest sender.
- **Redirects** - Manual 301/302 URL redirects, plus an optional 404-to-home fallback.
[Plugins downloadable here](https://synaptikcms.com/plugins/)

---

## [1.2.1] — 2026-07-11

### Changed
- Category pages now include content from sub-categories (any depth), instead of only exact category matches
- Tag and category merge dropdowns are now sorted alphabetically instead of following insertion order

### Fixed
- Fixed a critical bug where saving an article/page/project with tags could silently overwrite its own slug with the slug of the last tag entered, occasionally causing it to overwrite and delete an unrelated existing item that happened to share that slug
- Fixed a bug where double-clicking or double-tapping the Publish button could submit the form twice, creating a duplicate article/page/project with an auto-incremented slug
- Fixed category and tag orphan counts in the admin panel now account for pages, not just articles and projects, so the displayed orphan count matches what the purge action actually removes
- Fixed Template Editor triggering a "leave page" browser warning when saving a file
- Article and project cards now display resolved tag names instead of raw slugs 
- Admin WYSIWYG editor's HTML sanitizer is no longer stripping inline `<svg>` icons on save
- Axion theme: fixed display issues (`data-reveal` removed from grid containers whose size depend on number of items) and CSS fixes

---

## [1.2] — 2026-06-26

### Added
- Added a built-in translation editor: edit any locale's strings directly from the admin panel, and create new locales by duplicating en.json with one click — accessible from Tools → Translations and from the Settings → General language selector.
- Admin language can now be different from front-end language, both can be set from the Settings section.
- Replaced the CSS-only theme editor with a full Template Editor: browse and edit every file of the active theme (PHP, CSS, JS, JSON) from a single grouped file dropdown, with per-file backups and adaptive stats (lines, rules/variables for CSS, functions/comments for PHP & JS).
- New themes: [Downloadable Here](https://synaptikcms.com/themes/)
 - Theme SynaptikDocs
 - Theme Atrium
 - Theme Myelin
 - Theme Axion

### Changed
- Admin interface improvements: now fully responsive
- Modified sidebar to display flyouts for all sections when collapsed
- Editor sidebar is now collapsible to focus on content writing / edition 
- Modified admin tabs style to be more visible
- Modified Alt-text-assistant and Seo-overview to display tabs in the same style as settings
- Moved SEO Overview to the Tools section of sidebar
- Improved header scripts, added async to the JS files to improve load speed even more.
- Architecture Changes:
 - Tags are now stored as slugs in item files ("tags": ["my-tag"] instead of "tags": ["My Tag"]). Display names are resolved at render time from tags.json. Renaming a tag now only requires updating tags.json — no item files are touched when only the display name changes; item files are updated only when the slug itself changes.
 - Categories follow the same pattern: item files now store the category slug ("category": "anatomie" instead of "category": "Anatomie"). Display names and parent relationships are resolved from categories.json. Renaming a category only rewrites item files when the slug changes.
 - Creating a tag or category inline from the content editor (without going through the dedicated management pages) now automatically upserts the new entry into tags.json / categories.json, keeping the stores always in sync with actual content.
- Moved static assets (/css, /js) into /assets/css and /assets/js, and front-end locale files into /lang/front/ for a cleaner root directory structure.

### Fixed
- Fixed CSS display issue in file manager list view, which was hiding the folder name. Replaced folder icon with SVG that fits the admin design better.
- Fixed a tag duplication bug in the content editor where renaming a tag (e.g. "Themes" → "themes") would leave the old display string in existing item files, causing both versions to appear in the tag input suggestions.
- Fixed missing categories in data/categories.json: some were used by content items but absent from the store, causing getCategoryPath() to fail silently and breaking hierarchical URLs for those categories and their children.

---

## [1.1] — 2026-06-13

### Added
- Homepage SEO fields — dedicated meta title, meta description, keywords, OG title, OG description and OG image configurable from the SEO tab in settings; independent from the global site title and description
- Major overhaul and improvements of admin:
	- Added dark/light mode in admin
	- Top bar — A sticky admin toolbar is now displayed on the front end when logged into the admin, providing quick access to the dashboard, content editing, and site settings
	- Quick edit links added on item list table view hover
		- Added function to duplicate articles/pages/projects
	- Improved CSS styles: more modern, more uniform design, replaced all icons with more modern svg ones
	- Refactored all standalone files, now using common header.php and footer.php as templates, and layout.php
	- Social networks — Extended social media support from 5 to 18 platforms. 
		- New platforms available in Settings → Social Media and rendered as inline SVG icons in the footer: Bluesky, Discord, Mastodon, Pinterest, Reddit, Snapchat, Telegram, Threads, TikTok, Twitch, WhatsApp, X, YouTube. Legacy `twitter` value preserved for backward compatibility.
- New themes: [Downloadable Here](https://synaptikcms.com/themes/)
	- Theme: Vanta - added missing support for related content and custom fields
	- Theme: Nova - added missing support for related content and custom fields
	- Theme: Natura - added missing support for shortcodes, related content and custom fields
	- Theme: Mono - added missing support for custom fields, added styles for recent projects shortcodes
	- Theme: introduction of Prism theme, flat design, colorful, with dark/light switcher.

### Changed
- Sitemap generator: removed creation of page list
- Improved social media section display in settings

### Fixed
- Fixed batch-selection mode not displaying correct table headers in content lists
- Fixed infinite recursion crash (memory exhausted) when using theme live preview — `loadSettings()` was calling `resolve_admin_dir()` which re-entered `loadSettings()` before the request cache was written; fixed by reading `admin_dir` directly from the already-parsed settings array
- Fixed a bug where temp folders wouldn't get deleted after a database restore in `/bckps`
- Fixed [toc] anchor links now include the current page URL instead of resolving to the site root
- Fixed `_shortcode_parse_attrs()` undefined index on empty quoted attribute values (e.g. url="") 
- Fixed settings fields (site_description, meta fields): were double-encoded on each save due to htmlspecialchars() applied before JSON storage — values are now stored raw and encoded only at display time
- Fixed CSS display issue in search overlay where the clear button was misplaced
- Fixed canonical URL generation to always output a normalized, trailing-slash URL via cleanUrl() instead of $_SERVER['REQUEST_URI'], resolving duplicate-content canonical conflicts reported by Google Search Console
- Theme: Portfolio - fixed several display issues and missing CSS rules, galleries display
- Theme: Nova - fixed several display issues and missing CSS rules
- Theme: Natura - fixed several display issues and missing CSS rules, galleries display
- Theme: Vanta - fixed gallery displays

--- 

## [1.0] — 2026-06-05

### Added
- **Automatic updates** — update notification banner in dashboard; one-click update downloads the release ZIP, validates its structure, creates a safety backup, and replaces core files without touching content, settings or uploads
- **Full backup and restore** — ZIP backup of `/data/`, `/files/` and `settings.json`; restore from any backup with automatic pre-restore safety snapshot; server backups table with download and delete
- **Related Content** — per-post manual selection or automatic suggestions based on shared tags and categories; toggle display per post
- **Alt Text Assistant** — centralised interface to audit and bulk-edit alt text and captions across all gallery images
- **SEO Overview** — content audit table showing meta title and description completion status across all articles, pages and projects with inline editing
- **Theme Manager** — list installed themes with preview image, activate and delete; theme upload via ZIP with `theme.json` validation
- **Live theme preview** — preview any installed theme in the current site context without activating it; signed HMAC token, 2-hour TTL, admin-only
- **CSS editor** — live-edit the active theme stylesheet from the admin; automatic backup before each save; restore from any backup
- **RSS feed** — auto-injected in theme `<head>` via `render_header_scripts()`; no user action required
- **hCaptcha** — anti-spam protection for the contact form; gracefully disabled when keys are not configured
- **Password reset by email** — one-time token link sent to admin email; 15-minute TTL; public route via `?reset_token=` to avoid exposing the admin folder name
- **User account name** — display name shown in admin sidebar; separate from login username
- **Custom Fields** — define additional fields per content type (text, textarea, number, URL, checkbox, select); available in editor sidebar and theme via `$item['custom_fields']['key']`
- **Autosave** — configurable interval (1, 3, 5 or 10 minutes); JSON drafts preserved across sessions; one-click restore
- **Scheduled publication** — set a future date and time; cron-free, checked on front-end request
- **Markdown editor** — CodeMirror-based; per-post format switch (WYSIWYG ↔ Markdown); content preserved on switch
- **Pagination** — server-side pagination and AJAX search/filter for the admin content list beyond 200 items
- **Timezone setting** — configurable in settings; applied globally to all PHP `date()` calls
- **Robots.txt editor** — edit `robots.txt` directly from the admin SEO tab
- **Shortcode builder** — modal UI in WYSIWYG editor to insert shortcodes without typing syntax
- **Menu Builder** — drag and drop custom navigation; nested items up to 2 levels; external links; open in new tab
- ~120 new i18n keys across `lang/admin/en.json`, `fr.json`, `es.json`

### Changed
- Split monolithic `template-functions.php` into `tf-cards.php`, `tf-markdown.php`, `tf-navigation.php`, `tf-page.php`, `tf-shortcodes.php`
- `backup-dl.php` rewritten to support `.zip` and `.json`, with path traversal protection
- Admin editor sidebar reorganised into tabs: Content, SEO, Custom Fields

### Fixed
- SEO preview JS failing on Markdown-format posts
- Date format display inconsistency in content list
- Security hardening on sensitive folder access and CSRF validation in theme upload form

---

## [0.9] — 2026-05-01

### Added
- Markdown support in the content editor (CodeMirror)
- Timezone selector in settings; publish time displayed in admin content lists and on posts
- Server-side pagination and AJAX search for admin content list (triggered above 200 items)
- RSS feed auto-injected in theme headers
- User account name (display name separate from login credentials)
- Custom Fields for all 3 content types — defined in settings, rendered in editor sidebar
- User-defined autosave intervals in settings
- Related Content — manual or algorithmic, toggle per post
- Live post preview working with both HTML and Markdown

### Changed
- Admin editor redesigned with sticky top bar; publish button always visible
- Editor sidebar improved with tabs separating Content, SEO and Custom Fields sections
- All default themes updated to support custom fields, site logo/favicon, RSS feed, related content

### Fixed
- SEO score preview JS broken on Markdown posts
- Date format display in content list
- Security vulnerabilities in sensitive folder access
- CSRF validation added to theme upload form

---

## [0.8] — 2026-02-12

### Added
- Split-file data architecture — each content item stored as its own JSON file in `/data/{type}/` instead of a single monolithic JSON database
- Custom article and project summaries for listing cards (replaces auto-generated excerpt)
- Support for page templates in Pages content type
- Theme partials system — override article and project card rendering per theme
- Theme `functions.php` — auto-loaded after core; register hooks, shortcodes or custom behaviour
- Categories and tags: merge two into one, filter and purge orphans
- Update notifications and news feed in admin dashboard
- First-run installer (`install.php`) — sets site title, language, admin credentials and admin folder name

### Changed
- Content index rebuilt as lightweight per-type `_index.json` files; single-item pages load exactly one JSON file
- Article card excerpts: raw shortcode syntax replaced with `…` or custom summary when present

### Fixed
- Raw shortcode tags leaking into article card excerpts on list pages

---

## [0.7] — 2025-08-10

### Added
- Secure password reset via emailed one-time link (15-minute TTL)
- Theme management interface with preview image and activate button
- Admin sidebar popovers for collapsed icon-only mode

### Changed
- Admin login and change-password hardened (rate limiting, lockout after failed attempts)
- Admin sidebar revamped — retractable, icon-only collapsed state
- Media Manager interface redesigned
- Settings page redesigned with tabbed layout

---

## [0.6] — 2025-04-20

### Added
- Menu Builder — drag and drop custom navigation, nested items, external links
- Backup and restore for the JSON database
- Content display options (show/hide title, featured image, date, breadcrumbs per post)
- SEO panel improvements — personalised score and recommendations in the content editor

### Changed
- Admin sidebar collapsible to icons-only mode
- SEO recommendations algorithm revised for accuracy

---

## [0.5] — 2025-03-15

### Added
- Categories and tags for Articles and Projects
- Category and tag picker in the content editor
- Full-screen editing mode
- HTML source code editor (raw mode toggle in WYSIWYG)
- Shortcodes engine — `[gallery]`, `[toc]`, `[callout]`, `[contact_form]`, `[recent_articles]`

### Changed
- Complete admin interface overhaul — more compact, consistent design language
- Content creation interface redesigned — sidebar layout, less visual noise

---

## [0.4] — 2025-01-22

### Added
- Image galleries per post — grid, masonry, justified, carousel layouts
- Lightbox for gallery images
- Media Manager — upload, browse, delete, rename files; folder creation
- Image optimisation on upload (resize, compress, optional WebP conversion)
- Batch image optimiser for existing media library
- Open Graph and Twitter Card meta tags
- JSON-LD schema markup

### Changed
- Theme system extended with hooks (`add_action`) and filters (`add_filter`)
- Theme API helper functions consolidated into `theme-api.php`

---

## [0.3] — 2024-11-08

### Added
- Projects content type (portfolio) with separate listing and single-item templates
- Theme system — active theme selected in settings, templates loaded from `/theme/{name}/`
- Ships with `default` theme
- Sitemap generator (`sitemap.xml`)
- Canonical URL tag in `<head>`
- `robots.txt` included in release package
- i18n system — front-end and admin panel, locale files in `/lang/` and `/lang/admin/`
- English, French and Spanish locale files

### Changed
- Admin panel redesigned with sidebar navigation replacing top-bar tabs

---

## [0.2] — 2024-08-30

### Added
- Pages content type with standalone routing (no type prefix in URL)
- SEO panel in content editor — meta title, description, keywords
- Custom URL slug per content item
- Breadcrumb navigation (optional, toggled in settings)
- Clean URL routing via `.htaccess` rewrite rules and `parseRequestUri()`
- Category listing pages at `/category/{slug}/`
- Tag listing pages at `/tag/{slug}/`
- Search overlay — `Ctrl+K` / `Cmd+K`, searches across all content types, no external dependency
- Contact form shortcode with server-side processing and CSRF protection
- `install.php` first-run wizard (site title, admin password)

### Fixed
- Slug collision on content creation — auto-appends numeric suffix

---

## [0.1] — 2024-06-14

### Added
- Initial working version
- Articles content type with title, content, date, featured image, published flag
- Flat-file JSON storage in `/data/`
- Basic admin panel — login, list, create, edit, delete articles
- WYSIWYG editor (custom implementation)
- Front-end routing — homepage, article list, single article
- `getBaseUrl()`, `cleanUrl()`, `sanitizeSlug()` core helpers
- `.htaccess` security rules blocking direct access to `/data/` and `/bckps/`
- MIT license