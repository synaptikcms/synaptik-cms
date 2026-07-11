<div align="center">

# SynaptikCMS

**A flat-file PHP CMS. No database. No bloat. Just fast.**

[![Version](https://img.shields.io/badge/version-1.2-6366f1?style=flat-square)](https://github.com/synaptikcms/synaptik-cms/releases)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-22c55e?style=flat-square)](LICENSE.md)
[![Size](https://img.shields.io/badge/footprint-2MB-f59e0b?style=flat-square)]()

[Live Demo](https://synaptikcms.com/synaptik-demo/) · [Download Themes](https://synaptikcms.com/themes/) · [Documentation](https://synaptikcms.com/docs/) · [Changelog](CHANGELOG.md) · [Report a Bug](https://github.com/synaptikcms/synaptik-cms/issues)

![Dashboard](.github/assets/dashboard-light.png)
![Dashboard Dark](.github/assets/dashboard-dark.png)
![Editor](.github/assets/editor-light.jpg)
![Editor Dark](.github/assets/editor-dark.jpg)
![Media Manager](.github/assets/file-manager-light.jpg)
![Media Manager Dark](.github/assets/file-manager-dark.jpg)
![Editor Markdown](.github/assets/editor-markdown-sidebar-collapsed.png)
![Gallery Example](.github/assets/gallery-sidebar-collapsed.jpg)

</div>

---

## What is SynaptikCMS?

SynaptikCMS is a flat-file content management system built in PHP. Content is stored as individual JSON files — no database engine, no configuration overhead, no moving parts.

It was built for developers, designers, artists or creative professionals, or anyone who want a CMS that is fast by default, easy to deploy anywhere, and simple enough to theme from scratch without fighting a framework.

**Installed footprint: ~2MB.** Zero runtime dependencies beyond PHP.

---

## Why SynaptikCMS?

| | SynaptikCMS | WordPress | Grav | Kirby |
|---|---|---|---|---|
| Database required | ✗ | MySQL | ✗ | ✗ |
| Admin panel included | ✓ | ✓ | Plugin | ✓ |
| WYSIWYG + Markdown | ✓ | ✓ | ✗ | ✓ |
| Theme system | ✓ hooks/filters | ✓ | ✓ | ✓ |
| Built-in i18n | ✓ EN/FR/ES | Plugin | ✓ | ✓ |
| Image optimization | ✓ built-in | Plugin | Plugin | Plugin |
| License | MIT free | GPL free | MIT free | Paid |
| Install time | ~2 min | ~10 min | ~5 min | ~5 min |

SynaptikCMS is not trying to replace WordPress at scale. It is the right tool when you want a real admin panel with no database — for portfolios, documentation sites, small business sites, or any project where simplicity and load speed matter.

---

## Installation

No composer. No npm. No database setup.

1. Download the latest release ZIP
2. Extract and upload the files to your server
3. Visit `yourdomain.com/install.php` (or `yourdomain.com/subfolder/install.php`) and complete the setup wizard (site name, language, admin password, admin folder name)
4. Delete `install.php` from your server

**That's it.** You're running.

> ⚠️ Always run `install.php` before using the CMS. See the [installation guide](https://synaptikcms.com/docs/getting-started/#toc-installation) for details.

You can download more free themes for your Synaptik installation on the [Official Website](https://synaptikcms.com/themes/).

---

## Features

### Content
- **3 content types** — Articles, Pages, Projects (Portfolio)
- **WYSIWYG and Markdown editors** — switch per post, content preserved
- **Draft system** — autosave at user-defined intervals, one-click publish
- **Scheduled publication** — set a future date, CMS publishes automatically
- **Categories and Tags** — hierarchical categories (up to 3 levels), tag management, merge and purge orphans
- **Custom Fields** — define extra fields per content type, use them in your theme
- **Related Content** — manual selection or automatic suggestions based on shared tags and categories
- **Image Galleries** — one or more galleries per post, 4 layouts: grid, masonry, justified, carousel
- **Shortcodes** — `[toc]`, `[gallery]`, `[callout]`, `[quote]`, `[button]`, `[recent_articles]`, `[recent_projects]`, `[articles_by_tag]`, `[contact_form]`

### Admin Panel
- **Media Manager** — upload, browse, rename, move with drag and drop, batch compression
- **Menu Builder** — drag and drop custom navigation, nested items, external links
- **Template Editor** — live-edit your active theme files with automatic backup before each save
- **SEO Overview** — audit meta titles, descriptions and keywords across all content in one view
- **Alt Text Assistant** — bulk-edit alt text and captions for gallery images
- **Sitemap Generator** — one-click XML sitemap, submit URL shown inline
- **Backup and Restore** — full ZIP backup of `/data/`, `/files/` and settings; restore in one click
- **Automatic Updates** — update notification in dashboard, one-click update with automatic safety backup
- **Translation Editor** — add or edit translations with the built-in editor (both for back-end and front-end), or simply customize the text strings displayed on your site

### Themes
- Hook and filter system for theme developers
- Partials for article and project cards
- Live theme preview without activating
- Theme upload via ZIP with validation
- Theme manager with activate and delete
- Ships with default theme: `Mono`. More themes can be downloaded at [https://synaptikcms.com/themes/](https://synaptikcms.com/themes/).

### SEO and Performance
- Meta title, description, keywords per content item
- Open Graph and Twitter Card tags
- JSON-LD schema markup
- Canonical URLs
- RSS feed auto-injected in `<head>`
- Per-request cache for settings and content indexes
- Split-file architecture — single item pages load exactly one JSON file

### Internationalisation
- Front-end and admin panel fully localised
- Ships with English, French, Spanish
- Add a new language by dropping a JSON file in `/lang/` or by using the built-in translation editor

---

## System Requirements

### Server

| Requirement | Minimum | Notes |
|---|---|---|
| Web server | Apache 2.2+ | Nginx works but requires manual rewrite config |
| PHP | **7.4+** | 8.0+ recommended |
| Database | — | **None required** |

### PHP Extensions

**Required**

| Extension | Used for |
|---|---|
| `json` | All read/write on `.json` data files |
| `mbstring` | Search, contact form validation, UTF-8 string ops |
| `hash` | HMAC tokens (CSRF, theme preview signing) |
| `session` | Admin authentication |
| `pcre` | Slug sanitisation, HTML purification, content parsing |
| `filter` | Email validation in contact form |
| `fileinfo` | MIME type detection on file uploads |

**Required for image features**

| Extension | Used for |
|---|---|
| `gd` | Image resizing, thumbnails, JPEG/PNG/GIF optimisation |
| GD + JPEG | Handling `.jpg`/`.jpeg` uploads |
| GD + PNG | Handling `.png` uploads |

**Optional**

| Extension | Used for |
|---|---|
| GD + WebP | WebP conversion — gracefully disabled if absent |
| `ZipArchive` | Theme upload and automatic updates |

### Apache

Required modules: `mod_rewrite`, `mod_authz_core`  
Required directive: `AllowOverride All` on the document root

The CMS ships with a root `.htaccess` handling URL routing, security headers and cache rules:

```apacheconf
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

### Nginx (not officially supported)

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ ^/(data|bckps)/ { deny all; }
location ~ (settings\.json|admin-credentials\.php)$ { deny all; }
```

### Filesystem Permissions

The following paths must be writable by the PHP process:

| Path | Required for |
|---|---|
| `/` | `settings.json`, `install.lock` during setup |
| `/data/` | All content read/write |
| `/files/` | Media uploads |
| `/bckps/` | Backups, CSRF secret |
| `/admin/` | Credentials, draft autosave |
| `/theme/` | Theme ZIP upload |

Recommended: `755` for directories, `644` for files.

### Browser (Admin Panel)

| Browser | Minimum |
|---|---|
| Chrome / Edge | 80+ |
| Firefox | 75+ |
| Safari | 13.1+ |

Internet Explorer is not supported.

### Setup Checklist

```
[ ] Apache 2.2+ with mod_rewrite enabled
[ ] AllowOverride All on the document root
[ ] PHP 7.4+ (8.0+ recommended)
[ ] Extensions: json, mbstring, hash, session, pcre, filter, fileinfo
[ ] GD with JPEG and PNG support
[ ] ZipArchive recommended (theme upload, auto-updates)
[ ] Root .htaccess in place
[ ] Write permissions on: /, /data/, /files/, /bckps/, /admin/, /theme/
```

---

## Theming

Themes live in `/theme/{theme-name}/`. A minimal theme requires:

```
theme/
└── my-theme/
    ├── css/
    │   └── style.css
    ├── header.php
    ├── footer.php
    └── home.php
```

Optional overrides: `content-articles.php`, `content-pages.php`, `content-projects.php`, `content-list.php`, `404.php`, `functions.php`, `page-templates/`, `partials/`.

The Theme API exposes hooks (`add_action`), filters (`add_filter`), and helpers (`render_site_logo`, `render_header_scripts`, `render_navigation`, etc.). See the documentation for the full reference.

---

## License

MIT — free for personal and commercial use. See [LICENSE.md](LICENSE.md).

---

## Contributing

Issues and pull requests are welcome. Please open an issue before submitting a large PR to discuss the change.

---

<div align="center">
Made with care by <a href="https://github.com/synaptikcms">@synaptikcms</a>
</div>