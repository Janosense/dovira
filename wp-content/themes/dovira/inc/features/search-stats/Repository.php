<?php

namespace dovira\SearchStats;

/**
 * Writes the rows of the search table — its only writer is the route
 * (RecordController), after the request has been validated — and reads the
 * top queries the daily report prints.
 */
final class Repository {

	/** How many queries a list holds (DECISIONS "Three blocks, each with a yesterday list and a 28-day list"). */
	public const TOP = 5;

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

	/**
	 * The most frequent queries of one level in one period, most frequent
	 * first and, between equals, the most recently searched first.
	 *
	 * One query through the key level_created_at: equality on level, a range
	 * on created_at. Rows group by the query text and where it was typed; the
	 * grouping follows the column's collation (docs/DATA-MODEL.md). A query is
	 * "nothing found" only when every search of it in the period showed no
	 * results — MAX(results) = 0; the filter levels store NULL and never are.
	 *
	 * @param string $from UTC lower bound, inclusive, Y-m-d H:i:s.
	 * @param string $to   UTC upper bound, exclusive, Y-m-d H:i:s.
	 * @return list<TopQuery>
	 */
	public static function top( string $level, string $from, string $to ): array {
		global $wpdb;

		$table = Schema::table_name();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT query_text, context_id, COUNT(*) AS n, MAX(results) AS max_results, MAX(created_at) AS last_at FROM {$table} WHERE level = %s AND created_at >= %s AND created_at < %s GROUP BY query_text, context_id ORDER BY n DESC, last_at DESC LIMIT %d",
				$level,
				$from,
				$to,
				self::TOP
			)
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$top = [];

		foreach ( $rows as $row ) {
			$top[] = new TopQuery(
				(string) $row->query_text,
				(int) $row->context_id,
				(int) $row->n,
				null !== $row->max_results && 0 === (int) $row->max_results
			);
		}

		return $top;
	}
}
