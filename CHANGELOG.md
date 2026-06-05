## v1.0 | 2026-06-05

- Added automatic updates :
	- New page admin/templates/update.php: displays current vs available version, release date, notes, changelog link.
	- "Apply Update" button with confirmation modal: downloads the release ZIP (cURL with file_get_contents fallback), validates structure, creates a safety backup, extracts and replaces core files.
	- Files protected during update: `/data/`, `/files/`, `settings.json`, `install.lock`, `admin/admin-credentials.php`, `admin/cache/`, `admin/drafts/`, `bckps/`, `cache/`, `private/`.
	- Automatic handling of renamed admin folder via settings (e.g. `/myadmin`).
	- Handling of ZIPs with a root subdirectory wrapper (e.g. synaptikcms-v1.0/).
	- Update cache invalidated after a successful update.
- Dashboard: "update available" notice now links to the update page instead of an external URL.
- Added full data backup + restore instead of only database JSON.
	- Full ZIP backup: new button in the admin that generates and downloads a complete archive (`/data/`, `/files/`, `settings.json`, `version.json`). Replaces the old JSON backup system.
	- Restore from ZIP: upload a ZIP backup to restore the site. Validates the archive structure before applying. Automatically creates a safety backup of the current state before any restore.
	- Server backups table: lists all files in `/bckps/` (manual backups + pre-restore/pre-update safety backups) with Download and Delete buttons.
	- `backup-dl.php` rewritten: supports `.zip` and `.json`, proper auth check, path traversal protection.
	- New file `admin/includes/backup-functions.php`: shared functions `_backup_build_zip`, `_backup_clear_dir`, `_backup_copy_dir`.
- Split front-end `template-functions.php` into smaller chunk files
- ~50 new keys added to lang/admin/en.json, fr.json, es.json covering all backup, restore, and update UI strings.

## v0.9 | 2026-05-01

- Added **Markdown support** in editor
- Added **timezone in settings** + publish time added to admin lists and posts 
- Added **server-side pagination** and search for the admin content list: beyond 200 items, filtering and sorting are handled via AJAX instead of loading everything in the browser at once
- Added **RSS feed** (inserted dynamically by header functions - no user action needed)
- Added **user account name** (previously only a password to log into admin)
- Added **Custom Fields** to settings and editor interface for all 3 content types
- Added **user-defined autosave intervals** in settings
- Added **Related Content**: choose to display or not on a per-post basis, pick content manually or let the algorithm decide what to show
- **Live post preview** now works with both HTML and Markdown posts
- Redesigned admin editor with sticky top-bar to keep publish button visible at all times, and other improvements
- Fixed date format display in content-list
- Fixed `seo-preview`'s JS to function with markdown format posts for personalized SEO optimization advice
- Fixed security vulnerabilities in sensitive folders and added CSRF validation in theme form uploads
- Updated editor interface to reflect new features: sidebar improved with tabs to separate content, SEO and custom fields sections
- Updated all default provided themes to reflect changes: custom fields, user-defined logo and favicon, RSS feed, related content...
- Updated admin translations
- Updated documentation
- Various bug fixes and optimizations

## v0.8 | 2026-02-12

- Data is now stored as individual items in `/data/` instead of a unique Json database which can get heavy
- Added **custom article/project summaries** to display in paginated lists (`article-cards`) instead of `article-excerpt`
- Fixed raw shortcode display in `article-cards` excerpts, replaced with `...` or `article-summary` if present
- Added custom styling rules for Search overlay
- Added support for page-templates in pages content type
- Added partials to themes to further customize display of article/project cards
- Added support for custom functions.php file in themes
- Added categories / tags merge + filter orphan categories / tags checkbox
- Added updates notifications / news in dashboard
- Added installer to setup the CMS for first-time users

## v0.7 | 2025-08-10

- Fixed security in admin login/change password
- Added secure password reset system with email link
- Revamped admin sidebar with popovers
- Redesigned admin settings
- Redesigned media manager interface
- Added theme management interface 
- Updated theme management with preview image
- Improved Menu Builder

## v0.6 | 2025-04-20

- Improved sidebar integration, sidebar is now retractable with icons-only instead of full menu-items text
- Added display options to content creation interface
- Refined SEO settings: revamped algorithm for personalized SEO advice in content-add interface
- Added Menu Builder
- Added backup and restore database feature in settings

## v0.5 | 2025-03-27

- Complete overhaul of admin interface
- Redesign of content creation interface - more compact, more user-friendly, less confusing
- Added categories and tags to posts
- Added categories and tags picker in content editing
- Added support for full screen editing
- Added HTML source code editor
- Improved editor features

