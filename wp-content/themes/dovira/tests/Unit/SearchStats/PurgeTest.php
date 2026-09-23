<?php
/**
 * Tests for dovira\SearchStats\Purge.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\SearchStats;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use dovira\SearchStats\Purge;
use dovira\Tests\TestCase;
use dovira\Tests\WpdbDouble;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/Schema.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/Purge.php';

/**
 * The DELETE built for a given clock and retention, the retention floor, and the schedule.
 */
final class PurgeTest extends TestCase {

	/** 2026-09-23 12:00:00 UTC. */
	private const NOW = 1790164800;

	private const DELETE = 'DELETE FROM wp_dovira_search_queries WHERE created_at < %s';

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

	public function test_the_default_retention_deletes_rows_older_than_90_days(): void {
		Purge::run( self::NOW );

		$this->assertSame( [ [ self::DELETE, [ '2026-06-25 12:00:00' ] ] ], $this->wpdb->prepared );
		$this->assertSame(
			[ "DELETE FROM wp_dovira_search_queries WHERE created_at < '2026-06-25 12:00:00'" ],
			$this->wpdb->queries
		);
	}

	public function test_the_filter_is_applied_once_with_the_default(): void {
		$passed = [];
		Filters\expectApplied( 'dovira_search_stats_retention_days' )->andReturnUsing(
			function ( $days ) use ( &$passed ) {
				$passed[] = $days;

				return $days;
			}
		);

		Purge::run( self::NOW );

		$this->assertSame( [ 90 ], $passed );
		$this->assertSame( 1, Filters\applied( 'dovira_search_stats_retention_days' ) );
	}

	/**
	 * @return array<string, array{int, string}>
	 */
	public static function filtered_retentions(): array {
		return [
			'below the floor is clamped to 29' => [ 5, '2026-08-25 12:00:00' ],
			'the floor itself'                 => [ 29, '2026-08-25 12:00:00' ],
			'longer than the default'          => [ 120, '2026-05-26 12:00:00' ],
		];
	}

	#[DataProvider( 'filtered_retentions' )]
	public function test_a_filtered_retention_moves_the_cutoff( int $days, string $cutoff ): void {
		Filters\expectApplied( 'dovira_search_stats_retention_days' )->andReturn( $days );

		Purge::run( self::NOW );

		$this->assertSame( [ [ self::DELETE, [ $cutoff ] ] ], $this->wpdb->prepared );
	}

	public function test_schedule_registers_the_daily_event_when_absent(): void {
		$scheduled = [];
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\when( 'wp_schedule_event' )->alias(
			function ( ...$args ) use ( &$scheduled ) {
				$scheduled[] = $args;

				return true;
			}
		);

		Purge::schedule();

		$this->assertCount( 1, $scheduled );
		$this->assertIsInt( $scheduled[0][0] );
		$this->assertSame( [ 'daily', 'dovira_search_stats_purge' ], array_slice( $scheduled[0], 1 ) );
	}

	public function test_schedule_does_nothing_when_the_event_exists(): void {
		$scheduled = [];
		Functions\when( 'wp_next_scheduled' )->justReturn( self::NOW + 3600 );
		Functions\when( 'wp_schedule_event' )->alias(
			function ( ...$args ) use ( &$scheduled ) {
				$scheduled[] = $args;

				return true;
			}
		);

		Purge::schedule();

		$this->assertSame( [], $scheduled );
	}
}
