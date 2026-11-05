<?php
/**
 * THROWAWAY — content-mcp Sprint 1 Step 1 spike. Not loaded by the theme or
 * any plugin; delete after Sprint 1 Step 3 ships `Dump`.
 *
 * Measures a PHP-only dump of this install's database ($wpdb: SHOW TABLES,
 * SHOW CREATE TABLE, batched INSERTs, gzip) and prints what the real `Dump`
 * needs to know about the host.
 *
 *   wp eval-file content-mcp-dump-spike.php /path/outside/webroot
 *
 * The folder must exist and be writable. The dump file is left there for
 * inspection (`zcat … | head`); delete it by hand.
 */

// A URL hit (the dev FTP sync carries this file) runs nothing.
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

global $wpdb;

$dir = isset( $args[0] ) ? rtrim( (string) $args[0], '/' ) : '';
if ( '' === $dir || ! is_dir( $dir ) || ! is_writable( $dir ) ) {
	WP_CLI::error( 'Pass an existing writable folder as the first argument.' );
}

$real_dir  = (string) realpath( $dir );
$real_root = (string) realpath( ABSPATH );
$inside    = str_starts_with( $real_dir . '/', rtrim( $real_root, '/' ) . '/' );

$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
$file = sprintf( '%s/dovira-%s-%s.sql.gz', $real_dir, $host, gmdate( 'Y-m-d-His' ) );
if ( file_exists( $file ) ) {
	WP_CLI::error( "Refusing to overwrite $file" );
}

$batch   = 500;
$raw     = 0;
$rows    = 0;
$started = microtime( true );

$gz = gzopen( $file, 'wb6' );
if ( false === $gz ) {
	WP_CLI::error( "Cannot open $file" );
}

$write = static function ( string $sql ) use ( $gz, &$raw ): void {
	$raw += strlen( $sql );
	gzwrite( $gz, $sql );
};

$quote = static function ( $value ) use ( $wpdb ): string {
	if ( null === $value ) {
		return 'NULL';
	}
	return "'" . $wpdb->remove_placeholder_escape( $wpdb->_real_escape( (string) $value ) ) . "'";
};

$write( "-- dovira content-mcp dump spike\n-- host: $host\n-- " . gmdate( 'c' ) . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n" );

$tables = $wpdb->get_col( 'SHOW TABLES' );
foreach ( $tables as $table ) {
	$create = $wpdb->get_row( "SHOW CREATE TABLE `$table`", ARRAY_N );
	$write( "DROP TABLE IF EXISTS `$table`;\n" . $create[1] . ";\n\n" );

	for ( $offset = 0; ; $offset += $batch ) {
		$result = $wpdb->get_results( "SELECT * FROM `$table` LIMIT $batch OFFSET $offset", ARRAY_N );
		if ( empty( $result ) ) {
			break;
		}
		$values = [];
		foreach ( $result as $row ) {
			$values[] = '(' . implode( ',', array_map( $quote, $row ) ) . ')';
		}
		$rows += count( $result );
		$write( "INSERT INTO `$table` VALUES\n" . implode( ",\n", $values ) . ";\n" );
		if ( count( $result ) < $batch ) {
			break;
		}
	}
	$write( "\n" );
}

$write( "SET FOREIGN_KEY_CHECKS=1;\n" );
gzclose( $gz );
chmod( $file, 0600 );

$seconds  = microtime( true ) - $started;
$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );

WP_CLI::log( 'file:                 ' . $file );
WP_CLI::log( 'folder inside webroot: ' . ( $inside ? 'YES — not acceptable without a deny rule' : 'no' ) );
WP_CLI::log( 'tables:               ' . count( $tables ) );
WP_CLI::log( 'rows:                 ' . $rows );
WP_CLI::log( sprintf( 'raw SQL:              %.2f MB', $raw / 1048576 ) );
WP_CLI::log( sprintf( 'gz file:              %.2f MB', filesize( $file ) / 1048576 ) );
WP_CLI::log( sprintf( 'seconds:              %.2f', $seconds ) );
WP_CLI::log( sprintf( 'peak memory:          %.1f MB', memory_get_peak_usage( true ) / 1048576 ) );
WP_CLI::log( 'file mode:            ' . substr( sprintf( '%o', fileperms( $file ) ), -4 ) );
WP_CLI::log( 'PHP (CLI):            ' . PHP_VERSION . ' — ' . ( php_ini_loaded_file() ?: 'no php.ini' ) );
WP_CLI::log( 'max_execution_time:   ' . ini_get( 'max_execution_time' ) . ' (CLI value; the web value is the hosting panel\'s)' );
WP_CLI::log( 'memory_limit:         ' . ini_get( 'memory_limit' ) . ' (CLI); WP_MEMORY_LIMIT ' . WP_MEMORY_LIMIT );
WP_CLI::log( 'exec callable:        ' . ( function_exists( 'exec' ) && ! in_array( 'exec', $disabled, true ) ? 'yes' : 'no' ) . ' (information only)' );
WP_CLI::log( 'webroot:              ' . $real_root );
