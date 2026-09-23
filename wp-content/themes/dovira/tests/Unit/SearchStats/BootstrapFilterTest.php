<?php
/**
 * Tests for the gatb_extra_blocks callback the feature's bootstrap registers.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\SearchStats;

use Brain\Monkey\Functions;
use DateTimeZone;
use dovira\SearchStats\Renderer;
use dovira\Tests\TestCase;
use dovira\Tests\WpdbDouble;

require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/Schema.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/Periods.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/TopQuery.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/Repository.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/RecordController.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/SearchStats.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/Stats.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/Renderer.php';

/**
 * Renderer::add_to(), the callback bootstrap.php hooks to gatb_extra_blocks.
 * bootstrap.php itself is never loaded in a test (docs/TESTING.md → Rules);
 * its one registration line is proven by the step's manual verification.
 */
final class BootstrapFilterTest extends TestCase {

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

	public function test_the_plugins_list_comes_back_with_the_blocks_appended(): void {
		$this->wpdb->results = [
			[
				(object) [
					'query_text'  => 'вакцинація',
					'context_id'  => '0',
					'n'           => '2',
					'max_results' => '4',
					'last_at'     => '2026-09-22 10:00:00',
				],
			],
		];

		$this->assertSame(
			[ '<b>plugin</b>', "🔎 <b>Пошук по сайту</b>\nВчора:\n1. вакцинація — 2\nЗа 28 днів:\n—" ],
			Renderer::add_to( [ '<b>plugin</b>' ] )
		);
		$this->assertCount( 6, $this->wpdb->selected, 'the six lists were read' );
	}

	public function test_the_list_is_untouched_when_there_is_nothing_to_add(): void {
		$this->assertSame( [ '<b>plugin</b>' ], Renderer::add_to( [ '<b>plugin</b>' ] ), 'an empty table adds nothing' );
		$this->assertSame( 'not a list', Renderer::add_to( 'not a list' ), 'what is not a list is handed back as it came' );
		$this->assertCount( 6, $this->wpdb->selected, 'only the list was read for; nothing was read for the non-list' );
	}
}
