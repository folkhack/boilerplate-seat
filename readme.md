# Boilerplate Seat

A Composer-managed WordPress seat for local development, install testing, and project scaffolding around the folkhack Boilerplate theme system.

This repository is the **site scaffold**. It installs WordPress core into `public/wp`, serves the site from `public/`, keeps active WordPress content in `public/content`, and uses Composer for core, plugins, and themes.

## Current Strategy

- WordPress core is installed by Composer using `roots/wordpress-full`.
- WordPress core is installed into `public/wp`.
- Active project content lives in `public/content`.
- Composer-installed plugins land in `public/content/plugins`.
- Composer-installed themes land in `public/content/themes`.
- Bundled core plugins from `roots/wordpress-full` are removed after install/update.
- Only one bundled default WordPress core theme is kept under `public/wp/wp-content/themes`.
- Site-specific configuration lives in `wp_config.php`, outside the public webroot.

---

## Requirements

- PHP 8.3+
- Composer 2+
- MySQL 8.0+ or MariaDB 10.6+
- Nginx + PHP-FPM, or equivalent web server
- Git, if installing themes from GitHub package repositories

## Repository Layout

```text
.
├── composer.json
├── composer.with-boilerplate-themes.json
├── readme.md
├── wp_config.sample.php
├── nginx.sample.conf
├── scripts/
│   └── cleanup_wp_core_content.php
└── public/
    ├── index.php
    ├── wp-config.php
    └── content/
        ├── mu-plugins/
        │   └── loader.php
        ├── plugins/
        │   └── .gitignore
        ├── themes/
        │   └── .gitignore
        ├── upgrade/
        │   └── .gitignore
        └── uploads/
            └── .gitignore
```

Generated after `composer install`:

```text
vendor/
public/wp/
```

Generated after plugin/theme installs:

```text
public/content/plugins/...
public/content/themes/...
```

---

## Install

```bash
composer install
cp wp_config.sample.php wp_config.php
```

Composer also creates `wp_config.php` automatically if it does not already exist.

Edit:

```text
wp_config.php
```

Default local URLs:

```text
WP_HOME=http://wp-boilerplate.test
WP_SITEURL=http://wp-boilerplate.test/wp
WP_CONTENT_URL=http://wp-boilerplate.test/content
```

---

## WordPress Core

WordPress core is installed with:

```text
roots/wordpress-full:^7.0
roots/wordpress-core-installer:^4.0
```

Installed path:

```text
public/wp/
```

The Roots installer plugin is intentionally allowed in `composer.json`:

```json
"allow-plugins": {
  "composer/installers": true,
  "roots/wordpress-core-installer": true
}
```

---

## Why `roots/wordpress-full`

This seat intentionally uses `roots/wordpress-full`, not `roots/wordpress-no-content`.

The reason: it lets the install behave like a full official WordPress distribution, including bundled core content, while still allowing this project to clean that content after install/update.

After Composer installs WordPress, the cleanup script removes bundled default plugins and keeps only the configured/latest bundled default theme.

---

## Core Content Cleanup

`roots/wordpress-full` includes bundled official WordPress themes and bundled default plugins.

After install/update, this seat runs:

```text
scripts/cleanup_wp_core_content.php
```

The cleanup script removes bundled default core plugins from:

```text
public/wp/wp-content/plugins/
```

Removed by default:

```text
akismet/
hello.php
```

The cleanup script keeps the latest detected bundled WordPress default theme unless `BOILERPLATE_KEEP_CORE_THEME` is explicitly set.

Override if desired:

```bash
BOILERPLATE_KEEP_CORE_THEME=twentytwentyfive composer install
```

Run cleanup manually:

```bash
composer run boilerplate:cleanup-core-content
```

The cleanup script does **not** touch:

```text
public/content/plugins/
public/content/themes/
public/content/uploads/
```

---

## Configuration

The real site config is:

```text
wp_config.php
```

It is copied from:

```text
wp_config.sample.php
```

The real config file is ignored by Git.

This file controls:

- environment type
- home/site URLs
- content URL
- database credentials
- table prefix
- debug behavior
- SSL admin behavior
- memory limits
- salts
- automatic update behavior

This scaffold does not require or parse a `.env` file.

The config helper supports real environment variables through `getenv()`, so Docker, CI, or server-level environment values may override local defaults when needed.

Example:

```php
define( 'WP_HOME', bp_env( 'WP_HOME', 'http://wp-boilerplate.test' ) );
```

For normal local development, editing `wp_config.php` is enough.

---

## Composer Plugin Support

This scaffold uses `composer/installers` so WordPress plugins and themes can install outside `vendor`.

Install paths are configured in `composer.json`:

```json
"installer-paths": {
  "public/content/plugins/{$name}/": [
    "type:wordpress-plugin"
  ],
  "public/content/mu-plugins/{$name}/": [
    "type:wordpress-muplugin"
  ],
  "public/content/themes/{$name}/": [
    "type:wordpress-theme"
  ]
}
```

The active `composer.json` includes WPackagist:

```json
{
  "type": "composer",
  "url": "https://wpackagist.org"
}
```

Use [WPackagist](https://wpackagist.org/) for plugins and themes mirrored from the WordPress.org directories.

---

## Plugin Install Example: ACF + Yoast SEO

Use this as the first Composer plugin sanity check:

```bash
composer require wpackagist-plugin/advanced-custom-fields yoast/wordpress-seo
```

Expected install paths:

```text
public/content/plugins/advanced-custom-fields/
public/content/plugins/wordpress-seo/
```

Verify:

```bash
find public/content/plugins -maxdepth 2 -type d | sort

test -d public/content/plugins/advanced-custom-fields && echo "ACF installed"
test -d public/content/plugins/wordpress-seo && echo "Yoast SEO installed"
```

If the plugin test succeeds, commit the updated `composer.json` and `composer.lock` for the project.

---

## Removing Plugins

To remove plugins installed through Composer:

```bash
composer remove wpackagist-plugin/advanced-custom-fields yoast/wordpress-seo
```

---

## Yoast SEO Nginx Sitemap Rewrites

`nginx.sample.conf` includes an optional commented Yoast sitemap block.

Leave it commented unless Yoast SEO is installed and this works:

```text
http://wp-boilerplate.test/?sitemap=1
```

but this fails:

```text
http://wp-boilerplate.test/sitemap_index.xml
```

If that happens, uncomment the Yoast sitemap location block in the Nginx config, test, and reload:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

---

## Non-Boilerplate Themes

This scaffold can also be used with a non-Boilerplate theme.

For a WordPress.org theme mirrored by WPackagist:

```bash
composer require wpackagist-theme/twentytwentyfive
```

Expected path:

```text
public/content/themes/twentytwentyfive/
```

For a GitHub theme that does not publish a Composer package, add a package repository entry with `type: wordpress-theme` and require it normally.

---

## Built Theme Assets

Boilerplate theme repositories should include built `dist/` assets when they are installed from GitHub tags for WordPress use.

That keeps this seat deployable without requiring Node on the web server.

Composer installs WordPress, plugins, and themes. Theme repositories should already contain compiled CSS, JavaScript, images, and fonts.

Node is only required when editing and rebuilding theme repositories themselves.

---

## Nginx

`nginx.sample.conf` uses `wp-boilerplate.test` as the example domain.

Default sample root:

```text
/var/www/nginx_sites/wp-boilerplate.test/public
```

Default logs:

```text
/var/www/nginx_sites/wp-boilerplate.test/log
```

Default PHP-FPM socket:

```text
/run/php/php8.3-fpm.sock
```

Update those paths for the target machine.

Test and reload:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

---

## Git Ignore Strategy

This seat commits source/config samples only.

Ignored:

```text
vendor/
public/wp/
public/content/plugins/*
public/content/themes/*
public/content/uploads/*
wp_config.php
```

Preserved with `.gitignore` files:

```text
public/content/plugins/
public/content/themes/
public/content/uploads/
public/content/upgrade/
```

`composer.lock` should be committed after the desired core/plugin/theme set is installed. This is a project/site scaffold, not a reusable Composer library.

---

## MU Plugins

The only committed MU plugin file is:

```text
public/content/mu-plugins/loader.php
```

WordPress automatically loads PHP files directly inside `mu-plugins`, but it does not automatically load plugin entry files inside subdirectories.

`loader.php` is an explicit project-level tie-in point for future must-use plugin entry files.

---

## Validate

Before install:

```bash
composer validate --strict
```

PHP syntax checks:

```bash
php -l public/index.php
php -l public/wp-config.php
php -l wp_config.sample.php
php -l public/content/mu-plugins/loader.php
php -l scripts/cleanup_wp_core_content.php
```

After install directory scaffolding check:

```bash
find public/wp -maxdepth 2 -type f | sort | head -40
find public/wp/wp-content/plugins -maxdepth 2 -print 2>/dev/null | sort
find public/wp/wp-content/themes -maxdepth 1 -type d -print 2>/dev/null | sort
find public/content/plugins -maxdepth 2 -type d | sort
find public/content/themes -maxdepth 2 -type d | sort
```

Expected core plugin cleanup result:

```text
public/wp/wp-content/plugins
public/wp/wp-content/plugins/index.php
```

Expected core theme cleanup result should include the latest retained Twenty theme, currently:

```text
public/wp/wp-content/themes/twentytwentyfive
```

---

## First Boot Checklist

1. Run `composer install`.
2. Copy/update `wp_config.php`.
3. Update DB credentials and salts.
4. Configure Nginx from `nginx.sample.conf`.
5. Point `wp-boilerplate.test` to the local environment.
6. Open `http://wp-boilerplate.test`.
7. Complete WordPress install.
8. Activate the project theme.
9. Activate Composer-installed plugins in WordPress admin, or with WP-CLI if available.
10. Login at `http://wp-boilerplate.test/wp/wp-login.php`.
11. If the site URL is wrong, fix `WP_HOME` and `WP_SITEURL` in `wp_config.php`, not in the WordPress admin.

---

## Domain / Path Migration

Use `scripts/migrate_wp_domain.php` when moving a WordPress database from one domain/path to another.

The script loads `wp_config.php`, uses the configured database credentials and `$table_prefix`, scans prefixed WordPress tables, and performs serialization-aware replacements.

Dry run:

```bash
php scripts/migrate_wp_domain.php \
  --from=http://old-domain.test \
  --to=http://new-domain.test
```
