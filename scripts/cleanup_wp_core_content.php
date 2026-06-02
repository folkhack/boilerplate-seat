<?php
/**
 * Clean bundled WordPress core content after roots/wordpress-full install/update.
 *
 * This only touches public/wp/wp-content, which belongs to the installed core
 * package. It does not touch the site's active public/content directory.
 */

declare( strict_types=1 );

$project_root      = dirname( __DIR__ );
$core_content      = $project_root . '/public/wp/wp-content';
$plugins_dir       = $core_content . '/plugins';
$themes_dir        = $core_content . '/themes';
$active_themes_dir = $project_root . '/public/content/themes';

function bp_cleanup_log( string $message ): void {

    fwrite( STDOUT, '[boilerplate_seat] ' . $message . PHP_EOL );
}

function bp_remove_path( string $path ): void {

    if( ! file_exists( $path ) && ! is_link( $path ) ) {
        return;
    }

    if( is_file( $path ) || is_link( $path ) ) {
        @unlink( $path );
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach( $iterator as $item ) {
        $item_path = $item->getPathname();

        if( $item->isDir() && ! $item->isLink() ) {
            @rmdir( $item_path );
        } else {
            @unlink( $item_path );
        }
    }

    @rmdir( $path );
}

function bp_copy_path( string $source, string $destination ): void {

    if( is_link( $source ) ) {
        symlink( readlink( $source ), $destination );
        return;
    }

    if( is_file( $source ) ) {
        copy( $source, $destination );
        return;
    }

    if( ! is_dir( $destination ) ) {
        mkdir( $destination, 0755, true );
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach( $iterator as $item ) {
        $source_path      = $item->getPathname();
        $relative_path    = substr( $source_path, strlen( $source ) + 1 );
        $destination_path = $destination . '/' . $relative_path;

        if( $item->isDir() && ! $item->isLink() ) {

            if( ! is_dir( $destination_path ) ) {
                mkdir( $destination_path, 0755, true );
            }

            continue;
        }

        if( $item->isLink() ) {
            symlink( readlink( $source_path ), $destination_path );
            continue;
        }

        copy( $source_path, $destination_path );
    }
}

function bp_detect_latest_default_theme( string $themes_dir ): ?string {

    $theme_order = [
        'twentythirtynine',
        'twentythirtyeight',
        'twentythirtyseven',
        'twentythirtysix',
        'twentythirtyfive',
        'twentythirtyfour',
        'twentythirtythree',
        'twentythirtytwo',
        'twentythirtyone',
        'twentythirty',
        'twentytwentynine',
        'twentytwentyeight',
        'twentytwentyseven',
        'twentytwentysix',
        'twentytwentyfive',
        'twentytwentyfour',
        'twentytwentythree',
        'twentytwentytwo',
        'twentytwentyone',
        'twentytwenty',
        'twentynineteen',
        'twentyseventeen',
        'twentysixteen',
        'twentyfifteen',
        'twentyfourteen',
        'twentythirteen',
        'twentytwelve',
        'twentyeleven',
        'twentyten',
    ];

    foreach( $theme_order as $theme ) {
        if( is_dir( $themes_dir . '/' . $theme ) ) {
            return $theme;
        }
    }

    return null;
}

if( ! is_dir( $core_content ) ) {
    bp_cleanup_log( 'WordPress core content directory not found yet; skipping cleanup.' );
    exit( 0 );
}

// Remove bundled default plugins from roots/wordpress-full.
if( is_dir( $plugins_dir ) ) {

    $default_plugin_paths = [
        $plugins_dir . '/akismet',
        $plugins_dir . '/hello.php',
    ];

    foreach( $default_plugin_paths as $plugin_path ) {

        if( file_exists( $plugin_path ) || is_link( $plugin_path ) ) {

            bp_cleanup_log( 'Removing bundled core plugin: ' . str_replace( $project_root . '/', '', $plugin_path ) );
            bp_remove_path( $plugin_path );
        }
    }
}

// Keep only the configured/latest bundled WordPress default theme in core.
if( is_dir( $themes_dir ) ) {

    $configured_keep_theme = getenv( 'BOILERPLATE_KEEP_CORE_THEME' ) ?: null;

    $keep_theme = (
        $configured_keep_theme &&
        is_dir( $themes_dir . '/' . $configured_keep_theme )
    )
        ? $configured_keep_theme
        : bp_detect_latest_default_theme( $themes_dir );

    if( $keep_theme ) {
        bp_cleanup_log( 'Keeping bundled core theme: ' . $keep_theme );
    }

    foreach( glob( $themes_dir . '/twenty*', GLOB_ONLYDIR ) ?: [] as $theme_path ) {

        $theme = basename( $theme_path );

        if( $theme === $keep_theme ) {
            continue;
        }

        bp_cleanup_log( 'Removing older bundled core theme: ' . str_replace( $project_root . '/', '', $theme_path ) );
        bp_remove_path( $theme_path );
    }

    if( $keep_theme ) {

        $source_theme = $themes_dir . '/' . $keep_theme;
        $active_theme = $active_themes_dir . '/' . $keep_theme;

        if( is_dir( $active_theme ) ) {

            bp_cleanup_log( 'Active default theme already exists; leaving untouched: ' . str_replace( $project_root . '/', '', $active_theme ) );

        } elseif( is_dir( $source_theme ) ) {

            if( ! is_dir( $active_themes_dir ) ) {
                mkdir( $active_themes_dir, 0755, true );
            }

            bp_cleanup_log(
                'Copying bundled core theme into active content themes: ' .
                str_replace( $project_root . '/', '', $source_theme ) .
                ' -> ' .
                str_replace( $project_root . '/', '', $active_theme )
            );

            bp_copy_path( $source_theme, $active_theme );
        }
    }
}

exit( 0 );
