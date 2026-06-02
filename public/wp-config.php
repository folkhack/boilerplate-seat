<?php
/**
 * WordPress bootstrap for the Boilerplate Seat.
 *
 * Project layout:
 *
 *   project-root/
 *   ├── vendor/                    Composer dependencies
 *   ├── wp_config.php              Private site configuration, outside webroot
 *   └── public/                    Public webroot
 *       ├── index.php              Front controller
 *       ├── wp-config.php          This file
 *       ├── wp/                    WordPress core installed by Composer / Roots
 *       └── content/               Custom wp-content directory
 *
 * WordPress core lives in:
 *
 *   public/wp/
 *
 * WordPress content lives in:
 *
 *   public/content/
 *
 * Site-specific configuration lives outside the public webroot:
 *
 *   wp_config.php
 *
 * This keeps secrets, database credentials, salts, and environment-specific
 * values out of the public document root.
 */

// -------------------------------------------------------------------------------------------------
// Composer autoload
// -------------------------------------------------------------------------------------------------

/**
 * Load Composer's autoloader.
 *
 * This is required because WordPress core is installed and managed by Composer.
 * It also allows Composer-managed plugins, themes, and installer packages to
 * register correctly.
 */
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// -------------------------------------------------------------------------------------------------
// Private site configuration
// -------------------------------------------------------------------------------------------------

/**
 * The real site config is intentionally outside /public.
 *
 * wp_config.php should be copied from:
 *
 *   wp_config.sample.php
 *
 * and customized per environment.
 */
$site_config        = dirname( __DIR__ ) . '/wp_config.php';
$site_config_sample = dirname( __DIR__ ) . '/wp_config.sample.php';

if( is_readable( $site_config ) ) {

    require $site_config;

} elseif( is_readable( $site_config_sample ) ) {

    /**
     * The sample file exists, but the real config has not been created yet.
     *
     * This guard mainly protects manual bootstraps where Composer has not yet
     * copied the sample config into place.
     */
    http_response_code( 500 );
    exit( 'Copy wp_config.sample.php to wp_config.php and update the site configuration.' );

} else {

    /**
     * Neither the real config nor the sample config exists.
     *
     * The seat cannot safely boot without explicit site configuration.
     */
    http_response_code( 500 );
    exit( 'Missing required site configuration file: wp_config.php' );
}

// -------------------------------------------------------------------------------------------------
// Custom content directory
// -------------------------------------------------------------------------------------------------

/**
 * Keep wp-content outside the Composer-managed WordPress core directory.
 *
 * WordPress core:
 *
 *   public/wp/
 *
 * WordPress content:
 *
 *   public/content/
 *
 * This prevents core updates from touching project content, uploaded files,
 * project plugins, mu-plugins, and installed themes.
 */
if( ! defined( 'WP_CONTENT_DIR' ) ) {

    define( 'WP_CONTENT_DIR', __DIR__ . '/content' );
}

/**
 * Define the public URL for the custom content directory.
 *
 * Preferred behavior:
 *
 * - Define WP_CONTENT_URL in wp_config.php or through real environment config.
 *
 * Fallback behavior:
 *
 * - Derive the URL from the current request.
 * - Respect HTTPS and common reverse-proxy headers.
 *
 * In production, an explicit WP_CONTENT_URL is better than relying on
 * request-derived values.
 */
if( ! defined( 'WP_CONTENT_URL' ) ) {

    $scheme = 'http';

    if(
        ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ||
        ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' )
    ) {

        $scheme = 'https';
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    define( 'WP_CONTENT_URL', $scheme . '://' . $host . '/content' );
}

// -------------------------------------------------------------------------------------------------
// WordPress core path
// -------------------------------------------------------------------------------------------------

/**
 * Tell WordPress where core is installed.
 *
 * Composer / Roots installs WordPress into:
 *
 *   public/wp/
 *
 * ABSPATH must point at that core directory before loading wp-settings.php.
 */
if( ! defined( 'ABSPATH' ) ) {

    define( 'ABSPATH', __DIR__ . '/wp/' );
}

// -------------------------------------------------------------------------------------------------
// Boot WordPress
// -------------------------------------------------------------------------------------------------

/**
 * Load WordPress.
 *
 * At this point, the private config has already defined database credentials,
 * salts, debug constants, home/site URLs, table prefix, and other environment
 * settings.
 */
require_once ABSPATH . 'wp-settings.php';
