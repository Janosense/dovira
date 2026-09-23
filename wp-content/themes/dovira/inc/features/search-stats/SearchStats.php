<?php

namespace dovira\SearchStats;

/**
 * The six lists of the daily report: for each level, the top queries of
 * yesterday and of the last 28 days. Built fresh for every report by
 * Stats::build(); nothing of it is stored.
 */
final class SearchStats {

	/**
	 * @param list<TopQuery> $site_yesterday     The header search, yesterday.
	 * @param list<TopQuery> $site_28_days       The header search, the last 28 days.
	 * @param list<TopQuery> $services_yesterday The services block filter, yesterday.
	 * @param list<TopQuery> $services_28_days   The services block filter, the last 28 days.
	 * @param list<TopQuery> $service_yesterday  A service's price-list filter, yesterday.
	 * @param list<TopQuery> $service_28_days    A service's price-list filter, the last 28 days.
	 */
	public function __construct(
		public readonly array $site_yesterday,
		public readonly array $site_28_days,
		public readonly array $services_yesterday,
		public readonly array $services_28_days,
		public readonly array $service_yesterday,
		public readonly array $service_28_days,
	) {
	}
}
