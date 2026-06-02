<?php

// Require bp_env, and bp_env_bool functions
require_once( __DIR__ . '/scripts/bp_env.php' );

// -------------------------------------------------------------------------------------------------
// Environment
// -------------------------------------------------------------------------------------------------

define( 'WP_ENVIRONMENT_TYPE', bp_env( 'WP_ENVIRONMENT_TYPE', 'local' ) );

define( 'WP_HOME',    bp_env( 'WP_HOME',    'http://wp-boilerplate.test' ) );
define( 'WP_SITEURL', bp_env( 'WP_SITEURL', WP_HOME . '/wp' ) );
define( 'WP_CONTENT_URL', bp_env( 'WP_CONTENT_URL', WP_HOME . '/content' ) );

// -------------------------------------------------------------------------------------------------
// Database
// -------------------------------------------------------------------------------------------------

define( 'DB_NAME',     bp_env( 'DB_NAME',     'wordpress' ) );
define( 'DB_USER',     bp_env( 'DB_USER',     'wordpress' ) );
define( 'DB_PASSWORD', bp_env( 'DB_PASSWORD', 'wordpress' ) );
define( 'DB_HOST',     bp_env( 'DB_HOST',     '127.0.0.1' ) );
define( 'DB_CHARSET',  bp_env( 'DB_CHARSET',  'utf8mb4' ) );
define( 'DB_COLLATE',  bp_env( 'DB_COLLATE',  '' ) );

$table_prefix = bp_env( 'WP_TABLE_PREFIX', 'wp_' );

// -------------------------------------------------------------------------------------------------
// Debugging
// -------------------------------------------------------------------------------------------------

$is_local = in_array( WP_ENVIRONMENT_TYPE, [ 'local', 'development' ], true );

define( 'WP_DEBUG',         bp_env_bool( 'WP_DEBUG', $is_local ) );
define( 'WP_DEBUG_LOG',     bp_env_bool( 'WP_DEBUG_LOG', $is_local ) );
define( 'WP_DEBUG_DISPLAY', bp_env_bool( 'WP_DEBUG_DISPLAY', $is_local ) );
define( 'SCRIPT_DEBUG',     bp_env_bool( 'SCRIPT_DEBUG', $is_local ) );

if( WP_DEBUG_DISPLAY ) {

    @ini_set( 'display_errors', '1' );

} else {

    @ini_set( 'display_errors', '0' );
}

// -------------------------------------------------------------------------------------------------
// Security / updates
// -------------------------------------------------------------------------------------------------

define( 'DISALLOW_FILE_EDIT', true );
define( 'FORCE_SSL_ADMIN', bp_env_bool( 'FORCE_SSL_ADMIN', str_starts_with( WP_HOME, 'https://' ) ) );

// Composer owns WordPress core/plugins/themes in this seat. Keep automatic code updates disabled by default.
define( 'AUTOMATIC_UPDATER_DISABLED', bp_env_bool( 'AUTOMATIC_UPDATER_DISABLED', true ) );

// -------------------------------------------------------------------------------------------------
// Memory / uploads
// -------------------------------------------------------------------------------------------------

define( 'WP_MEMORY_LIMIT',     bp_env( 'WP_MEMORY_LIMIT',     '256M' ) );
define( 'WP_MAX_MEMORY_LIMIT', bp_env( 'WP_MAX_MEMORY_LIMIT', '512M' ) );

// -------------------------------------------------------------------------------------------------
// Salts
// -------------------------------------------------------------------------------------------------
// Generate production salts at: https://api.wordpress.org/secret-key/1.1/salt/
// Real environment variables are supported so production secrets do not need to live in this file.

define( 'AUTH_KEY',         bp_env( 'AUTH_KEY',         'put your unique phrase here' ) );
define( 'SECURE_AUTH_KEY',  bp_env( 'SECURE_AUTH_KEY',  'put your unique phrase here' ) );
define( 'LOGGED_IN_KEY',    bp_env( 'LOGGED_IN_KEY',    'put your unique phrase here' ) );
define( 'NONCE_KEY',        bp_env( 'NONCE_KEY',        'put your unique phrase here' ) );
define( 'AUTH_SALT',        bp_env( 'AUTH_SALT',        'put your unique phrase here' ) );
define( 'SECURE_AUTH_SALT', bp_env( 'SECURE_AUTH_SALT', 'put your unique phrase here' ) );
define( 'LOGGED_IN_SALT',   bp_env( 'LOGGED_IN_SALT',   'put your unique phrase here' ) );
define( 'NONCE_SALT',       bp_env( 'NONCE_SALT',       'put your unique phrase here' ) );
