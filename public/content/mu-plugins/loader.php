<?php
/**
 * Plugin Name: Boilerplate MU Loader
 * Description: Minimal MU plugin loader for explicit project-level tie-ins.
 * Version: 1.0.0
 * Author: folkhack
 */

if( ! defined( 'ABSPATH' ) ) {

    exit;
}

/**
 * Add project MU plugin entry files here when needed.
 *
 * WordPress automatically loads PHP files directly in mu-plugins, but it does
 * not automatically load plugin entry files inside subdirectories. Keep this
 * explicit so the seat stays predictable.
 */
$boilerplate_mu_plugins = apply_filters( 'boilerplate_mu_plugins', [] );

foreach( $boilerplate_mu_plugins as $boilerplate_mu_plugin ) {

    $boilerplate_mu_plugin = (string) $boilerplate_mu_plugin;

    if( $boilerplate_mu_plugin === '' ) {

        continue;
    }

    $boilerplate_mu_plugin_path = WPMU_PLUGIN_DIR . '/' . ltrim( $boilerplate_mu_plugin, '/' );

    if( is_readable( $boilerplate_mu_plugin_path ) ) {

        require_once $boilerplate_mu_plugin_path;
    }
}
