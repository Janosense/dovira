<?php

namespace dovira\SearchStats;

/**
 * Reads what the daily report prints: two queries per level — yesterday and
 * the last 28 days — top five each, straight from the table every time
 * (docs/features/search-stats/FEATURE.md → Invariants).
 */
final class Stats {

	/**
	 * @param int|null $now Unix time the periods are measured from; the current time when null.
	 */
	public static function build( ?int $now = null ): SearchStats {
		$now          = $now ?? time();
		$yesterday    = Periods::yesterday( $now );
		$last_28_days = Periods::last_28_days( $now );

		$lists = [];

		foreach ( [ RecordController::LEVEL_SITE, RecordController::LEVEL_SERVICES, RecordController::LEVEL_SERVICE ] as $level ) {
			$lists[] = Repository::top( $level, $yesterday['from'], $yesterday['to'] );
			$lists[] = Repository::top( $level, $last_28_days['from'], $last_28_days['to'] );
		}

		return new SearchStats( ...$lists );
	}
}
