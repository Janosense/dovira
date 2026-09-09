<?php
/**
 * Tests for the report builder: which requests it composes, and how it reads
 * the answers back.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge\Tests\Unit;

use Brain\Monkey\Functions;
use GaTelegramBridge\Report;
use GaTelegramBridge\ReportBuilder;
use GaTelegramBridge\Settings;
use GaTelegramBridge\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Composition is asserted request by request; parsing runs on the responses the
 * real property gave (docs/TESTING.md → Never mocked).
 */
final class ReportBuilderTest extends TestCase {

	/**
	 * A moment in the middle of a day, so "yesterday" is unambiguous.
	 */
	private const NOON = 1757505600;

	/**
	 * Stubs the WordPress helpers the builder goes through.
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'get_transient' )->justReturn( 'a-cached-access-token' );
		Functions\when( 'wp_timezone_string' )->justReturn( 'Europe/Kyiv' );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static fn( array $response ): int => (int) $response['response']['code']
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static fn( array $response ): string => (string) $response['body']
		);
		$this->given_blocks( array_fill_keys( Settings::BLOCKS, true ) );
	}

	/**
	 * Configures which report blocks are switched on.
	 *
	 * @param array<string, bool> $blocks The block switches.
	 */
	private function given_blocks( array $blocks ): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'property_id' => '533779496',
				'blocks'      => $blocks,
			)
		);
	}

	/**
	 * Reads a fixture and shapes it the way wp_remote_post returns.
	 *
	 * @param string $name The fixture file name.
	 * @return array{response: array{code: int}, body: string}
	 */
	private function fixture( string $name ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a test fixture on disk, no WP_Filesystem in unit tests.
		$raw     = (string) file_get_contents( __DIR__ . '/../fixtures/ga/' . $name );
		$fixture = (array) json_decode( $raw, true );

		return array(
			'response' => array( 'code' => (int) $fixture['status'] ),
			'body'     => (string) wp_json_encode( $fixture['body'] ),
		);
	}

	/**
	 * Answers like the real property: each call gets exactly the reports it
	 * asked for, in the order it asked for them.
	 *
	 * The two recordings hold one report per block; the stub picks them apart
	 * by what each request contains, so switching a block off changes the
	 * answer the way GA would rather than shifting the fixture's rows.
	 *
	 * @return array<int, list<array<string, mixed>>> Filled with the requests of each call.
	 */
	private function &given_the_recorded_property(): array {
		$calls = array();

		Functions\when( 'wp_remote_post' )->alias(
			function ( string $url, array $arguments ) use ( &$calls ): array {
				$body    = (array) json_decode( $arguments['body'], true );
				$calls[] = $body['requests'];

				$reports = array();

				foreach ( $body['requests'] as $request ) {
					$reports[] = $this->recorded_report( $this->identify( $request ) );
				}

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => (string) wp_json_encode( array( 'reports' => $reports ) ),
				);
			}
		);

		return $calls;
	}

	/**
	 * Names the block a request belongs to, the way the response order does.
	 *
	 * @param array<string, mixed> $request One report request.
	 */
	private function identify( array $request ): string {
		$dimensions = isset( $request['dimensions'] ) ? array_column( $request['dimensions'], 'name' ) : array();
		$start      = (string) $request['dateRanges'][0]['startDate'];

		if ( array() === $dimensions ) {
			return 'visitors';
		}

		if ( in_array( 'pagePath', $dimensions, true ) ) {
			return 'yesterday' === $start ? 'pages_yesterday' : 'pages_28_days';
		}

		if ( in_array( 'sessionDefaultChannelGroup', $dimensions, true ) ) {
			return 'channels';
		}

		return in_array( 'city', $dimensions, true ) ? 'cities' : 'devices';
	}

	/**
	 * Returns one recorded report by block name.
	 *
	 * @param string $key The block name.
	 * @return array<string, mixed>
	 */
	private function recorded_report( string $key ): array {
		$order = array( 'visitors', 'pages_yesterday', 'pages_28_days', 'channels', 'cities' );
		$index = array_search( $key, $order, true );

		if ( false === $index ) {
			$body = (array) json_decode( $this->fixture( 'batch-run-reports-daily-call-2.json' )['body'], true );

			return (array) $body['reports'][0];
		}

		$body = (array) json_decode( $this->fixture( 'batch-run-reports-daily-call-1.json' )['body'], true );

		return (array) $body['reports'][ $index ];
	}

	/**
	 * A full report is six requests, which cannot be one call.
	 *
	 * The Data API takes at most five requests per batchRunReports, so this is
	 * the invariant in FEATURE.md rather than a preference.
	 */
	public function test_a_full_report_is_split_into_two_calls_of_five_and_one(): void {
		$calls = &$this->given_the_recorded_property();

		ReportBuilder::build( self::NOON );

		$this->assertCount( 2, $calls );
		$this->assertCount( 5, $calls[0] );
		$this->assertCount( 1, $calls[1] );
	}

	/**
	 * The visitors request asks for the four periods the report compares.
	 */
	public function test_the_visitors_request_asks_for_the_four_periods(): void {
		$requests = ReportBuilder::requests( array_fill_keys( Settings::BLOCKS, true ) );

		$this->assertSame( 'activeUsers', $requests['visitors']['metrics'][0]['name'] );
		$this->assertSame(
			array(
				array(
					'startDate' => 'yesterday',
					'endDate'   => 'yesterday',
				),
				array(
					'startDate' => '8daysAgo',
					'endDate'   => '2daysAgo',
				),
				array(
					'startDate' => '28daysAgo',
					'endDate'   => 'yesterday',
				),
				array(
					'startDate' => '56daysAgo',
					'endDate'   => '29daysAgo',
				),
			),
			$requests['visitors']['dateRanges']
		);
		$this->assertArrayNotHasKey( 'dimensions', $requests['visitors'], 'GA adds the dateRange dimension itself' );
	}

	/**
	 * Each block asks for the dimensions, metric and limit the report needs.
	 *
	 * @param string        $key        The request key.
	 * @param array<string> $dimensions The dimensions it groups by.
	 * @param string        $metric     The metric it measures.
	 * @param int|null      $limit      The row limit, or null for all rows.
	 * @param string        $start      The first day of its range.
	 */
	#[DataProvider( 'provide_block_requests' )]
	public function test_a_block_asks_for_what_the_message_shows( string $key, array $dimensions, string $metric, ?int $limit, string $start ): void {
		$requests = ReportBuilder::requests( array_fill_keys( Settings::BLOCKS, true ) );
		$request  = $requests[ $key ];

		$this->assertSame(
			$dimensions,
			array_column( $request['dimensions'], 'name' )
		);
		$this->assertSame( $metric, $request['metrics'][0]['name'] );
		$this->assertSame( $start, $request['dateRanges'][0]['startDate'] );
		$this->assertSame( 'yesterday', $request['dateRanges'][0]['endDate'] );
		$this->assertSame( $metric, $request['orderBys'][0]['metric']['metricName'] );
		$this->assertTrue( $request['orderBys'][0]['desc'], 'a ranked block is ordered largest first' );

		if ( null === $limit ) {
			$this->assertArrayNotHasKey( 'limit', $request );

			return;
		}

		$this->assertSame( $limit, $request['limit'] );
	}

	/**
	 * The five ranked requests, as DECISIONS "Report content" fixes them.
	 *
	 * @return array<string, array{string, list<string>, string, int|null, string}>
	 */
	public static function provide_block_requests(): array {
		return array(
			'top pages yesterday' => array( 'pages_yesterday', array( 'pagePath', 'pageTitle' ), 'screenPageViews', 5, 'yesterday' ),
			'top pages 28 days'   => array( 'pages_28_days', array( 'pagePath', 'pageTitle' ), 'screenPageViews', 5, '28daysAgo' ),
			'traffic sources'     => array( 'channels', array( 'sessionDefaultChannelGroup' ), 'sessions', null, '28daysAgo' ),
			'cities'              => array( 'cities', array( 'city' ), 'activeUsers', 5, '28daysAgo' ),
			'devices'             => array( 'devices', array( 'deviceCategory' ), 'activeUsers', null, '28daysAgo' ),
		);
	}

	/**
	 * With every optional block off, one request goes out and nothing else.
	 */
	public function test_visitors_alone_is_one_request_naming_no_other_block(): void {
		$this->given_blocks( array( 'visitors' => true ) );
		$calls = &$this->given_the_recorded_property();

		$report = ReportBuilder::build( self::NOON );

		$this->assertCount( 1, $calls );
		$this->assertCount( 1, $calls[0] );

		$asked = (string) wp_json_encode( $calls );

		foreach ( array( 'pagePath', 'sessionDefaultChannelGroup', 'city', 'deviceCategory' ) as $dimension ) {
			$this->assertStringNotContainsString( $dimension, $asked, 'a disabled block issues no request' );
		}

		$this->assertNull( $report->pages_yesterday );
		$this->assertNull( $report->channels );
		$this->assertNull( $report->cities );
		$this->assertNull( $report->devices );
	}

	/**
	 * Whatever is switched on, the run never makes a third call, and the report
	 * carries exactly the blocks that were asked for.
	 *
	 * All sixteen combinations, because this is where an off-by-one in the
	 * request-to-response zipping would put one block's rows under another's
	 * name.
	 */
	public function test_every_block_combination_holds_the_two_call_invariant(): void {
		foreach ( range( 0, 15 ) as $combination ) {
			$blocks = array(
				'visitors' => true,
				'pages'    => (bool) ( $combination & 1 ),
				'channels' => (bool) ( $combination & 2 ),
				'cities'   => (bool) ( $combination & 4 ),
				'devices'  => (bool) ( $combination & 8 ),
			);

			$this->given_blocks( $blocks );
			$calls = &$this->given_the_recorded_property();

			$report = ReportBuilder::build( self::NOON );

			$this->assertLessThanOrEqual( 2, count( $calls ), 'never more than two batchRunReports calls' );

			foreach ( array( 'pages', 'channels', 'cities', 'devices' ) as $block ) {
				$this->assertSame(
					$blocks[ $block ],
					$report->has_block( $block ),
					sprintf( 'block %s in combination %d', $block, $combination )
				);
			}
		}
	}

	/**
	 * The visitors block is read by date range, not by row order.
	 *
	 * The recorded response lists date_range_3 first and date_range_0 last,
	 * because GA orders rows by the metric. Reading them positionally would
	 * report the previous 28 days as yesterday.
	 */
	public function test_the_periods_are_keyed_by_date_range_and_not_by_row_order(): void {
		$this->given_the_recorded_property();

		$report = ReportBuilder::build( self::NOON );

		$this->assertSame( 69, $report->visitors_yesterday, 'date_range_0, which the response lists last' );
		$this->assertSame( 394 / 7, $report->visitors_average_7_days );
		$this->assertSame( 1560, $report->visitors_28_days );
		$this->assertSame( 1577, $report->visitors_previous_28_days );
		$this->assertSame( 23, $report->visitors_change_vs_average );
		$this->assertSame( -1, $report->visitors_change_28_days );
	}

	/**
	 * The day the report covers comes from the property's own time zone.
	 */
	public function test_the_report_covers_yesterday_in_the_properties_time_zone(): void {
		$this->given_the_recorded_property();

		$report = ReportBuilder::build( self::NOON );

		$this->assertSame( 'Europe/Kiev', $report->time_zone );
		$this->assertSame( '2025-09-09', $report->date );
	}

	/**
	 * The same instant is a different "yesterday" on the two sides of midnight.
	 *
	 * 2025-09-10 20:30 UTC is still the 10th in Kyiv (23:30), so yesterday is
	 * the 9th; half an hour later it is already the 11th there, and yesterday
	 * becomes the 10th. This is the whole reason the time zone is read from the
	 * property instead of taken from the server.
	 */
	public function test_the_report_date_turns_over_at_the_properties_midnight(): void {
		$before = 1757536200;
		$after  = $before + 3600;

		$this->assertSame( '2025-09-09', ReportBuilder::report_date( 'Europe/Kiev', $before ) );
		$this->assertSame( '2025-09-10', ReportBuilder::report_date( 'Europe/Kiev', $after ) );
		$this->assertSame( '2025-09-09', ReportBuilder::report_date( 'UTC', $after ), 'the server would still say the 9th' );
	}

	/**
	 * An unusable time zone falls back rather than throwing at 9 in the morning.
	 */
	public function test_an_unknown_time_zone_falls_back_to_utc(): void {
		$this->assertSame( '2025-09-09', ReportBuilder::report_date( 'Mars/Olympus_Mons', 1757505600 ) );
	}

	/**
	 * The pages block keeps the title, the path and the views of each row.
	 */
	public function test_the_pages_block_is_read_from_the_recorded_rows(): void {
		$this->given_the_recorded_property();

		$report = ReportBuilder::build( self::NOON );
		$pages  = $report->pages_yesterday;

		$this->assertIsArray( $pages );
		$this->assertCount( 5, $pages );
		$this->assertSame( '/', $pages[0]['path'] );
		$this->assertSame( 73, $pages[0]['views'] );
		$this->assertStringContainsString( 'Ветеринарна клініка', $pages[0]['title'] );
		$this->assertGreaterThan( $pages[4]['views'], $pages[0]['views'], 'rows arrive ordered by views' );
	}

	/**
	 * A page GA cannot name is labelled with its path instead.
	 *
	 * The recording holds no such row — every page of the live site has a title
	 * — so the two shapes are given directly to the parser.
	 */
	public function test_a_page_without_a_title_is_labelled_with_its_path(): void {
		$this->given_blocks(
			array(
				'visitors' => true,
				'pages'    => true,
			)
		);

		Functions\when( 'wp_remote_post' )->justReturn(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => (string) wp_json_encode(
					array(
						'reports' => array(
							array( 'rows' => array() ),
							array(
								'rows' => array(
									$this->page_row( '/no-title/', '', 9 ),
									$this->page_row( '/not-set/', '(not set)', 8 ),
									$this->page_row( '/named/', 'A page', 7 ),
								),
							),
							array( 'rows' => array() ),
						),
					)
				),
			)
		);

		$pages = ReportBuilder::build( self::NOON )->pages_yesterday;

		$this->assertIsArray( $pages );
		$this->assertSame( '/no-title/', $pages[0]['title'] );
		$this->assertSame( '/not-set/', $pages[1]['title'] );
		$this->assertSame( 'A page', $pages[2]['title'] );
	}

	/**
	 * Builds one row of a pages report.
	 *
	 * @param string $path  The page path.
	 * @param string $title The page title GA reported.
	 * @param int    $views The view count.
	 * @return array<string, mixed>
	 */
	private function page_row( string $path, string $title, int $views ): array {
		return array(
			'dimensionValues' => array( array( 'value' => $path ), array( 'value' => $title ) ),
			'metricValues'    => array( array( 'value' => (string) $views ) ),
		);
	}

	/**
	 * A city GA could not determine is dropped before the shares are worked out.
	 *
	 * The recorded response really contains such a row (114 users), so the
	 * shares below are shares of what the message actually lists.
	 */
	public function test_an_unknown_city_is_dropped_and_the_shares_exclude_it(): void {
		$this->given_the_recorded_property();

		$cities = ReportBuilder::build( self::NOON )->cities;

		$this->assertIsArray( $cities );
		$this->assertSame(
			array( 'Kharkiv', 'Kyiv', 'Dnipro', 'Lviv' ),
			array_column( $cities, 'label' ),
			'the recorded (not set) row is gone'
		);
		$this->assertSame( 623, $cities[0]['value'] );
		$this->assertSame( 52, $cities[0]['share'], '623 of the 1197 that are named, not of the 1311 GA counted' );
	}

	/**
	 * The other ranked blocks keep every row and share the block's total.
	 */
	public function test_channels_and_devices_keep_every_row(): void {
		$this->given_the_recorded_property();

		$report = ReportBuilder::build( self::NOON );

		$this->assertIsArray( $report->channels );
		$this->assertCount( 7, $report->channels );
		$this->assertSame( 'Organic Search', $report->channels[0]['label'] );
		$this->assertSame( 70, $report->channels[0]['share'] );

		$this->assertIsArray( $report->devices );
		$this->assertSame(
			array( 'mobile', 'desktop', 'tablet' ),
			array_column( $report->devices, 'label' )
		);
		$this->assertSame( 80, $report->devices[0]['share'] );
	}

	/**
	 * A property with no traffic yields an empty report, not a broken one.
	 *
	 * Every enabled block is an empty array rather than null — switched on,
	 * nothing to say — and no comparison divides by zero.
	 */
	public function test_a_property_with_no_traffic_reports_nothing_without_failing(): void {
		$this->given_blocks(
			array(
				'visitors' => true,
				'pages'    => true,
				'channels' => true,
				'cities'   => true,
			)
		);
		$calls = array();
		Functions\when( 'wp_remote_post' )->alias(
			function ( string $url, array $arguments ) use ( &$calls ): array {
				$body    = (array) json_decode( $arguments['body'], true );
				$calls[] = $body['requests'];

				return $this->fixture( 'batch-run-reports-no-data.written.json' );
			}
		);

		$report = ReportBuilder::build( self::NOON );

		$this->assertCount( 1, $calls, 'five requests still fit into one call' );
		$this->assertSame( 0, $report->visitors_yesterday );
		$this->assertSame( 0.0, $report->visitors_average_7_days );
		$this->assertNull( $report->visitors_change_vs_average );
		$this->assertNull( $report->visitors_change_28_days );
		$this->assertSame( array(), $report->pages_yesterday );
		$this->assertSame( array(), $report->channels );
		$this->assertSame( array(), $report->cities );
		$this->assertNull( $report->devices, 'switched off is still different from empty' );
		$this->assertSame( '2025-09-09', $report->date );
	}

	/**
	 * A short answer is a failure, not a report with holes in it.
	 */
	public function test_fewer_reports_than_requests_is_refused(): void {
		$this->given_blocks( array_fill_keys( Settings::BLOCKS, true ) );
		Functions\when( 'wp_remote_post' )->justReturn(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"reports":[{"rows":[]}]}',
			)
		);

		$this->expectExceptionMessageMatches( '/fewer reports than were asked for/' );

		ReportBuilder::build( self::NOON );
	}

	/**
	 * The builder returns the value object the renderer is written against.
	 */
	public function test_the_result_is_a_report(): void {
		$this->given_the_recorded_property();

		$this->assertInstanceOf( Report::class, ReportBuilder::build( self::NOON ) );
	}
}
