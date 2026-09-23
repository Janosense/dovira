<?php

namespace dovira\SearchStats;

/**
 * The table every recorded search lands in, created and upgraded with dbDelta()
 * from a schema version kept in an option (docs/DATA-MODEL.md → Feature search-stats).
 */
final class Schema {

	public const VERSION = 1;
	public const OPTION = 'dovira_search_stats_db_version';
	public const TABLE = 'dovira_search_queries';

	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Written to dbDelta()'s rules: one column per line, two spaces after
	 * PRIMARY KEY, KEY rather than INDEX, lowercase types, no backticks. The
	 * integer widths are the ones MariaDB's DESCRIBE prints, so a later run
	 * finds nothing to alter.
	 */
	public static function create_table_sql(): string {
		global $wpdb;

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  level varchar(16) NOT NULL,
  query_text varchar(100) NOT NULL,
  context_id bigint(20) unsigned NOT NULL DEFAULT 0,
  results int(10) unsigned NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY level_created_at (level,created_at),
  KEY created_at (created_at)
) {$charset_collate}";
	}

	/**
	 * Creates or upgrades the table, and records the version only when dbDelta
	 * left no error behind — a failed run is retried on the next request.
	 */
	public static function install(): void {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		dbDelta( self::create_table_sql() );

		if ( '' === $wpdb->last_error ) {
			update_option( self::OPTION, self::VERSION, true );
		}
	}

	/**
	 * Runs on every init, but dbDelta only when the stored version is behind:
	 * the option is autoloaded, so a current install pays one array lookup.
	 */
	public static function maybe_upgrade(): void {
		if ( (int) get_option( self::OPTION, 0 ) < self::VERSION ) {
			self::install();
		}
	}
}
