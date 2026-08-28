# Changelog

All notable changes to SynaptikCMS are documented here.  

## [1.4.1] — 2026-08-27

### Added

- **New "Starter" theme** — a minimal, fully working theme included out of the box, meant as a clean starting point for anyone building a custom theme from scratch.
- **Light/dark logo variants** — themes with a dark/light mode toggle can now show a different site logo for each. Add a `-light` and `-dark` version of your logo file with the same name (e.g. `logo-dark.webp` / `logo-light.webp`) and it switches automatically, with no flash.
- **Theme customizations that survive updates** — create a `theme/child_theme/{your-theme}/` folder with just the files you want to change (a template, extra CSS on top of the theme's own, custom PHP functions), and it takes priority automatically. A theme update only ever touches the original theme's folder, so hand-made customizations placed here are never overwritten.
- **Full-page cache for anonymous visitors** — a page is now rendered once and served instantly to every following visitor until its content, settings, or theme actually change.
- **Preview a draft or unpublished item at its real URL** — the "view online" button in the content editor (next to the custom slug) now works even before publishing: as the logged-in admin, you see the item exactly as it will look live, with a small badge reminding you it's not visible to anyone else yet. Visitors still get exactly what they got before (a 404, or the category page).

### Changed

- **The editor's "Preview" button now shows the real page** — now saves your current changes and opens the item's actual URL, using the exact same rendering as visitors will see (only you can view it before publishing — see above).
- **Faster page loads** — search, the image lightbox, and collapsible/tab content blocks now only load their supporting script on pages that actually use that feature, instead of loading all of it on every page regardless of need.
- **Simplified stylesheet loading** — more reliable loading, just as fast.
- **Leaner plugin widgets** — pages using Booking, Comments, Form Builder, Newsletter, or Cookie Consent no longer load a small internal helper script multiple times over; it now runs once and picks up each widget's data automatically.
- **One less script on themes that never used it** — now only shipped by the built-in themes that actually use it, folded into each one's own script instead of a separate request.
- **Full-page cache check is now instant regardless of site size** — checking whether a cached page is still valid used to re-check every article, page, and project file on every single request, which could grow slower with large sites. It now checks a single marker that's updated only when content actually changes, so the cache check costs the same on a 10-article site as a 5,000-article one.

### Fixed

- **Social footer links** — icon-only links to social profiles had no accessible name for screen readers, and links opening in a new tab were missing a security attribute recommended for external links. Both fixed.
- **Page language attribute switches automatically** — the page's declared language (used by screen readers, browsers, and search engines) never followed your site's actual language setting, even after switching to another language. Fixed.
- **Safety backup when manually re-uploading a theme** — reinstalling or updating a theme by uploading a ZIP by hand overwrote the existing theme with no backup, unlike the automated update path. It now backs up the existing theme first, matching the automated flow.
- **Menu builder links update themselves when you rename the type** — any link left with its default name (i.e. you didn't type a custom label for it) follows the rename automatically. A link you gave a custom label to (e.g. "Features" instead of "Articles") is never touched. The link's actual destination now follows the rename too.
- **Renamed content types reflected everywhere** — if you renamed "Article" or "Project" to something else in Settings → Reading, the search panel's filter checkboxes and result badges kept showing the original English word instead of your custom name. Fixed across the search UI and all affected built-in themes.
- **Renaming a content type when singular and plural are the same word** — Settings → Reading refused to save if a type's singular and plural name matched (common when a language has no distinct plural form, or you just want one name for both). Fixed.
- **Admin confirmation modals appearing too high on the page** — now centers correctly.

### Security

- **Backup downloads check for admin role** — an Editor or Author with a guessed or discovered backup filename could download a full site backup, including `config.json` and its secrets. Now restricted to Admins, matching the page that links to it.
- **Removing or demoting a user ends their existing session** — a deleted or downgraded account kept its old access for up to two hours instead of losing it immediately. Role and access are now re-checked on every request.
- **Rich content could carry a disguised full-screen overlay via inline styles** — content sanitization now strips a `style` attribute if it contains anything capable of that (fixed/absolute positioning, `url(...)`, `expression(...)`), while still allowing normal styling like colors and alignment.
- **A theme ZIP could disguise an executable file with a trailing space in its name** (e.g. `shell.php .jpg`) to slip past the forbidden-extension check. Closed.
- **`llms-full.txt` was unreachable on nginx** — only Apache had been updated to serve it; the nginx example configuration now matches.
- **Draft/unpublished preview at a live URL didn't expire with the session** — it kept working for a stale admin session past the 2-hour inactivity window, even after the admin panel itself would have logged it out. Now enforced consistently.

---

## [1.4.0] — 2026-08-24

### Added

- **Multi-user accounts with roles** — the admin panel now supports several accounts, each with Admin, Editor or Author permissions.
- **Trash** — deleted articles, pages and projects go to a trash bin instead of disappearing for good. Restore anytime, or let it clear automatically after 30 days.
- **Revisions** — every update saves the previous version automatically. Review changes and restore an earlier version anytime.
- **Alt-Text Assistant now covers every image** — including featured images and images pasted directly into content, not just galleries.
- **Plugin content filters** — plugins can now modify content, meta tags, menus and saved data, not just add admin pages.
- **llms-full.txt** — a new file exposing full site content to AI tools, alongside the existing llms.txt.
- **Dashboard traffic widget** — if Analytics is active, the Dashboard now shows a quick traffic overview.
- **Dashboard appointments widget** — if Booking is active, the Dashboard now shows upcoming and pending appointments.
- **Dashboard comments widget** — if Comments is active, the Dashboard now shows how many comments are waiting for moderation, with the most recent ones.
- **Dashboard subscribers widget** — if Newsletter is active, the Dashboard now shows confirmed and pending subscriber counts.
- **Show or hide each Dashboard widget** — Analytics, Booking, Comments and Newsletter each have a switch in their own Settings tab to keep their widget off the Dashboard without turning the plugin off.
- **New date format option** — added DD.MM.YYYY.
- **Activity log** — a new page under Tools tracks sensitive admin actions (logins, template edits, extension installs, user changes, restores) with who, when and where from.
- **System Information page** — moved to its own page under Tools, with more checks included.
- **Pin a plugin to the sidebar** — choose which plugins get a shortcut in the sidebar, instead of showing them all.
- **Your role on the Account page** — your account page now shows whether you're an Admin, Editor or Author.
- **Author name on content** — on sites with more than one user, you'll now see who wrote each article, page or project in the content list and on the dashboard.
- **Import a locale from a ZIP** — on the Translations page, upload a language pack (admin + front translations) instead of building it by hand or uploading directly on your server.
- **Rename content types** — Settings > Reading now lets you rename Articles, Pages or Projects (e.g. "Posts" instead of "Articles" for a blog, "Recipes" or "Portfolio" instead of "Projects" for a food site or an artist website), including their public URL. Previously only possible through the translation editor, undiscoverable.
- **Delete individual revisions** — remove a single saved version from an item's history, right from the revision diff view or the revision list.
- **Markdown editor now supports text color** — color a word or phrase without switching to the visual editor.
- **Clear admin cache button** — forces a fresh update check without needing FTP access.

### Improvements

- **Admin dark mode** — now follows system's preference by default.
  - **Admin dark mode reworked** — the page background is deeper while panels, cards and tables sit clearly above it, so sections read as distinct blocks. Text fields, textareas and dropdowns are now darker than the panel holding them, which makes them stand out instead of blending in. Alert colours and secondary text were adjusted in step so nothing looks washed out.
  - **No more highlight when the mouse passes over a panel** — settings sections, editor panels, stat cards and plugin panels no longer change colour on hover, which was distracting on pages full of them. Table rows and lists keep their hover highlight.
- **Faster admin pages on sites with lots of comments** — the comment counts shown in the sidebar and on the Dashboard are now read once per page instead of re-scanning every comment file several times.
- **Images in content load as you scroll** — pictures inside an article body are now deferred until needed, while the site logo and the featured image keep loading immediately so the top of the page appears just as fast.
- **CSP hardening** — the pre-paint theme boot snippet in all themes is now allowlisted by a single SHA-256 hash instead of `'unsafe-inline'`. All bundled themes use the same byte-identical snippet so one hash entry covers every theme. Updated `.htaccess` to use the correct hash.
- **Theme content filters** — theme authors' content filters are now correctly applied.
- **Faster, lighter admin panel** — a number of behind-the-scenes improvements to loading speed and code organization, with no visible change to how anything looks or works.
- **Tools menu** — reorganized into clearer groups.
- **Content editor** — simplified layout, redundant help text removed, buttons moved around for ergonomics and visual clarity.
- **Custom fields from the editor** — you can now create a new custom field directly while writing an article, without going to Settings first.
- **Refreshed admin icons** — more consistent look across the whole admin panel, including the editor toolbar icons.
- **Tidier content list table** — reorganized information, visually clearer.
- **Admin fonts** — switched from Google Fonts to Bunny Fonts (privacy and GDPR-friendly font provider).
- **Plugins page redesigned** — a simpler list with on/off switches and each plugin's own icon, instead of a card grid.
- **Sidebar plugin shortcuts** — only pinned plugins show up in the sidebar now, keeping it tidy (see "Pin a plugin to the sidebar" above).
- **Faster, lighter public pages** — code blocks in content are now highlighted by a proper, well-tested library instead of a hand-built one, and it only loads on pages that actually have a code block.
- **Image picker window in Settings** — now matches the look and feel of the image picker window used elsewhere in the editor.
- **Improved category and tag selection in the editor** — replaced the "Browse all" button and expanding list with a compact search field, matching the related content picker. Type to filter, click to select, or type a new one and press Enter.
- **Editor toolbar** — the Heading 1/2/3 buttons are now a single dropdown to save space, and the button groups are more clearly separated.
- **Drafts are real content from the start** — start a new article, page or project and it's saved as a proper draft the moment autosave kicks in: a permanent link, its own edit history, and a spot in the trash if you delete it, just like anything else. No more separate, fragile "in-progress" state that could get lost. A Status filter in the content list helps you find drafts, scheduled items and unpublished content.
- **"Select all visible" checkbox** added to the content list's batch-selection header — ticks every visible row at once instead of one by one.
- **Dashboard redesign** — more useful at a glance, less clutter. Also integrates widgets from plugins.

### Fixed

- **Style updates not reaching visitors** — the bundled system stylesheet was cached in the browser for up to a year with no way to refresh it, so fixes to gallery, search or shortcode styling never arrived for anyone who had already visited the site. It now updates as soon as it changes.

- **Content safety hardening** — closed a gap where certain hand-written HTML in an article could carry active code through to the page. Content is now cleaned the same way whichever formatting mode it was written in.

- **Internal links written by hand in HTML mode** — a link pointing to another page on your own site (for example `/articles/my-post`) was silently turned into a dead link when the article was saved. Fixed, and link options like opening in a new tab are no longer dropped.

- **Comment rate limiting** — a burst of comments submitted at the exact same moment could slip past the hourly limit, and the file tracking it grew forever instead of clearing out old entries. Both fixed.

- **File manager pop-up messages** — could sometimes appear blank. Fixed.

- **Menu Builder links on translated sites** — links to content lists could lead to a broken page if the site isn't in English. Fixed.

- **Update safety backup** — updates now also back up core files, not just content and settings, so you can always roll back if something goes wrong.

- **Update failure reporting** — a failed update now tells the user exactly which files failed.

- **Installer reliability** — fixed a case where a failed setup step could leave the site in a broken state.

- **Synaptik Docs theme blocked by strict Content-Security-Policy setups** — two small scripts controlling dark mode and sidebar state were inline, which some server CSP configurations reject. Moved to an external file so they load cleanly everywhere.

- **hCaptcha settings help text** — removed placeholder text shown by mistake.

- **Markdown callout blocks** — fixed a formatting bug that could break the page layout around them.

- **Notification and warning messages** — some success/error/warning messages and confirmation pop-ups across the admin panel had a see-through background, making them hard to read. Now solid and readable everywhere.

- **Login, reset and forgot-password pages** — login box now centered vertically regardless of window size, instead of sitting near the top.

- **Search overlay silently showing no results when added to a theme manually** — the `render_search_ui()` helper generated HTML that no longer matched the structure the search script expects, so results never appeared. Fixed.

- **Booking plugin — email template editor toolbar** — was missing its icons after a recent editor refresh. Fixed.

- **Settings sidebar highlight** — switching between Settings tabs didn't update which one was highlighted in the sidebar. Fixed.

- **Empty autosave revisions** — autosaving a draft with no actual changes since the last save no longer creates a pointless "no changes" revision.

- **Clear Cache / Clear Admin Cache buttons** — now clear the cache correctly and confirm with a success message.

- **Various fixes and code optimizations** throughout the CMS.

- **Schema.org JSON-LD** — the Schema Type field in the SEO panel now generates a real `<script type="application/ld+json">` block in the page `<head>` for articles, pages and projects. Includes headline, URL, dates, featured image, description, author, and publisher with logo. Two new settings under Settings > SEO let you set the default author name and publisher type (Person or Organization).

- **Batch delete count** — the selected item count in the batch delete button was showing roughly double the actual number of selected items. Fixed.

### Security

- **Stronger script-loading protection (Content Security Policy)** across the admin panel and the public site, making script-injection attacks harder.
- **Subresource Integrity restored** — external scripts are properly verified again.
- **Missing protection added to several actions** — creating or editing content, duplicating an item, restoring a draft are now protected against forged requests, matching the rest of the admin panel.
- **Analytics plugin privacy improvement** — visitor IP addresses are now hashed with a unique key per installation instead of a shared one.
- **Removed unused code with a security risk** — a leftover upload code path no longer used by the editor.
- **"Forgot password" timing fix** — removed a subtle delay difference that could reveal whether an email address has an account.
- **Settings page text handling fix** — fixed a rare case where certain characters in a translation could break the page.
- **Plugin file protection** — a few plugins were missing standard protection for their internal files on some servers. Added across all of them.
- **Markdown content sanitization** — content written in Markdown could previously contain unsafe embedded code that was saved and shown as-is. It's now checked and cleaned just like the regular editor's content.
- **Autosave and preview sanitization** — a change that was only autosaved, or only previewed, could skip this safety check before. It's now applied everywhere content is saved or shown, not just on the final Publish.

### Plugins

- **Maintenance Mode bypass fixed** — certain URLs could accidentally bypass Maintenance Mode.
- **Booking reliability** — fixed a rare case where two people could book the same slot at the same time.
- **Rate limiting improved** — Booking, Newsletter and Form Builder now handle rapid repeated requests more reliably.
- **Plugin login sessions** — all plugins now respect the same automatic-logout timeout as the rest of the admin panel.
- **Newsletter confirmation links now expire** after 7 days, for better security.
- **WP Importer content safety** — imported content is now cleaned of potentially unsafe code.
- **Analytics referrer stats fix** — fixed a bug that could mangle some website names in traffic stats.

### Themes

Updated to take advantage of version 1.4.0's improvements :

- Backlinks updated to display renamed content-types instead of harcoded "Articles", "Pages" or "Projects".
- Updated to reflect new security features.
- **Optimized for page load speed:** all themes load even faster than before.

---

## [1.3.6] — 2026-08-16

This update makes the CMS more secure and the admin much faster.

### Added

- **Nginx sample config** — sample `nginx.conf.example` for nginx servers: directory deny rules for sensitive paths, security headers (CSP, X-Frame-Options, etc.), static asset caching, and plugin data directory protection (`.htaccess` has no effect on nginx).
- **Third-party scripts** — added integrity checks on all externally loaded libraries. Also fixed a missing CDN entry in the Content Security Policy that was silently blocking the template editor.

### Changed

- **PHP minimum requirement** — raised from 7.4 to 8.3.

### Fixed

- **Markdown editor** — "open in new tab" links were broken in Markdown mode.
- **Admin content list** — on large sites the list was loading the full body text of every item on every page load, causing 10+ second load times. Now instant regardless of item count.
- **Admin sidebar** — sidebar layout was compressing when many items were visible (e.g. multiple plugins installed + accordion open); sidebar now scrolls correctly without visual glitches on desktop.
- **llms.txt** — returned a 403 error on Apache installs.

### Security

Thanks to [@treeandcoffee](https://github.com/treeandcoffee) for the continued security review.

- **ZIP validator** — uploaded archives (themes, plugins, backup restores) are now validated before extraction: path traversal, null bytes, absolute paths, and dangerous extensions are all rejected. The backup restore path and automatic extension updates had no validation at all in previous versions. Download URLs for automatic updates are also restricted to known hosts.
- **Output escaping** — introduced `hsc()`, a project-wide escaping helper enforcing `ENT_QUOTES` and `UTF-8` consistently. Migrated the entire admin panel, including the two unauthenticated pages (password reset and forgot password).
- **Subresource Integrity** — all externally loaded libraries now include integrity hashes.
- **Plugin directory** — the plugins folder no longer exposes its contents on servers with directory listing enabled.
- **PHP minimum version** — raised to 8.3. The previous stated minimum (7.4) was broken in practice since 1.3.5: profile saving and extension updates would crash silently on PHP 7.4.
- **Theme loading** — a malicious theme could redirect template loading to an unintended file. Fixed.
- **File cache** — the internal cache used a format vulnerable to PHP object injection. Replaced with JSON.
- **File manager** — the host header used to build file URLs was not sanitised, allowing header injection.

---

## [1.3.5] — 2026-08-14

### Security

- **Security audit** — full audit of version 1.3.4.4 conducted by [@treeandcoffee](https://github.com/treeandcoffee). Full credit in `SECURITY.md`.
- **Password reset** — the reset link could be exposed in the browser if the server's mail function fails, and the reset email could be hijacked via a forged Host header.
- **File manager** — files could be renamed to dangerous extensions or moved outside the upload directory. Reported by [@treeandcoffee](https://github.com/treeandcoffee) and [Dinesh Goud](https://github.com/d1n3sh-0x3).
- **Template editor** — a forged request could overwrite active theme files while an admin was logged in.
- **Admin credentials** — a backslash in the display name field could corrupt the credentials file and lock out the admin.
- **Front-end routing** — a crafted URL could be used to read arbitrary data files.
- **Admin actions** — delete and purge actions could be triggered without a valid security token.
- **AJAX endpoints** — file upload, autosave, and several other endpoints accepted requests without security token validation.
- **Session timeout** — several admin pages bypassed the 2-hour inactivity timeout.
- **Session cookies** — security flags were only set via Apache config, with no effect on PHP-FPM servers. Now enforced in PHP directly.
- **Login rate limiter** — concurrent login attempts could bypass the lockout counter.
- **Search endpoint** — no rate limiting; all content was loaded on every request regardless of what was searched.
- **Markdown links** — link labels and footnote keys were not fully escaped; `javascript:` URLs in links are now blocked.
- **Data directories exposed on nginx** — the dashboard now warns when `/data/` is publicly accessible (common on nginx without manual config).

### Added

- `llms.txt` support (spec: https://llmstxt.org/). Accessible at `/llms.txt`. Lists published pages and articles for LLM indexers. Thanks to [@treeandcoffee](https://github.com/treeandcoffee) for the suggestion.
- `site_url` saved to `config.json` at install time for reliable password reset links. Existing installs can populate it via `migrate.php`.

### Fixed

- **Content preview** — broken after the 1.3.5 security fixes due to a function redeclaration.
- **Tag and category pages** — were falling through to the homepage after a routing fix applied in the same release.
- **Draft delete buttons** — stopped working after CSRF hardening was applied.
- **Duplicate locale string** — `extensions_no_plugins` was defined twice in `lang/admin/en.json`.
- **File manager upload panel** — expanded horizontally during uploads, pushing the folder panel off-screen.
- **Core updater** — `theme/default/` is no longer overwritten during a core update.
- **Extension updater** — `theme/default/` can now be updated via the Extensions Manager. (Thanks [@treeandcoffee](https://github.com/treeandcoffee).)

--- 

## [1.3.4.4] — 2026-08-12

### Added

- **Markdown footnotes** — `[^1]` markers in content now render as superscript links that jump to numbered footnote definitions at the bottom of the article. Inline superscript syntax also added: `^text^` → `<sup>text</sup>`.

### Fixed

- **Markdown footnotes** — footnote definitions inside fenced code blocks were incorrectly consumed by the footnote parser, causing them to disappear from rendered code examples. (`core/render/tf-markdown.php`)
- **synaptik-docs theme v1.1.4 — TOC scroll-spy** — three bugs fixed: headings without the `[toc]` shortcode had no anchors and weren't tracked; duplicate heading titles caused incorrect highlighting; scroll position detection was unreliable on some layouts.
- **Booking plugin** — PDF recap: long values now wrap correctly; title and submission date were overlapping.
- **Booking plugin** — HTTP 500 on form submission caused by a missing core file reference left over from the v1.3.3 restructure.
- **Booking plugin** — Several email improvements: cancellation link now on its own line; emails now sent as HTML; dates formatted in the site's active language; `[button]` shortcode now works in email templates and accepts a `color` attribute.
- **Search overlay** — search requests returned 404 errors on sites with non-standard URL structures. Base URL is now injected directly by the CMS instead of being reconstructed from the current URL.
- **main.js** — a variable name conflict caused a JavaScript crash when another script on the page used the same variable name. Fixed by wrapping the file in an IIFE.
- **Markdown editor** — shortcode picker now includes all available shortcodes, matching the WYSIWYG editor.

## [1.3.4.3] — 2026-08-11

### Fixed

- **Installer** — fresh installs had a broken search endpoint and RSS feed due to an overly restrictive `.htaccess` in `/core/`.
- **Installer** — language dropdown only showed English; now lists all available locales.
- **Installer** — selected language is now applied to both the front end and admin panel.

## [1.3.4.2] — 2026-08-10

### Added

- **Markdown nested lists** — lists now support arbitrary nesting depth with mixed ordered/unordered levels.

### Changed

- **Analytics plugin v1.0.2** — overview chart can now switch between Page Views and Unique Visitors; selected time range is remembered across tabs.
- **Installer** — font switched to Inter via Bunny Fonts; password fields now have a show/hide toggle; install notes clarified.

### Security

- Hardened `.htaccess` protection across core directories, admin sub-directories, and plugin `data/` and `private/` folders. Plugin directories now always rewrite their `.htaccess` on activation, closing a gap where a manually recreated directory would be left unprotected.

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
