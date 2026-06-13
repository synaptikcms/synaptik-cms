# Changelog

All notable changes to SynaptikCMS are documented here.  

---

## [1.1] — 2026-06-13

### Added
- Homepage SEO fields — dedicated meta title, meta description, keywords, OG title, OG description and OG image configurable from the SEO tab in settings; independent from the global site title and description
- Major overhaul and improvements of admin:
	- Added dark/light mode in admin
	- Top bar — A sticky admin toolbar is now displayed on the front end when logged into the admin, providing quick access to the dashboard, content editing, and site settings
	- Quick edit links added on hover on item list table view
		- Added function to duplicate articles/pages/projects
	- Improved CSS styles: more modern, more uniform design, replaced all icons with more modern svg ones
	- Refactored all standalone files, now using common header.php and footer.php as templates, and layout.php
	- Social networks — Extended social media support from 5 to 18 platforms. 
		- New platforms available in Settings → Social Media and rendered as inline SVG icons in the footer: Bluesky, Discord, Mastodon, Pinterest, Reddit, Snapchat, Telegram, Threads, TikTok, Twitch, WhatsApp, X, YouTube. Legacy `twitter` value preserved for backward compatibility.
	
	- Added 
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