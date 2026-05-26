# What is SynaptikCMS? 

SynaptikCMS is a **Super Lightweight**, **Super Fast**, **Super Simple**, and **Super Flexible** file-based content management system. 
It was built from the ground up, with a strong focus on **page load speed**, **SEO** (optimization for search engines) and **user-friendliness**.

It intends to remain as lightweight as possible, while natively offering most of the powerful features any larger CMS can offer, and without sacrificing options and useful features.

The philosophy behind it is simple:

> Everything you need, nothing you don't.

Which makes it one of the most lightweight CMS on the market. Powerful features packed in **less than 1.5Mb**.
 
---

## What's inside

- **Zero-install setup** — extract the ZIP, run `install.php`, done
- **Flat-file JSON data** — split-file architecture, each content item is its own file
- **Built-in i18n** — front-end and admin panel, separate locale files
- **Theme system** — hooks, filters, partials, live CSS editor, live theme preview
- **Content types** — Articles, Pages, Projects (Portfolio)
- **Draft system** — manually or autosaved JSON drafts, one-click publish
- **Autosave** — save posts automatically every 5 minutes so you never lose content, even after closing a page accidentally
- **Categories and Tags** — organize and link your content as you like
- **Image Galleries built-in** — add one or more image galleries to your posts, choose between 4 layouts (grid, masonry, justified or carousel)
- **Scheduled publication** — delay publishing your post and set a date for automatic later posting
- **Post Preview** — see posts in current theme's context before actually saving or publishing 
- **Built-in powerful search engine** — `Ctrl+K` overlay, no external dependency
- **Media manager** — upload, browse, reorganize with drag &amp; drop, batch-image optimisation
- **Shortcode engine** — galleries, TOC, callouts, quotes, buttons, contact form, recent articles/projects
- **SEO built-in** — meta tags, Open Graph, JSON-LD schema, canonical URLs, sitemap generator...
- **Backup &amp; Restore** — save your database, restore your website content in 1 click
- **Menu Builder** — create your optimal navigation menu easily with a drag and drop custom tool
- **CSS Editor** — live-edit your current theme's CSS styles
- **Alt Text Assistant** — add alt-text and captions to all your gallery images in one place

---

# Installation

There is **Zero** installation required. Just extract the ZIP archive, and you are already up and running.

After extracting the zip content, visit `yourdomain.com/install.php` (or `yourdomain.com/subfolder/install.php` if you uploaded the CMS files to a domain subfolder) to complete the initial setup (site name, language, admin password, admin folder name).
It is recommended to change your website's admin folder name for added security.

## System Requirements

### Server

| Requirement | Minimum | Notes |
|---|---|---|
| Web server | Apache 2.2+ | Nginx possible but requires manual rewrite config (see below) |
| PHP | **7.4** | Arrow functions (`fn() =>`) used in the data layer |
| Database | — | **None required.** Flat-file JSON architecture |

---

### PHP Extensions

#### Required

| Extension | Used for |
|---|---|
| `json` | All read/write operations on `.json` data files. Bundled since PHP 5.2 |
| `mbstring` | Search engine, contact form validation, UTF-8 safe string ops |
| `hash` | HMAC tokens (contact form CSRF, theme preview signing). Bundled since PHP 5.1.2 |
| `session` | Admin authentication. Bundled by default |
| `pcre` | Slug sanitization, HTML purification, content parsing. Bundled by default |
| `filter` | Email validation in contact form. Bundled by default |
| `fileinfo` | MIME type detection on file uploads. Bundled since PHP 5.3 |

#### Required for image features

| Extension | Used for |
|---|---|
| `gd` | Image resizing, thumbnails, JPEG/PNG/GIF optimization |
| GD + JPEG support | Handling `.jpg`/`.jpeg` uploads (`imagecreatefromjpeg`) |
| GD + PNG support | Handling `.png` uploads (`imagecreatefrompng`) |
| GD + GIF support | Handling `.gif` uploads (`imagecreatefromgif`) |

> **Note:** GD is bundled with most PHP packages. Check that JPEG and PNG support are compiled in (`phpinfo()` → GD section).

#### Optional

| Extension | Used for |
|---|---|
| GD + WebP support | WebP conversion (`imagewebp`). Gracefully disabled if absent |
| `zip` / `ZipArchive` | Theme upload via `.zip` archive. The theme manager warns if missing |

---

### Apache Configuration

#### Required modules

| Module | Why |
|---|---|
| `mod_rewrite` | All front-end URLs (`/article/my-slug/`) are routed through `index.php` via rewrite rules |
| `mod_authz_core` | `.htaccess` access control (`Require all denied`) on `/data/` and `/bckps/` |

#### Required directive

```apacheconf
AllowOverride All
```

This must be set on the document root (or the CMS subdirectory) in your Apache `VirtualHost` or `httpd.conf`. Without it, `.htaccess` files are silently ignored — URL rewriting and directory protection both stop working.

#### Required .htaccess at CMS root

The CMS ships with a root `.htaccess` that handles URL routing, security headers, and cache rules. Its core rewrite block is:

```apacheconf
RewriteEngine On

# If the CMS is installed in a subdirectory, uncomment and set this:
# RewriteBase /your-subdir/

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

Without this, front-end URLs return 404.

#### Nginx (not officially supported)

The CMS works on Nginx but `.htaccess` files have no effect. You must manually replicate the rewrite rules and access restrictions in your `nginx.conf`:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

# Block direct access to sensitive directories
location ~ ^/(data|bckps)/ {
    deny all;
}

# Block sensitive files
location ~ (settings\.json|data\.json|admin-credentials\.php)$ {
    deny all;
}
```

---

### Filesystem Permissions

The following paths must be **writable by the PHP process** (typically `www-data` or `apache`):

| Path | Required for |
|---|---|
| `/` (root) | Writing `settings.json`, `install.lock` during installation |
| `/data/` | All content read/write (articles, pages, projects) |
| `/data/articles/` | Article JSON files |
| `/data/pages/` | Page JSON files |
| `/data/projects/` | Project JSON files |
| `/files/` | Media uploads |
| `/bckps/` | Backup exports, contact rate-limiting, CSRF secret |
| `/admin/` | Credential file write, draft autosave |
| `/admin/drafts/` | Autosave draft files |
| `/theme/` | Theme upload (ZIP import) |

Recommended permissions: `755` for directories, `644` for files.

---

### Browser (Admin Panel)

The admin panel requires a modern browser with JavaScript enabled.

| Browser | Minimum version |
|---|---|
| Chrome / Edge | 80+ |
| Firefox | 75+ |
| Safari | 13.1+ |

Internet Explorer is not supported.

---

### Summary Checklist

```
[ ] Apache 2.2+ with mod_rewrite enabled
[ ] AllowOverride All set on the document root
[ ] PHP 7.4 or higher
[ ] PHP extensions: json, mbstring, hash, session, pcre, filter, fileinfo
[ ] PHP GD extension with JPEG and PNG support
[ ] ZipArchive (recommended — required for theme upload)
[ ] Root .htaccess in place and not overridden
[ ] Write permissions on: /, /data/, /files/, /bckps/, /admin/, /theme/
```
