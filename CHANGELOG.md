# Changelog

All notable changes to Synaptik CMS are documented here.  

## [1.4.7] — 2026-10-20

### Changed

- **Chart.js Self-Hosted** - The Analytics plugin now ships Chart.js instead of loading it from a CDN, so the admin no longer allows any external script source.

### Fixed

- **Stale Menu Links After Renaming Tags And Categories** - Menu entries kept pointing to the old URL (404) when a slug was edited by hand, on sites with translated tag/category URLs, and after a merge or deletion; category entries were never updated at all.
- **Menu Sync Race Condition** - Updating menu links after a rename or edit wrote the settings file non-atomically, so a simultaneous save could drop one of the two changes.
- **Menu Builder "No Parent" Option** - The edit dialog showed the raw translation code text instead of the label.
- **Custom .htaccess Rules** - `php_value` and `php_flag` are now blocked. Rules that make the site or admin return 403/404 or become unreachable are now rolled back automatically, the admin login is checked too.
- **Sitemap Not Updated After Category, Tag And Content Type Changes** - Editing, merging or deleting a category or tag, or changing a content type's URLs, now regenerates it.
- **Scheduled Publication Write Race** - Promoting a scheduled item rewrote its file and the index non-atomically; both now go through a temporary file.
- **Anchor And Footnote Links Trusted The Raw Request URI** - A protocol-relative request path could end up in cached links if the canonical redirect were ever relaxed; the path is now normalized.
- **Public CSP Allowed Unused Script CDNs** - jsDelivr and cdnjs are no longer allowed in the front-end `script-src`.
- **Custom Content Type Named `drafts`** - It swallowed every user's autosaved drafts on rename; reserved names and identifiers matching an existing data folder are now refused.
- **Content Type URLs Colliding With Real Folders** - A URL such as `files`, `theme` or the admin folder name was accepted and made the type's pages unreachable; they are now refused.
- **Sitemap Not Updated On Unpublish Or Scheduled Publication** - An unpublished item stayed in the sitemap and returned 404, and a scheduled item that went live was missing until the next manual update.
- **Menu Builder** - Content titles and menu labels were inserted into the page unescaped, letting a crafted title inject markup (XSS) into the admin.

### Security

- **Remote Code Execution Through File Manager Rename** - An author could rename an uploaded .txt file to .php by sending the wrong item type; the real type is now checked server-side.
- **Uploads Folder PHP Block Widened** - `.phtml`, `.phar`, `.pht` and numbered `.php` variants are now denied in the uploads folder too.

---

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

---

## [1.4.5] — 2026-09-08

### Added

- **Manual slug editing for categories and tags**,instead of always deriving the URL from the name.
- **Menu builder can now link directly to a category archive**, picked from a dropdown the same way tags already work.
- **Template editor now saves to child themes instead of the theme itself** — edits are written to a child theme override (`theme/child_theme/{theme}/`) so customizations survive theme updates instead of being overwritten.
- **Revert to Theme Default button** in the template editor — discards a file's customization and falls back to the theme's original version; overrides that end up byte-identical to the theme file are now cleaned up automatically instead of silently freezing that file against future updates.

### Changed

- **New "Advanced" settings tab** groups the robots.txt editor and .htaccess rules manager — previously scattered across the SEO and Contact tabs.
- **Full backup ZIP filename now starts with the site name** (e.g. `mysite-snk-fullbkp-date.zip`) instead of a generic `synaptik-full-backup-...` name, so backups from different sites are easy to tell apart.

### Removed

- **Read-only page templates listing in Settings > Advanced** — purely informational, duplicated the template picker already in the page editor.

### Fixed

- **CSP hash for the pre-paint theme-preference script was out of sync with the actual snippet** in Atrium, Ink, Prism, and Vitae, blocking the script and causing a dark/light-mode flash — flagged by PageSpeed Insights as a Content-Security-Policy violation. Re-synced `.htaccess` and `nginx.conf.example` to the snippet's real hash and added a regression test so the two can't drift apart again unnoticed.
- **Restoring a backup could carry over a stale admin folder name**, breaking plugins (Analytics) that build admin URLs from it directly. The restored value is now re-verified against the actual folder right after restore.
- **A manually renamed admin folder, or any other cause of a stale admin folder name in the settings file, now self-corrects the next time settings are saved** — every save now goes through a single, centralized function instead of each screen writing the settings file on its own.
- **Scheduled and draft articles, pages, or projects could silently disappear from the admin content list** — the moment any other item of the same type became due for publishing during an ordinary front-end visit, its index got rewritten from a copy that had already dropped every draft and still-future-scheduled item, permanently deleting their rows. The item itself was never touched, only its listing — this is why it could still be found inside a backup ZIP. Fixed.
- **A fresh draft's file could stay named "Untitled draft" forever**, even after filling in a real title and publishing or scheduling it, because autosave already synced the new title into the listing without renaming the underlying file — so the later save/schedule action never detected a change to act on. It now gets renamed to match on the next save.
- **File Manager Rename Modal** - CSS fix, now displays centered.

### Security

- **Missing CSRF checks on saving the menu and on activating or deleting a theme** — fixed.
- **Missing CSRF checks on adding, editing, merging, purging, or deleting categories and tags** — fixed.
- **Custom .htaccess rules** — `Header`, `RequestHeader`, and the `[P]` flag are now blocked alongside the existing handler and `php_admin_*` restrictions.

---

## [1.4.4] — 2026-09-02

### Added

- **Custom .htaccess rules** (Settings → SEO) — add your own redirects or server rules from the admin instead of by FTP. They're re-applied automatically after every update, and if a rule would take your site down it's undone on the spot.
- **Site backups now also include your .htaccess file and any child-theme customizations** — both are restored automatically too, so a full backup/restore no longer leaves your custom server rules or theme overrides behind.
- **Category and tag pages now have their own meta description and title** — add an optional description to any category or tag (Categories/Tags management screens) to use it for search engines; without one, a distinct description is generated automatically instead of every archive sharing the site's default one.
- **Media Library warns before deleting an image still used in your content** - it also lists where.
- **Search your own publications right from the "Insert Link" dialog** (WYSIWYG and Markdown editors) — type a title and pick a result to link internally without leaving the editor or copy-pasting a URL.

### Changed

- **Category and tag pages are indexed only once they have a description** — otherwise excluded from the sitemap and marked `noindex`.

### Fixed

- **"Add New" button in the admin top bar shows renamed content types** -- Settings → Rename Content Types: didn't update if you only filled in one of the two name fields (e.g. just the plural). It now uses whichever name you set.
- **Clickable image in Markdown.** Wrapping an image in a link (to make it clickable) now displays correctly.
- **Images hosted on another site never loaded.** Embedding an image by its full web address now works, in the editor and on the published page.
- **Featured image alt text could be lost on save**, including autosave, even without touching the image. Now preserved.
- **Renaming, moving, or WebP-converting an image could break its link to the posts using it** — the file changed, but posts kept the old path. These actions now update every post, gallery, and inline reference automatically.
- **WebP conversion could occasionally produce a corrupted, blank image** if the process was interrupted mid-write. Every converted image is now verified before replacing the original.
- **A post save could silently drop any field not present on the edit form** (relevant to plugin developers using `item_before_save`). These fields are now preserved.
- **Autosave flagged a published post as having unsaved changes**, due to comparing category slug to its display name. Editing an unrelated field no longer shows a false "unsaved changes" badge in the content list.
- **Default theme styling** - modified links.

### Security

- **Missing CSRF checks in the Media Library** (upload, folder creation, rename, move, batch optimizer) — could be triggered by another site while you were logged in. Deleting was already protected.
- **Stored XSS via code blocks** — content inside a fenced code block wasn't sanitized like the rest of the editor, so a `<script>` tag placed there would run in an admin's browser. Code blocks are now always escaped as plain text.
- **Stored XSS via WordPress import** — imported content skipped sanitization. Now cleaned like any other content; WordPress video embeds and image captions need to be re-added manually as a result.
- **Missing CSRF check on the Settings form**, including the Robots.txt editor.
- **Plugin files could be executed directly by URL** on Nginx (Apache was already protected via `.htaccess`), including the WordPress Importer's. Blocked on both now.

---

## [1.4.3] — 2026-08-29

### Fixed

- **Pages loaded without any styling under a secondary domain** — the other variant of your address (with or without "www") now redirects to your main one.
- **A mistyped "Canonical Site Host" could make the whole site unreachable.** Bad values are now corrected or ignored instead of breaking the site.
- **Markdown images with a caption in quotes didn't display.** They now work, on images and links alike.

### Added

- **Reference-style Markdown links and images** — write `[text][label]` and put the address once at the bottom of the page as `[label]: address`.
- **Default editor preference** (Settings → Writing) — new content can now open in Markdown instead of always starting in WYSIWYG.

---

## [1.4.2] — 2026-08-28

### Fixed

- **Theme upload failed on a fresh install** — installing any theme through Appearance → Theme Manager was cancelled with a "could not back up the existing theme" error, even though there was no existing theme to back up. The destination folder was created and left empty. Fixed.
- **Full-page cache served the wrong site's HTML on multi-hostname setups** — if your site answered on more than one hostname (e.g. with and without "www."), whichever hostname was cached first determined the styles, images, and scripts served to *every* hostname afterward, breaking the page for the others. The cache now keeps a separate entry per hostname, so this can no longer happen.

### Security

- **A forged Host header could get cached and served to every visitor** — the page cache didn't account for the hostname a request claimed to be for, so a single crafted request could plant content (including the page's canonical link) that later anonymous visitors would then receive. Closed by the same fix as above.
- **New optional "Canonical Site Host" setting** (Settings → SEO) — lets you pin the exact domain used for generated links, the canonical tag, and the page cache, instead of trusting the hostname each visitor's browser happens to send. If your site answers under more than one hostname, or you're not sure, setting this is recommended even beyond the multi-hostname case: without it, a request under any hostname still earns its own page-cache entry, so it also caps how many cache entries an anonymous visitor can cause to be written.
- **Installer now asks for the Canonical Site Host up front** — pre-filled, editable, optional.

### Removed

- Leftover legacy code from the pre-multi-user login system.

---

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
