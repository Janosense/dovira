<?php
/**
 * Tests for the message renderer: what the owner actually receives.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use GaTelegramBridge\MessageRenderer;
use GaTelegramBridge\Report;
use GaTelegramBridge\ReportBuilder;
use GaTelegramBridge\Settings;
use GaTelegramBridge\Tests\SitePosts;
use GaTelegramBridge\Tests\TestCase;

/**
 * The message is asserted whole, because the message is the deliverable.
 *
 * What goes into it comes from the recorded responses of the live property
 * through the real builder (docs/TESTING.md → Never mocked); only the two
 * WordPress formatting functions are stubbed, and they are stubbed with what
 * WordPress does in English, so the expectations below are the English source
 * strings. The Ukrainian rendering is proven by the manual verification guide.
 */
final class MessageRendererTest extends TestCase {

	/**
	 * A moment in the middle of a day, so "yesterday" is unambiguous.
	 */
	private const NOON = 1757505600;

	/**
	 * Stubs the WordPress helpers the renderer and the builder go through.
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'get_transient' )->justReturn( 'a-cached-access-token' );
		Functions\when( 'wp_timezone_string' )->justReturn( 'Europe/Kyiv' );
		Functions\when( 'home_url' )->justReturn( 'https://dovira.vet' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		SitePosts::given( 'https://dovira.vet' );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static fn( array $response ): int => (int) $response['response']['code']
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static fn( array $response ): string => (string) $response['body']
		);
		$this->given_english_formatting();
		$this->given_blocks( array_fill_keys( Settings::BLOCKS, true ) );
	}

	/**
	 * Formats dates and numbers the way WordPress does with no translation.
	 */
	private function given_english_formatting(): void {
		Functions\when( 'number_format_i18n' )->alias(
			static fn( $number ): string => number_format( (float) $number )
		);
		Functions\when( 'wp_date' )->alias(
			static function ( string $format, ?int $timestamp = null, $timezone = null ): string {
				$zone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone( 'UTC' );

				return ( new DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( $zone )->format( $format );
			}
		);
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
	 * Answers the two calls of a full report with the two recorded responses.
	 *
	 * They were recorded against the live property in exactly this composition,
	 * so with every block on they line up call for call.
	 */
	private function given_the_recorded_property(): void {
		$queue = array(
			$this->fixture( 'batch-run-reports-daily-call-1.json' ),
			$this->fixture( 'batch-run-reports-daily-call-2.json' ),
		);

		Functions\when( 'wp_remote_post' )->alias(
			static function () use ( &$queue ): array {
				return (array) array_shift( $queue );
			}
		);
	}

	/**
	 * Builds the report of the recorded day.
	 */
	private function recorded_report(): Report {
		$this->given_the_recorded_property();

		return ReportBuilder::build( self::NOON );
	}

	/**
	 * Builds the report of a property that has no traffic at all.
	 *
	 * Devices are switched off so that the five requests of the written
	 * fixture and the five reports it answers with match.
	 */
	private function silent_report(): Report {
		$this->given_blocks(
			array(
				'visitors' => true,
				'pages'    => true,
				'channels' => true,
				'cities'   => true,
			)
		);
		Functions\when( 'wp_remote_post' )->alias(
			fn(): array => $this->fixture( 'batch-run-reports-no-data.written.json' )
		);

		return ReportBuilder::build( self::NOON );
	}

	/**
	 * The whole message of a day the property really had.
	 */
	public function test_a_full_report_is_written_out_block_by_block(): void {
		$expected = implode(
			"\n",
			array(
				'📊 <b>dovira.vet — 9 September (Tuesday)</b>',
				'<b>Visitors</b>',
				'Yesterday: 69 (▲ 23% to the 7-day average)',
				'Last 28 days: 1,560 (▼ 1% to the previous 28)',
				'<b>Top 5 pages yesterday</b>',
				'1. Головна сторінка — 101',
				'2. Послуги — 60',
				'3. Контакти — 29',
				'4. Приймальне відділення — 22',
				'5. Стоматологічні послуги — 16',
				'<b>Top 5 pages over 28 days</b>',
				'1. Головна сторінка — 2,237',
				'2. Послуги — 1,000',
				'3. Контакти — 471',
				'4. Приймальне відділення — 396',
				'5. Про нас — 200',
				'<b>Sources over 28 days</b>',
				'Organic Search 70% · Direct 16% · Referral 7% · Organic Social 7% · AI Assistant 1% · Paid Search 0% · Unassigned 0%',
				'<b>Cities over 28 days</b>',
				'Kharkiv 52% · Kyiv 30% · Dnipro 11% · Lviv 7%',
				'<b>Devices over 28 days</b>',
				'mobile 80% · desktop 19% · tablet 1%',
			)
		);

		$this->assertSame( $expected, MessageRenderer::render( $this->recorded_report() ) );
	}

	/**
	 * A switched-off block leaves no trace in the message.
	 */
	public function test_a_switched_off_block_is_left_out_entirely(): void {
		$message = MessageRenderer::render( $this->without_pages_and_devices( $this->recorded_report() ) );

		$this->assertStringNotContainsString( 'pages', $message );
		$this->assertStringNotContainsString( 'Devices', $message );
		$this->assertStringNotContainsString( 'mobile', $message );
		$this->assertStringContainsString( '<b>Sources over 28 days</b>', $message );
		$this->assertStringContainsString( '<b>Cities over 28 days</b>', $message );
		$this->assertCount(
			8,
			explode( "\n", $message ),
			'the header, the visitors block of three lines, and two blocks of two'
		);
	}

	/**
	 * A block that is on but empty is left out too, rather than shown bare.
	 */
	public function test_a_block_with_no_rows_is_not_given_a_heading(): void {
		$report = $this->silent_report();

		$this->assertSame( array(), $report->pages_yesterday, 'switched on' );

		$message = MessageRenderer::render( $report );

		$this->assertStringNotContainsString( 'Top 5 pages', $message );
		$this->assertStringNotContainsString( 'Sources', $message );
		$this->assertStringNotContainsString( 'Cities', $message );
		$this->assertStringContainsString( '<b>Visitors</b>', $message );
	}

	/**
	 * With nothing to compare against, the message says so instead of "0%".
	 */
	public function test_a_comparison_without_a_baseline_prints_a_dash(): void {
		$message = MessageRenderer::render( $this->silent_report() );

		$this->assertStringContainsString( 'Yesterday: 0 (— to the 7-day average)', $message );
		$this->assertStringContainsString( 'Last 28 days: 0 (— to the previous 28)', $message );
		$this->assertStringNotContainsString( '▲', $message );
		$this->assertStringNotContainsString( '▼', $message );
	}

	/**
	 * A rise and a fall are marked by the arrow, never by a minus sign.
	 */
	public function test_a_rise_and_a_fall_are_marked_by_their_arrow(): void {
		$message = MessageRenderer::render(
			$this->report_with(
				array(
					'visitors_change_vs_average' => 23,
					'visitors_change_28_days'    => -1,
				)
			)
		);

		$this->assertStringContainsString( '(▲ 23% to the 7-day average)', $message );
		$this->assertStringContainsString( '(▼ 1% to the previous 28)', $message );
		$this->assertStringNotContainsString( '-1', $message );
	}

	/**
	 * A page title Google supplies can carry markup; Telegram must not see it.
	 *
	 * The live property has no such page, so the row is given to the renderer
	 * directly. Without the escaping Telegram answers 400 "can't parse
	 * entities" and the whole report is lost.
	 */
	public function test_everything_google_supplies_is_escaped(): void {
		$message = MessageRenderer::render(
			$this->report_with(
				array(
					'pages_yesterday' => array(
						array(
							'title' => 'Ціни <b>дешево</b> & "акції"',
							'path'  => '/prices/',
							'views' => 7,
						),
					),
					'cities'          => array(
						array(
							'label' => 'Ivano-Frankivsk & Co',
							'value' => 3,
							'share' => 100,
						),
					),
				)
			)
		);

		$this->assertStringContainsString( '1. Ціни &lt;b&gt;дешево&lt;/b&gt; &amp; &quot;акції&quot; — 7', $message );
		$this->assertStringContainsString( 'Ivano-Frankivsk &amp; Co 100%', $message );
		$this->assertSame(
			4,
			substr_count( $message, '<b>' ),
			'the only markup left is the message\'s own bold: the header and three headings'
		);
	}

	/**
	 * The Ukrainian thousands separator reaches Telegram as a character.
	 *
	 * WordPress hands out "1&nbsp;560" in Ukrainian, and Telegram knows only
	 * four named entities — &lt;, &gt;, &amp; and &quot;. An undecoded &nbsp;
	 * is therefore either printed literally or refused.
	 */
	public function test_a_localized_number_carries_no_named_entity(): void {
		Functions\when( 'number_format_i18n' )->alias(
			static fn( $number ): string => number_format( (float) $number, 0, ',', '&nbsp;' )
		);

		$message = MessageRenderer::render( $this->recorded_report() );

		$this->assertStringNotContainsString( '&nbsp;', $message );
		$this->assertStringContainsString( "Last 28 days: 1\u{00A0}560", $message );
	}

	/**
	 * The failure notice names the site and the day it has nothing to say about.
	 */
	public function test_the_failure_notice_names_the_site_and_the_day(): void {
		$this->assertSame(
			'⚠️ <b>dovira.vet</b> — the report for 9 September (Tuesday) was not built. The details are in the plugin\'s run log.',
			MessageRenderer::render_failure( '2025-09-09' )
		);
	}

	/**
	 * The report can be changed by a filter before it is written out.
	 */
	public function test_a_filter_may_change_the_report_before_rendering(): void {
		$report = $this->recorded_report();

		Filters\expectApplied( 'gatb_report_data' )
			->once()
			->with( $report )
			->andReturn( $this->report_with( array( 'visitors_yesterday' => 4242 ) ) );

		$this->assertStringContainsString( 'Yesterday: 4,242', MessageRenderer::render( $report ) );
	}

	/**
	 * A filter that returns something other than a report is ignored.
	 */
	public function test_a_filter_that_returns_a_stranger_is_ignored(): void {
		Filters\expectApplied( 'gatb_report_data' )->once()->andReturn( 'not a report at all' );

		$this->assertStringContainsString( 'Yesterday: 69', MessageRenderer::render( $this->recorded_report() ) );
	}

	/**
	 * The finished message can be changed by a filter before it is sent.
	 */
	public function test_a_filter_may_change_the_finished_message(): void {
		Filters\expectApplied( 'gatb_message_html' )->once()->andReturn( '<b>something else</b>' );

		$this->assertSame( '<b>something else</b>', MessageRenderer::render( $this->recorded_report() ) );
	}

	/**
	 * The failure notice passes through the same filter as the report does.
	 */
	public function test_the_failure_notice_is_filtered_too(): void {
		Filters\expectApplied( 'gatb_message_html' )->once()->andReturn( 'replaced' );

		$this->assertSame( 'replaced', MessageRenderer::render_failure( '2025-09-09' ) );
	}

	/**
	 * Returns the recorded report with the two blocks switched off.
	 *
	 * @param Report $report The report to take the numbers from.
	 */
	private function without_pages_and_devices( Report $report ): Report {
		return new Report(
			$report->date,
			$report->time_zone,
			$report->visitors_yesterday,
			$report->visitors_average_7_days,
			$report->visitors_28_days,
			$report->visitors_previous_28_days,
			$report->visitors_change_vs_average,
			$report->visitors_change_28_days,
			null,
			null,
			$report->channels,
			$report->cities,
			null
		);
	}

	/**
	 * Builds a report of the given parts, with every other block switched off.
	 *
	 * @param array<string, mixed> $parts What this report is to hold.
	 */
	private function report_with( array $parts ): Report {
		$values = array_merge(
			array(
				'date'                       => '2025-09-09',
				'time_zone'                  => 'Europe/Kiev',
				'visitors_yesterday'         => 66,
				'visitors_average_7_days'    => 79.5,
				'visitors_28_days'           => 1559,
				'visitors_previous_28_days'  => 1577,
				'visitors_change_vs_average' => -17,
				'visitors_change_28_days'    => -1,
				'pages_yesterday'            => null,
				'pages_28_days'              => null,
				'channels'                   => null,
				'cities'                     => null,
				'devices'                    => null,
			),
			$parts
		);

		return new Report(
			(string) $values['date'],
			(string) $values['time_zone'],
			(int) $values['visitors_yesterday'],
			(float) $values['visitors_average_7_days'],
			(int) $values['visitors_28_days'],
			(int) $values['visitors_previous_28_days'],
			null === $values['visitors_change_vs_average'] ? null : (int) $values['visitors_change_vs_average'],
			null === $values['visitors_change_28_days'] ? null : (int) $values['visitors_change_28_days'],
			$values['pages_yesterday'],
			$values['pages_28_days'],
			$values['channels'],
			$values['cities'],
			$values['devices']
		);
	}
}
