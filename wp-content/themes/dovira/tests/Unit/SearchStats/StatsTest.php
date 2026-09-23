<?php
/**
 * Tests for dovira\SearchStats\Stats.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\SearchStats;

use Brain\Monkey\Functions;
use DateTimeZone;
use dovira\SearchStats\SearchStats;
use dovira\SearchStats\Stats;
use dovira\SearchStats\TopQuery;
use dovira\Tests\TestCase;
use dovira\Tests\WpdbDouble;

require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/Schema.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/Periods.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/TopQuery.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/Repository.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/RecordController.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/SearchStats.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/Stats.php';

/**
 * The six lists: one query per level and period, in a fixed order, with the
 * bounds the clock gives, and each list holding the rows of its own query.
 */
final class StatsTest extends TestCase {

	/** 2026-09-23 12:00:00 UTC, 15:00 in Kyiv. */
	private const NOW = 1790164800;

	private WpdbDouble $wpdb;

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'Europe/Kyiv' ) );

		$this->wpdb      = new WpdbDouble( 'wp_' );
		$GLOBALS['wpdb'] = $this->wpdb;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_six_queries_in_order_with_the_clocks_bounds(): void {
		$this->wpdb->results = array_map(
			fn( string $text ): array => [ $this->row( $text ) ],
			[ 'one', 'two', 'three', 'four', 'five', 'six' ]
		);

		$stats = Stats::build( self::NOW );

		$yesterday    = [ '2026-09-21 21:00:00', '2026-09-22 21:00:00', 5 ];
		$last_28_days = [ '2026-08-25 21:00:00', '2026-09-22 21:00:00', 5 ];

		$this->assertSame(
			[
				[ 'site', ...$yesterday ],
				[ 'site', ...$last_28_days ],
				[ 'services', ...$yesterday ],
				[ 'services', ...$last_28_days ],
				[ 'service', ...$yesterday ],
				[ 'service', ...$last_28_days ],
			],
			array_map( static fn( array $call ): array => $call[1], $this->wpdb->prepared )
		);

		$texts = static fn( array $list ): array => array_map( static fn( TopQuery $top ): string => $top->query, $list );

		$this->assertSame( [ 'one' ], $texts( $stats->site_yesterday ) );
		$this->assertSame( [ 'two' ], $texts( $stats->site_28_days ) );
		$this->assertSame( [ 'three' ], $texts( $stats->services_yesterday ) );
		$this->assertSame( [ 'four' ], $texts( $stats->services_28_days ) );
		$this->assertSame( [ 'five' ], $texts( $stats->service_yesterday ) );
		$this->assertSame( [ 'six' ], $texts( $stats->service_28_days ) );
	}

	public function test_each_list_holds_the_rows_of_its_query(): void {
		$this->wpdb->results = [
			[ $this->row( 'вакцинація', '0', '7', '3' ), $this->row( 'груминг', '0', '2', '0' ) ],
			[],
			[ $this->row( 'узі', '12', '4', null ) ],
		];

		$stats = Stats::build( self::NOW );

		$this->assertEquals(
			[ new TopQuery( 'вакцинація', 0, 7, false ), new TopQuery( 'груминг', 0, 2, true ) ],
			$stats->site_yesterday
		);
		$this->assertSame( [], $stats->site_28_days );
		$this->assertEquals( [ new TopQuery( 'узі', 12, 4, false ) ], $stats->services_yesterday );
	}

	public function test_an_empty_table_gives_six_empty_lists(): void {
		$stats = Stats::build( self::NOW );

		$this->assertEquals( new SearchStats( [], [], [], [], [], [] ), $stats );
		$this->assertCount( 6, $this->wpdb->selected, 'six queries even when there is nothing to find' );
	}

	private function row( string $query_text, string $context_id = '0', string $n = '1', ?string $max_results = null ): \stdClass {
		return (object) [
			'query_text'  => $query_text,
			'context_id'  => $context_id,
			'n'           => $n,
			'max_results' => $max_results,
			'last_at'     => '2026-09-22 10:00:00',
		];
	}
}
