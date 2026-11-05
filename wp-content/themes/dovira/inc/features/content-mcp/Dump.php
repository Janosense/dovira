<?php

namespace dovira\ContentMcp;

/**
 * The database dump taken inside the first confirmed write of a session
 * (DECISIONS 2026-11-05 — A database dump is taken inside the first confirmed
 * write of a session, into a web-closed folder, a new timestamped file every
 * time).
 *
 * PHP only (`$wpdb`: `SHOW TABLES`, `SHOW CREATE TABLE`, `INSERT`s of 500
 * rows, gzip), into `DOVIRA_MCP_DUMP_DIR` or the fallback
 * `wp-content/backup-db/` behind an `.htaccess` deny. A file is never
 * overwritten or renamed; any failure throws, and the caller writes nothing.
 */
final class Dump {

	/** The daily WP-Cron event that deletes old dumps. */
	public const HOOK = 'dovira_mcp_dump_retention';

	/** Days a dump is kept unless filtered (`dovira_mcp_dump_retention_days`). */
	public const RETENTION_DAYS = 90;

	public const BATCH = 500;

	/** A dump file of this feature, and nothing else in the folder. */
	public const PATTERN = '/^dovira-[A-Za-z0-9.\-]+-\d{4}-\d{2}-\d{2}-\d{6}(-\d+)?\.sql\.gz$/';

	private const DUMPED = 'dovira_mcp_dumped_';

	private const HTACCESS = "# Written by the theme feature content-mcp: database dumps, never served.\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";

	private object $wpdb;
	private ?string $configured;
	private ?string $fallback;
	private ?string $host;

	/**
	 * @param object|null $wpdb     `wpdb`; null uses the global one.
	 * @param string|null $dir      The configured folder; null reads `DOVIRA_MCP_DUMP_DIR`.
	 * @param string|null $fallback The folder without one; null is `WP_CONTENT_DIR . '/backup-db'`.
	 * @param string|null $host     The host in the file name; null is `home_url()`'s.
	 */
	public function __construct( ?object $wpdb = null, ?string $dir = null, ?string $fallback = null, ?string $host = null ) {
		$this->wpdb       = $wpdb ?? $GLOBALS['wpdb'];
		$this->configured = $dir ?? ( defined( 'DOVIRA_MCP_DUMP_DIR' ) ? (string) constant( 'DOVIRA_MCP_DUMP_DIR' ) : null );
		$this->fallback   = $fallback;
		$this->host       = $host;
	}

	/**
	 * The session's dump: `"existing"` when this session has one, otherwise
	 * the name of the file written now. An empty session dumps every time.
	 *
	 * @throws \RuntimeException When the dump cannot be written completely.
	 */
	public function ensure( string $session_id ): string {
		if ( '' !== $session_id ) {
			$done = get_transient( self::DUMPED . $session_id );
			if ( is_string( $done ) && '' !== $done ) {
				return 'existing';
			}
		}

		$path = $this->free_path( $this->folder() );
		$this->write( $path );

		if ( ! chmod( $path, 0600 ) ) {
			wp_delete_file( $path );
			throw new \RuntimeException( 'Не вдалося закрити доступ до файлу дампу.' );
		}

		$name = basename( $path );
		if ( '' !== $session_id ) {
			set_transient( self::DUMPED . $session_id, $name, DAY_IN_SECONDS );
		}

		return $name;
	}

	/**
	 * On `init`: the daily retention event, scheduled once.
	 */
	public static function schedule(): void {
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::HOOK );
		}
	}

	/**
	 * On the retention event.
	 */
	public static function retain(): void {
		( new self() )->prune();
	}

	/**
	 * Deletes this feature's dump files older than the retention days (never
	 * fewer than 1) from the folder in use; nothing else is touched.
	 *
	 * @return int How many files were deleted.
	 */
	public function prune( ?int $now = null ): int {
		$days  = max( 1, (int) apply_filters( 'dovira_mcp_dump_retention_days', self::RETENTION_DAYS ) );
		$dir   = $this->configured_dir() ?? $this->fallback_dir();
		$limit = ( $now ?? time() ) - $days * DAY_IN_SECONDS;

		if ( ! is_dir( $dir ) ) {
			return 0;
		}

		$deleted = 0;
		foreach ( (array) scandir( $dir ) as $name ) {
			$path = $dir . '/' . $name;
			if ( ! is_string( $name ) || ! preg_match( self::PATTERN, $name ) || ! is_file( $path ) ) {
				continue;
			}
			$mtime = filemtime( $path );
			if ( false !== $mtime && $mtime < $limit ) {
				wp_delete_file( $path );
				$deleted += file_exists( $path ) ? 0 : 1;
			}
		}

		return $deleted;
	}

	/**
	 * The folder to write into, created when missing; the fallback gets its
	 * `.htaccess` deny and an empty `index.php`.
	 *
	 * @throws \RuntimeException When it cannot be created or written.
	 */
	private function folder(): string {
		$configured = $this->configured_dir();
		$dir        = $configured ?? $this->fallback_dir();

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			throw new \RuntimeException( sprintf( 'Не вдалося створити папку дампів %s.', $dir ) );
		}
		if ( ! is_writable( $dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
			throw new \RuntimeException( sprintf( 'Папка дампів %s недоступна для запису.', $dir ) );
		}

		if ( null === $configured ) {
			foreach ( [ '.htaccess' => self::HTACCESS, 'index.php' => '' ] as $file => $contents ) {
				if ( ! file_exists( $dir . '/' . $file ) && false === file_put_contents( $dir . '/' . $file, $contents ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
					throw new \RuntimeException( sprintf( 'Не вдалося захистити папку дампів %s.', $dir ) );
				}
			}
		}

		return $dir;
	}

	private function configured_dir(): ?string {
		return null !== $this->configured && '' !== trim( $this->configured ) ? rtrim( $this->configured, '/' ) : null;
	}

	private function fallback_dir(): string {
		return rtrim( $this->fallback ?? WP_CONTENT_DIR . '/backup-db', '/' );
	}

	/**
	 * `dovira-{host}-{Y-m-d}-{His}.sql.gz` in site time; a name that exists
	 * gets `-2`, `-3`, … — nothing is ever overwritten.
	 */
	private function free_path( string $dir ): string {
		$host = (string) preg_replace( '/[^A-Za-z0-9.\-]/', '-', $this->host ?? (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$base = sprintf( '%s/dovira-%s-%s', $dir, '' !== $host ? $host : 'site', wp_date( 'Y-m-d-His' ) );

		$path = $base . '.sql.gz';
		for ( $n = 2; file_exists( $path ); $n++ ) {
			$path = sprintf( '%s-%d.sql.gz', $base, $n );
		}

		return $path;
	}

	/**
	 * Writes the whole database to `$path`; on any failure the file is deleted
	 * and the failure thrown.
	 *
	 * @throws \RuntimeException
	 */
	private function write( string $path ): void {
		$started = microtime( true );

		// Reserve the name atomically ('x' fails when it exists), then gzip into it.
		$reserved = fopen( $path, 'xb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $reserved ) {
			throw new \RuntimeException( sprintf( 'Не вдалося створити файл дампу %s.', basename( $path ) ) );
		}
		fclose( $reserved ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$gz = gzopen( $path, 'wb6' );
		if ( false === $gz ) {
			wp_delete_file( $path );
			throw new \RuntimeException( sprintf( 'Не вдалося створити файл дампу %s.', basename( $path ) ) );
		}

		try {
			$this->put( $gz, sprintf( "-- Dovira content-mcp dump\n-- host: %s\n-- date: %s\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n", $this->host ?? (string) wp_parse_url( home_url(), PHP_URL_HOST ), wp_date( 'c' ) ) );

			$tables = $this->wpdb->get_col( 'SHOW TABLES' );
			$this->check( 'SHOW TABLES' );
			if ( ! is_array( $tables ) || [] === $tables ) {
				throw new \RuntimeException( 'База даних не повернула жодної таблиці.' );
			}

			foreach ( $tables as $table ) {
				$this->table( $gz, (string) $table );
			}

			$this->put(
				$gz,
				sprintf(
					"SET FOREIGN_KEY_CHECKS=1;\n-- tables: %d\n-- finished in %.2f s, peak memory %.1f MB\n",
					count( $tables ),
					microtime( true ) - $started,
					memory_get_peak_usage( true ) / 1048576
				)
			);
		} catch ( \Throwable $e ) {
			gzclose( $gz );
			wp_delete_file( $path );
			throw new \RuntimeException( 'Дамп бази не створено: ' . $e->getMessage(), 0, $e );
		}

		if ( ! gzclose( $gz ) ) {
			wp_delete_file( $path );
			throw new \RuntimeException( 'Дамп бази не створено: файл не закрився.' );
		}
	}

	/**
	 * @param resource $gz
	 */
	private function table( $gz, string $table ): void {
		$quoted = '`' . str_replace( '`', '``', $table ) . '`';

		$create = $this->wpdb->get_row( "SHOW CREATE TABLE {$quoted}", 'ARRAY_N' );
		$this->check( "SHOW CREATE TABLE {$table}" );
		if ( ! is_array( $create ) || empty( $create[1] ) ) {
			throw new \RuntimeException( sprintf( 'Немає опису таблиці %s.', $table ) );
		}

		$this->put( $gz, "DROP TABLE IF EXISTS {$quoted};\n" . $create[1] . ";\n\n" );

		for ( $offset = 0; ; $offset += self::BATCH ) {
			$rows = $this->wpdb->get_results( sprintf( 'SELECT * FROM %s LIMIT %d OFFSET %d', $quoted, self::BATCH, $offset ), 'ARRAY_N' );
			$this->check( "SELECT {$table}" );
			if ( ! is_array( $rows ) ) {
				throw new \RuntimeException( sprintf( 'Не прочитано рядки таблиці %s.', $table ) );
			}
			if ( [] === $rows ) {
				break;
			}

			$values = [];
			foreach ( $rows as $row ) {
				$values[] = '(' . implode( ',', array_map( [ $this, 'quote' ], (array) $row ) ) . ')';
			}
			$this->put( $gz, "INSERT INTO {$quoted} VALUES\n" . implode( ",\n", $values ) . ";\n" );

			if ( count( $rows ) < self::BATCH ) {
				break;
			}
		}

		$this->put( $gz, "\n" );
	}

	/**
	 * @param mixed $value
	 */
	private function quote( $value ): string {
		if ( null === $value ) {
			return 'NULL';
		}

		return "'" . $this->wpdb->remove_placeholder_escape( $this->wpdb->_real_escape( (string) $value ) ) . "'";
	}

	/**
	 * @param resource $gz
	 */
	private function put( $gz, string $sql ): void {
		$written = gzwrite( $gz, $sql );
		if ( false === $written || ( '' !== $sql && 0 === $written ) ) {
			throw new \RuntimeException( 'Запис у файл дампу не вдався.' );
		}
	}

	private function check( string $what ): void {
		$error = (string) ( $this->wpdb->last_error ?? '' );
		if ( '' !== $error ) {
			throw new \RuntimeException( sprintf( '%s: %s', $what, $error ) );
		}
	}
}
