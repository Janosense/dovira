<?php
/**
 * Tests for dovira\SearchStats\Repository::top().
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\SearchStats;

use dovira\SearchStats\Repository;
use dovira\SearchStats\TopQuery;
use dovira\Tests\TestCase;
use dovira\Tests\WpdbDouble;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/Schema.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/TopQuery.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/Repository.php';

/**
 * The query built for a level and a period, and the rows it returns shaped
 * into TopQuery objects. The rows are written here, next to the query they
 * answer, the way $wpdb->get_results() returns them: stdClass, every value a
 * string or null (docs/TESTING.md → Fixtures).
 */
final class RepositoryTopTest extends TestCase {

	private const SELECT = 'SELECT query_text, context_id, COUNT(*) AS n, MAX(results) AS max_results, MAX(created_at) AS last_at FROM wp_dovira_search_queries WHERE level = %s AND created_at >= %s AND created_at < %s GROUP BY query_text, context_id ORDER BY n DESC, last_at DESC LIMIT %d';

	private const YESTERDAY = [ '2026-09-21 21:00:00', '2026-09-22 21:00:00' ];

	private const LAST_28_DAYS = [ '2026-08-25 21:00:00', '2026-09-22 21:00:00' ];

	private WpdbDouble $wpdb;

	protected function setUp(): void {
		parent::setUp();

		$this->wpdb      = new WpdbDouble( 'wp_' );
		$GLOBALS['wpdb'] = $this->wpdb;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/**
	 * @return array<string, array{string, array{string, string}}>
	 */
	public static function levels_and_periods(): array {
		return [
			'site, yesterday'         => [ 'site', self::YESTERDAY ],
			'site, 28 days'           => [ 'site', self::LAST_28_DAYS ],
			'services, yesterday'     => [ 'services', self::YESTERDAY ],
			'services, 28 days'       => [ 'services', self::LAST_28_DAYS ],
			'service, yesterday'      => [ 'service', self::YESTERDAY ],
			'service, 28 days'        => [ 'service', self::LAST_28_DAYS ],
		];
	}

	/**
	 * @param array{string, string} $period
	 */
	#[DataProvider( 'levels_and_periods' )]
	public function test_the_query_for_each_level_and_period( string $level, array $period ): void {
		Repository::top( $level, $period[0], $period[1] );

		$this->assertSame( [ [ self::SELECT, [ $level, $period[0], $period[1], 5 ] ] ], $this->wpdb->prepared );
		$this->assertSame(
			[
				"SELECT query_text, context_id, COUNT(*) AS n, MAX(results) AS max_results, MAX(created_at) AS last_at FROM wp_dovira_search_queries WHERE level = '{$level}' AND created_at >= '{$period[0]}' AND created_at < '{$period[1]}' GROUP BY query_text, context_id ORDER BY n DESC, last_at DESC LIMIT 5",
			],
			$this->wpdb->selected
		);
	}

	public function test_rows_become_top_queries_in_the_order_given(): void {
		$this->wpdb->results = [
			[
				$this->row( 'вакцинація', '0', '7', '3', '2026-09-22 10:00:00' ),
				$this->row( 'стерилізація кота', '0', '2', '5', '2026-09-22 18:30:00' ),
			],
		];

		$this->assertEquals(
			[
				new TopQuery( 'вакцинація', 0, 7, false ),
				new TopQuery( 'стерилізація кота', 0, 2, false ),
			],
			Repository::top( 'site', ...self::YESTERDAY )
		);
	}

	/**
	 * Flagged only when the largest results count of the period is 0: a query
	 * searched with 3 results and then with 0 found something once, and the
	 * filter levels, which store no count, are never flagged.
	 */
	public function test_nothing_found_only_when_every_search_found_nothing(): void {
		$this->wpdb->results = [
			[
				$this->row( 'груминг', '0', '2', '0', '2026-09-22 09:00:00' ),
				$this->row( 'рентген', '0', '2', '3', '2026-09-22 08:00:00' ),
				$this->row( 'узі', '12', '4', null, '2026-09-22 07:00:00' ),
			],
		];

		$flags = array_map(
			static fn( TopQuery $top ): bool => $top->nothing_found,
			Repository::top( 'site', ...self::YESTERDAY )
		);

		$this->assertSame( [ true, false, false ], $flags );
	}

	public function test_no_rows_give_an_empty_list(): void {
		$this->wpdb->results = [ [], null ];

		$this->assertSame( [], Repository::top( 'site', ...self::YESTERDAY ), 'no rows' );
		$this->assertSame( [], Repository::top( 'site', ...self::YESTERDAY ), 'a null answer' );
	}

	private function row( string $query_text, string $context_id, string $n, ?string $max_results, string $last_at ): \stdClass {
		return (object) [
			'query_text'  => $query_text,
			'context_id'  => $context_id,
			'n'           => $n,
			'max_results' => $max_results,
			'last_at'     => $last_at,
		];
	}
}
