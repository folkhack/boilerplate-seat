<?php
/**
 * WordPress domain/path migration helper.
 *
 * Loads root wp_config.php, connects to MySQL/MariaDB, scans WordPress tables,
 * and replaces one URL/domain/path with another.
 *
 * Dry-run is the default. Pass --execute to write changes.
 *
 * Examples:
 *
 *   php scripts/migrate_wp_domain.php --from=http://old.test --to=http://new.test
 *
 *   php scripts/migrate_wp_domain.php --from=https://old.com --to=https://new.com/blog --execute
 *
 *   php scripts/migrate_wp_domain.php --from=https://old.com/site --to=https://new.com --include-guid --execute
 */

declare( strict_types=1 );

$project_root = dirname( __DIR__ );
$config_file  = $project_root . '/wp_config.php';

if( ! is_readable( $config_file ) ) {
    fwrite( STDERR, "Missing readable config file: wp_config.php\n" );
    exit( 1 );
}

require $config_file;

$options = getopt( '', [
    'from:',
    'to:',
    'execute',
    'include-guid',
    'tables:',
] );

$from = isset( $options['from'] ) ? trim( (string) $options['from'] ) : '';
$to   = isset( $options['to'] ) ? trim( (string) $options['to'] ) : '';

$execute      = array_key_exists( 'execute', $options );
$include_guid = array_key_exists( 'include-guid', $options );

if( $from === '' || $to === '' ) {
    fwrite( STDERR, "Usage: php scripts/migrate_wp_domain.php --from=<old> --to=<new> [--execute] [--include-guid] [--tables=wp_options,wp_posts]\n" );
    exit( 1 );
}

if( $from === $to ) {
    fwrite( STDERR, "--from and --to are identical; nothing to migrate.\n" );
    exit( 1 );
}

if( ! defined( 'DB_NAME' ) || ! defined( 'DB_USER' ) || ! defined( 'DB_PASSWORD' ) || ! defined( 'DB_HOST' ) ) {
    fwrite( STDERR, "DB_NAME, DB_USER, DB_PASSWORD, and DB_HOST must be defined by wp_config.php.\n" );
    exit( 1 );
}

if( ! isset( $table_prefix ) || ! is_string( $table_prefix ) || $table_prefix === '' ) {
    fwrite( STDERR, '$table_prefix must be defined by wp_config.php.' . "\n" );
    exit( 1 );
}

function bp_migration_log( string $message ): void {

    fwrite( STDOUT, '[wp_migration] ' . $message . PHP_EOL );
}

function bp_quote_identifier( string $identifier ): string {

    return '`' . str_replace( '`', '``', $identifier ) . '`';
}

function bp_parse_db_host( string $db_host ): array {

    $host   = $db_host;
    $port   = null;
    $socket = null;

    if( str_contains( $db_host, ':' ) ) {

        [ $host_part, $extra ] = explode( ':', $db_host, 2 );

        if( str_starts_with( $extra, '/' ) ) {
            $host   = $host_part;
            $socket = $extra;
        } elseif( ctype_digit( $extra ) ) {
            $host = $host_part;
            $port = $extra;
        }
    }

    return [
        'host'   => $host,
        'port'   => $port,
        'socket' => $socket,
    ];
}

function bp_pdo(): PDO {

    $host_parts = bp_parse_db_host( DB_HOST );

    $dsn_parts = [
        'dbname=' . DB_NAME,
        'charset=' . ( defined( 'DB_CHARSET' ) ? DB_CHARSET : 'utf8mb4' ),
    ];

    if( $host_parts['socket'] ) {
        $dsn_parts[] = 'unix_socket=' . $host_parts['socket'];
    } else {
        $dsn_parts[] = 'host=' . $host_parts['host'];

        if( $host_parts['port'] ) {
            $dsn_parts[] = 'port=' . $host_parts['port'];
        }
    }

    return new PDO(
        'mysql:' . implode( ';', $dsn_parts ),
        DB_USER,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ]
    );
}

function bp_is_text_column_type( string $type ): bool {

    $type = strtolower( $type );

    return (
        str_contains( $type, 'char' ) ||
        str_contains( $type, 'text' ) ||
        str_contains( $type, 'json' )
    );
}

function bp_is_serialized( mixed $value ): bool {

    if( ! is_string( $value ) ) {
        return false;
    }

    $value = trim( $value );

    if( $value === 'N;' ) {
        return true;
    }

    if( strlen( $value ) < 4 || $value[1] !== ':' ) {
        return false;
    }

    $token = $value[0];

    if( ! in_array( $token, [ 's', 'a', 'O', 'b', 'i', 'd' ], true ) ) {
        return false;
    }

    return @unserialize( $value ) !== false || $value === 'b:0;';
}

function bp_replace_scalar_string( string $value, array $pairs, int &$replacement_count ): string {

    $new_value = $value;

    foreach( $pairs as $from => $to ) {

        if( $from === '' ) {
            continue;
        }

        $count = substr_count( $new_value, $from );

        if( $count > 0 ) {
            $new_value = str_replace( $from, $to, $new_value );
            $replacement_count += $count;
        }
    }

    return $new_value;
}

function bp_replace_recursive( mixed $value, array $pairs, int &$replacement_count ): mixed {

    if( is_string( $value ) ) {
        return bp_replace_scalar_string( $value, $pairs, $replacement_count );
    }

    if( is_array( $value ) ) {

        $new = [];

        foreach( $value as $key => $item ) {
            $new_key = is_string( $key )
                ? bp_replace_scalar_string( $key, $pairs, $replacement_count )
                : $key;

            $new[ $new_key ] = bp_replace_recursive( $item, $pairs, $replacement_count );
        }

        return $new;
    }

    if( is_object( $value ) ) {

        foreach( get_object_vars( $value ) as $key => $item ) {
            $value->{$key} = bp_replace_recursive( $item, $pairs, $replacement_count );
        }

        return $value;
    }

    return $value;
}

function bp_replace_database_value( mixed $value, array $pairs, int &$replacement_count ): mixed {

    if( ! is_string( $value ) || $value === '' ) {
        return $value;
    }

    if( bp_is_serialized( $value ) ) {

        $unserialized = @unserialize( $value );

        if( $unserialized === false && $value !== 'b:0;' ) {
            return $value;
        }

        $before_count = $replacement_count;
        $replaced     = bp_replace_recursive( $unserialized, $pairs, $replacement_count );

        if( $replacement_count === $before_count ) {
            return $value;
        }

        return serialize( $replaced );
    }

    return bp_replace_scalar_string( $value, $pairs, $replacement_count );
}

function bp_get_tables( PDO $pdo, string $table_prefix, ?string $table_csv ): array {

    if( $table_csv ) {
        return array_values(
            array_filter(
                array_map( 'trim', explode( ',', $table_csv ) ),
                fn( string $table ): bool => $table !== ''
            )
        );
    }

    $stmt = $pdo->prepare( 'SHOW TABLES LIKE :prefix' );
    $stmt->execute( [
        ':prefix' => str_replace( [ '_', '%' ], [ '\\_', '\\%' ], $table_prefix ) . '%',
    ] );

    return array_map(
        static fn( array $row ): string => (string) array_values( $row )[0],
        $stmt->fetchAll()
    );
}

function bp_get_primary_key_column( PDO $pdo, string $table ): ?string {

    $stmt = $pdo->query( 'SHOW KEYS FROM ' . bp_quote_identifier( $table ) . " WHERE Key_name = 'PRIMARY'" );
    $rows = $stmt->fetchAll();

    if( count( $rows ) !== 1 ) {
        return null;
    }

    return (string) $rows[0]['Column_name'];
}

function bp_get_text_columns( PDO $pdo, string $table, bool $include_guid ): array {

    $stmt = $pdo->query( 'SHOW FULL COLUMNS FROM ' . bp_quote_identifier( $table ) );
    $rows = $stmt->fetchAll();

    $columns = [];

    foreach( $rows as $row ) {
        $field = (string) $row['Field'];
        $type  = (string) $row['Type'];

        if( ! $include_guid && $field === 'guid' ) {
            continue;
        }

        if( bp_is_text_column_type( $type ) ) {
            $columns[] = $field;
        }
    }

    return $columns;
}

$plain_from = rtrim( $from, '/' );
$plain_to   = rtrim( $to, '/' );

$pairs = [
    $from => $to,
];

if( $plain_from !== $from || $plain_to !== $to ) {
    $pairs[ $plain_from ] = $plain_to;
}

$pairs[ str_replace( '/', '\\/', $from ) ] = str_replace( '/', '\\/', $to );

if( $plain_from !== $from || $plain_to !== $to ) {
    $pairs[ str_replace( '/', '\\/', $plain_from ) ] = str_replace( '/', '\\/', $plain_to );
}

$pdo = bp_pdo();

bp_migration_log( $execute ? 'Mode: EXECUTE' : 'Mode: DRY RUN' );
bp_migration_log( 'From: ' . $from );
bp_migration_log( 'To:   ' . $to );
bp_migration_log( 'Table prefix: ' . $table_prefix );
bp_migration_log( 'GUID column: ' . ( $include_guid ? 'included' : 'skipped' ) );

$table_csv = isset( $options['tables'] ) ? (string) $options['tables'] : null;
$tables    = bp_get_tables( $pdo, $table_prefix, $table_csv );

$total_rows_scanned = 0;
$total_rows_changed = 0;
$total_replacements = 0;

$report = [];

foreach( $tables as $table ) {

    $primary_key = bp_get_primary_key_column( $pdo, $table );

    if( ! $primary_key ) {
        $report[] = [ $table, 'SKIPPED', 'No single-column primary key', 0, 0, 0 ];
        continue;
    }

    $columns = bp_get_text_columns( $pdo, $table, $include_guid );

    if( ! $columns ) {
        $report[] = [ $table, 'SKIPPED', 'No text columns', 0, 0, 0 ];
        continue;
    }

    $select_columns = array_merge( [ $primary_key ], $columns );
    $select_sql     = 'SELECT ' . implode( ', ', array_map( 'bp_quote_identifier', $select_columns ) ) .
        ' FROM ' . bp_quote_identifier( $table );

    $stmt = $pdo->query( $select_sql );

    $table_rows_scanned = 0;
    $table_rows_changed = 0;
    $table_replacements = 0;

    while( $row = $stmt->fetch() ) {

        $table_rows_scanned++;
        $total_rows_scanned++;

        $updates = [];

        foreach( $columns as $column ) {

            $original = $row[ $column ];

            if( $original === null || $original === '' ) {
                continue;
            }

            $cell_replacements = 0;
            $replaced          = bp_replace_database_value( $original, $pairs, $cell_replacements );

            if( $cell_replacements > 0 && $replaced !== $original ) {
                $updates[ $column ] = $replaced;
                $table_replacements += $cell_replacements;
                $total_replacements += $cell_replacements;
            }
        }

        if( ! $updates ) {
            continue;
        }

        $table_rows_changed++;
        $total_rows_changed++;

        if( $execute ) {

            $set_parts = [];
            $params    = [];

            foreach( $updates as $column => $value ) {
                $param = ':value_' . count( $params );
                $set_parts[] = bp_quote_identifier( $column ) . ' = ' . $param;
                $params[ $param ] = $value;
            }

            $params[':primary_key'] = $row[ $primary_key ];

            $update_sql = 'UPDATE ' . bp_quote_identifier( $table ) .
                ' SET ' . implode( ', ', $set_parts ) .
                ' WHERE ' . bp_quote_identifier( $primary_key ) . ' = :primary_key' .
                ' LIMIT 1';

            $update = $pdo->prepare( $update_sql );
            $update->execute( $params );
        }
    }

    $report[] = [
        $table,
        $execute ? 'UPDATED' : 'DRY-RUN',
        implode( ',', $columns ),
        $table_rows_scanned,
        $table_rows_changed,
        $table_replacements,
    ];
}

fwrite( STDOUT, PHP_EOL );
fwrite( STDOUT, str_pad( 'Table', 34 ) . str_pad( 'Mode', 10 ) . str_pad( 'Rows', 10 ) . str_pad( 'Changed', 10 ) . str_pad( 'Repl.', 10 ) . "Columns / note\n" );
fwrite( STDOUT, str_repeat( '-', 110 ) . PHP_EOL );

foreach( $report as $row ) {
    [ $table, $mode, $note, $rows_scanned, $rows_changed, $replacements ] = $row;

    fwrite(
        STDOUT,
        str_pad( $table, 34 ) .
        str_pad( $mode, 10 ) .
        str_pad( (string) $rows_scanned, 10 ) .
        str_pad( (string) $rows_changed, 10 ) .
        str_pad( (string) $replacements, 10 ) .
        $note .
        PHP_EOL
    );
}

fwrite( STDOUT, str_repeat( '-', 110 ) . PHP_EOL );
bp_migration_log( 'Rows scanned: ' . $total_rows_scanned );
bp_migration_log( 'Rows changed: ' . $total_rows_changed );
bp_migration_log( 'Replacements: ' . $total_replacements );

if( ! $execute ) {
    bp_migration_log( 'Dry run only. Re-run with --execute to write changes.' );
}

exit( 0 );
