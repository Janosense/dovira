<?php

namespace dovira\SearchStats;

/**
 * Writes the rows of the search table; its only caller is the route
 * (RecordController), after the request has been validated.
 */
final class Repository {

	/**
	 * Inserts one row, created now in UTC. A null $results is stored as NULL:
	 * $wpdb->insert() writes NULL for a null value whatever its format.
	 *
	 * @return bool Whether the row was written.
	 */
	public static function insert( string $level, string $query_text, int $context_id, ?int $results ): bool {
		global $wpdb;

		$written = $wpdb->insert(
			Schema::table_name(),
			[
				'level'      => $level,
				'query_text' => $query_text,
				'context_id' => $context_id,
				'results'    => $results,
				'created_at' => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%d', '%d', '%s' ]
		);

		return false !== $written;
	}
}
