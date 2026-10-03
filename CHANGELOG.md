# Changelog

All notable changes to Synaptik CMS are documented here.  

## [1.4.6] — 2026-10-03

### Added

- **Sitemap Generator** - Added manual URL exclusion list from sitemap.
- **IndexNow** - Regenerating the sitemap now tells Bing, Yandex, Seznam and Naver which pages changed.
- **Self-hosted Scripts** - Gallery, code highlighting and editor scripts are now bundled with the CMS, so visitor IP addresses are no longer sent to outside CDNs.
- **Force HTTPS** - New setting (also offered by the installer) redirects visitors to HTTPS and enables HSTS.
- **WYSIWYG Editor** - New Parapraph button in the WYSIWYG toolbar; clicking an active heading level again reverts it to a paragraph.
- **Custom Content Types** - New admin screen under Content Types lets you register content types beyond Article/Page/Project; each one gets its own URLs, admin listing and a generic front-end template automatically. Each custom content type has its own editable labels and URL segments (single item and list, so plurals work in any language), independent of its internal identifier and validated for conflicts; the identifier itself can also be renamed, with its content, menus and custom fields following.
- **Conditional Shortcode/Gallery/Lightbox Assets** - The shortcode, gallery/collapsible and lightbox stylesheets and scripts now load only on pages that actually use them, instead of on every single page.
- **Sitemap Auto-update** - Publishing, unpublishing, deleting or restoring content now regenerates it automatically, and the `Sitemap:` line is added to `robots.txt` if it is not already there.

### Changed

- **Data Layer Split Into Modules** - `data-layer.php` and `admin-data-layer.php` (large files) are now thin loaders over focused modules (cache, storage, trash, revisions, content types, etc.).
- **Category-Path Routing Simplified** - The router's category-chain matching for articles and pages collapsed four near-identical live/preview lookup loops into one shared pass, with identical matching priority.
- **Admin Functions Split Into Modules** - `admin-functions.php` (1300 lines, a mix of users, theming, formatting and progress helpers) is now a thin loader over focused modules.
- **Admin Content Handlers Separated From Routing** - The functions that save and update content were mixed into the same file that dispatches admin actions to templates; they now live in their own file.
- **Explicit Admin Template Variables** - Admin templates received their data by inheriting whatever variables happened to be in scope where they were included, with no visible contract of what each one actually needs. Every template invoked from the content admin now receives its variables explicitly through a single render helper.
- **CSS Token Naming Consolidated** - Niche search/gallery CSS custom properties (`--search-accent`, `--search-radius`, `--color-background-secondary`, etc.) now fall back to the equivalent general-purpose token (`--color-primary`, `--border-radius-sm`, `--bg-accent`...) before their own hardcoded default, so a theme only needs to define the general tokens to get search and gallery styling right too.
- **Output Escaping Unified** - Over a thousand scattered HTML-escaping calls across the core, admin and plugins now go through one shared helper, so escaping behaves the same everywhere; rendered output is unchanged.

### Fixed

- **Bing Sitemap Ping Did Nothing** - Bing shut that endpoint down in 2022 but the CMS still reported success; IndexNow replaces it.
- **Menu Builder Category Links** - Now use localized category URL.
- **Search Overlay CSS** - checkboxes now display centered check signs.
- **Admin Editor Formatting** - Emptying a heading line now reverts it to a paragraph instead of leaving an empty heading tag; triple-click replace and heading merges no longer carry heading styles over.
- **Admin Content List Cards View** - Status badge no longer stretches full width; date, category and tag chips share one height with consistent spacing.
- **Admin Content List Categories** - List view now shows categories as badges, matching the card view.
- **Duplicate Content URLs** - A categorized article, page or project was reachable at two different URLs (its type-prefixed URL and its category-path URL) with no redirect between them, hurting SEO. The non-canonical URL now 301-redirects to the canonical one.
- **Canonical Tag Category Mismatch** - The `<link rel="canonical">` tag for a categorized article or project was built from the requested URL's category instead of the item's own, so it could point back at the non-canonical URL the 301 redirect above had just eliminated.
- **`og:url` Could Disagree With The Canonical URL** - It was built separately from the raw request instead of reusing the canonical URL, so a subdirectory install got it doubled and a category or tag archive could get the wrong page's address; it now always reuses the same canonical URL.
- **In-Page Anchor Links Broken By `<base href>`** - Since every page sets a site-root `<base href>`, a content link such as `href="#section"` resolved to the homepage instead of scrolling down the current page. Such links are now prefixed with the current page's URL, the same fix the `[toc]` shortcode already applied to its own links.
- **Translation Payload On Every Page** - Now only needed the keys are sent, cutting roughly 12 KB from every page load.
- **Contact Form Rate Limit Race Condition** - The submission counter was read and written in two separate steps, so near-simultaneous submissions from the same IP could both slip through under the limit. The check and the counter update are now a single atomic operation.
- **llms.txt Routing Fragility** - `/llms.txt` and `/llms-full.txt` depended on specific `.htaccess`/nginx rewrite rules. Routing now happens in `index.php` itself from the request path, so it survives a server config rewrite.
- **`llms.txt` Site Description** - The Agent Instructions block always introduced the site as "a flat-file PHP CMS" and linked to SynaptikCMS's own docs/themes/plugins/GitHub, regardless of what the site was actually about. It now describes the site's own title and description.
- **llms-full.txt Raw Markdown** - Full-text export now rendered the same way a real page is before being converted to plain text.
- **JSON Write Race Condition** - Every JSON write used two near-simultaneous writes to the same file could interleave and silently drop one of them. Each write now uses a unique temporary file.
- **Lightbox CSS On Every Page** - Every bundled theme hardcoded a direct `<link>` to the lightbox stylesheet in its own header, regardless of whether the page had a gallery. Removed from all themes; the CMS now injects it only when needed.
- **Broken Search/Gallery Styling On Themes Missing CSS Variables** - `search.css` and `gallery-layout.css` read their colors and spacing entirely from CSS custom properties with no fallback value. Custom properties now has a real fallback value.
- **`version.json` Publicly Readable** - Anyone could fetch it over HTTP and read the exact installed version, making known-vulnerability targeting trivial. Now blocked in both `.htaccess` and `nginx.conf.example`; the updater already read it from disk, never over HTTP.
- **`og:type` Always "website"** - Article and project pages declared `og:type=website` like any other page, with no publish/update dates, weakening link previews on social networks and AI tools. They now declare `og:type=article` with `article:published_time` and `article:modified_time`.
- **URLs Without A Trailing Slash Served 200** - `/some-page` and `/some-page/` both rendered the same content instead of one redirecting to the other, splitting SEO link equity between two URLs for the same page. The slash-less URL now 301-redirects to the canonical one.
- **Sitemap Dates Were The Generation Date** - Every URL now shows when its content last changed; categories and tags use their most recent item.
- **Content Images Missing Dimensions, First One Lazy-Loaded** - Markdown images with no explicit size hint had no `width`/`height`, letting the browser reflow the page as each one loaded; every image was also `loading="lazy"`, including the first one above the fold, delaying it instead of prioritizing it. Images without an explicit size now get their real dimensions read from the file; the first image in the content is now `loading="eager"` with `fetchpriority="high"`.
- **Articles/Pages/Projects Without A Chosen Schema Type Had No Structured Data** - The editor's schema type field defaults to "None", so any item left untouched had zero structured data. It now falls back to a sensible type per content type (Article, CreativeWork, WebPage) instead of emitting nothing.
- **No Site-Wide WebSite/Organization Structured Data** - Only individual articles/pages/projects could carry structured data; the site itself never described itself to search engines and AI tools. Every page now also emits `WebSite` (and `Organization`/`Person`, when a site logo is set) JSON-LD.
- **No `og:image` On Listing, Category Or Tag Pages** - Only single items and the homepage had a social preview image; archive-style pages shared none. They now fall back to the site-wide default social image when one is configured.
- **Galleries Always Loaded jQuery, Render-Blocking** - Every gallery layout loaded jQuery from a CDN even though only the "justified" layout actually uses it (masonry/imagesLoaded and the carousel are jQuery-free standalone builds). jQuery now loads only for a "justified" gallery.
- **JSON-LD Author Could Be The Site Name Typed As A Person** - When an article or page had no author set and no default schema author configured, the site title was used as a fallback author name, emitted as `"@type": "Person"`. The `author` field is now omitted entirely when no real author name is available.
- **Deleted Or Renamed Content Never 404'd** - A category-path URL whose last segment didn't match any article or page (e.g. an old link to a renamed or deleted article) served the parent category page with a 200 instead of a 404. The router now only falls back to a category page when the full requested path is itself a real category.
- **Autosave on published or scheduled items ignored most fields**, so edits to tags, SEO, image, schedule date, custom fields and more were silently dropped while still showing "Saved" — every field is now compared and kept in the pending draft.
- **Autosave said "Saved" even when nothing was saved** — it now shows "No changes to save" when there is nothing new to keep.
- **Saving an item could delete the unsaved autosave of a different item with the same title** — only that item's own pending autosave is removed now.
- **Duplicate Batch-Progress Helpers** - Two unused, conflicting copies of the batch-progress functions could silently shadow the real ones; removed.

### Security

- **Direct-Access Guard On Admin-Only Files** - Admin function libraries and plugin admin templates now refuse to run when requested directly by URL, protecting servers that lack the bundled deny rules (for example a custom nginx setup).
- **Admin Content-Security-Policy Tightened** - Admin pages no longer allow scripts from jsDelivr or jQuery's CDN.
