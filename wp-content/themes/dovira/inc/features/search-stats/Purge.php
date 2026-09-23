<?php

namespace dovira\SearchStats;

/**
 * Deletes recorded searches older than the retention period, once a day from
 * WP-Cron — the only delete the feature does, never from a public request.
 */
final class Purge {

	public const HOOK = 'dovira_search_stats_purge';
	public const FILTER = 'dovira_search_stats_retention_days';
	public const DEFAULT_RETENTION_DAYS = 90;
	/** The report reads 28 days back; one more day keeps a whole period in the table. */
	public const MIN_RETENTION_DAYS = 29;

	public static function retention_days(): int {
		return max( self::MIN_RETENTION_DAYS, (int) apply_filters( self::FILTER, self::DEFAULT_RETENTION_DAYS ) );
	}

	/**
	 * Registers the daily event when it is not scheduled yet; runs on init.
	 */
	public static function schedule(): void {
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::HOOK );
		}
	}

	/**
	 * @param int|null $now Unix time to measure the period from; the current time when null.
	 */
	public static function run( ?int $now = null ): void {
		global $wpdb;

		$now    = $now ?? time();
		$cutoff = gmdate( 'Y-m-d H:i:s', $now - self::retention_days() * DAY_IN_SECONDS );
		$table  = Schema::table_name();

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
	}
}
