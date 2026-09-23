<?php

namespace dovira\SearchStats;

/**
 * One row of a top-5 list: a query, where it was typed, how often in the
 * period, and whether every one of those searches found nothing.
 */
final class TopQuery {

	/**
	 * @param string $query         The normalized query text, as stored.
	 * @param int    $context_id    The page or service it was typed in; 0 for the site level.
	 * @param int    $count         How many times it was searched in the period.
	 * @param bool   $nothing_found Whether every search of it in the period showed no results — the site level only.
	 */
	public function __construct(
		public readonly string $query,
		public readonly int $context_id,
		public readonly int $count,
		public readonly bool $nothing_found,
	) {
	}
}
