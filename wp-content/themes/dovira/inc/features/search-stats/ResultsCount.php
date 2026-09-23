<?php

namespace dovira\SearchStats;

/**
 * How many results search.php lists for a query — the figure a site search is
 * recorded with, so it must follow what the template prints, not what it collects.
 */
final class ResultsCount {

	/** The post types search.php lists besides services, each in a block of its own. */
	public const LISTED_TYPES = [ 'post', 'page', 'employee' ];

	/**
	 * Every matched price row, the services at the top level (search.php lists
	 * only those), and every listed type; child services and any other post
	 * type the queries returned are collected by the page but never printed.
	 *
	 * @param array<int, array<string, mixed>>            $sub_services The matched price rows.
	 * @param array<string, array<int, \WP_Post|object>> $search_posts The found posts, keyed by post type, then id.
	 */
	public static function shown( array $sub_services, array $search_posts ): int {
		$count = count( $sub_services );

		foreach ( $search_posts['service'] ?? [] as $service ) {
			if ( 0 === $service->post_parent ) {
				++$count;
			}
		}

		foreach ( self::LISTED_TYPES as $type ) {
			$count += count( $search_posts[ $type ] ?? [] );
		}

		return $count;
	}
}
