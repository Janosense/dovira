<?php
/**
 * Tests for the schedule: when the next run falls, and what the run does.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge\Tests\Unit;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use GaTelegramBridge\RunLog;
use GaTelegramBridge\Scheduler;
use GaTelegramBridge\Settings;
use GaTelegramBridge\Tests\TestCase;

/**
 * The maths is asserted against a fixed clock in a real time zone with real
 * transitions — a test that waited for a March morning would prove nothing —
 * and the scheduled run itself is run for real against the recorded responses,
 * as every run is (docs/TESTING.md → Never mocked).
 */
final class SchedulerTest extends TestCase {

	/**
	 * The site's zone: the one these installs run in, and one that still moves
	 * its clock twice a year.
	 */
	private const ZONE = 'Europe/Kiev';

	/**
	 * A token of the right shape that belongs to no bot.
	 */
	private const TOKEN = '111111111:AAtest-token-that-belongs-to-no-bot';

	/**
	 * The configured channel.
	 */
	private const CHAT_ID = '-1001234567890';

	/**
	 * What the stubbed options hold during one test.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = array();

	/**
	 * Every URL a run posted to.
	 *
	 * @var list<string>
	 */
	private array $posted = array();

	/**
	 * Everything that was done to WP-Cron, in order.
	 *
	 * @var list<array<int, mixed>>
	 */
	private array $cron_calls = array();

	/**
	 * What wp_next_scheduled() answers in this test.
	 *
	 * @var int|false
	 */
	private $next_scheduled = false;

	/**
	 * Whether this request is a WP-Cron request.
	 *
	 * @var bool
	 */
	private bool $doing_cron = false;

	/**
	 * Stubs WP-Cron, the options and the two hosts a run talks to.
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'get_transient' )->justReturn( 'a-cached-access-token' );
		Functions\when( 'wp_timezone_string' )->justReturn( self::ZONE );
		Functions\when( 'home_url' )->justReturn( 'https://dovira.vet' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'number_format_i18n' )->alias(
			static fn( $number ): string => number_format( (float) $number )
		);
		Functions\when( 'wp_date' )->alias(
			static function ( string $format, ?int $timestamp = null, $timezone = null ): string {
				$zone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone( 'UTC' );

				return ( new DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( $zone )->format( $format );
			}
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static fn( array $response ): int => (int) $response['response']['code']
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static fn( array $response ): string => (string) $response['body']
		);

		$this->options        = array(
			Settings::OPTION => array(
				'property_id'        => '533779496',
				'telegram_bot_token' => self::TOKEN,
				'telegram_chat_id'   => self::CHAT_ID,
				'send_time'          => '07:15',
				'blocks'             => array_fill_keys( Settings::BLOCKS, true ),
			),
		);
		$this->posted         = array();
		$this->cron_calls     = array();
		$this->next_scheduled = false;
		$this->doing_cron     = false;

		Functions\when( 'get_option' )->alias(
			fn( string $option, $default_value = false ) => array_key_exists( $option, $this->options ) ? $this->options[ $option ] : $default_value
		);
		Functions\when( 'update_option' )->alias(
			function ( string $option, $value ): bool {
				$this->options[ $option ] = $value;

				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( string $option ): bool {
				unset( $this->options[ $option ] );

				return true;
			}
		);
		Functions\when( 'wp_timezone' )->alias(
			static fn (): DateTimeZone => new DateTimeZone( self::ZONE )
		);
		Functions\when( 'wp_doing_cron' )->alias(
			fn (): bool => $this->doing_cron
		);
		Functions\when( 'wp_next_scheduled' )->alias(
			fn ( string $hook ) => 'gatb_daily_report' === $hook ? $this->next_scheduled : false
		);
		Functions\when( 'wp_clear_scheduled_hook' )->alias(
			function ( string $hook ): int {
				$this->cron_calls[] = array( 'clear', $hook );

				return 1;
			}
		);
		Functions\when( 'wp_schedule_event' )->alias(
			function ( int $timestamp, string $recurrence, string $hook ): bool {
				$this->cron_calls[] = array( 'schedule', $timestamp, $recurrence, $hook );

				return true;
			}
		);
		Functions\when( 'wp_unschedule_hook' )->alias(
			function ( string $hook ): int {
				$this->cron_calls[] = array( 'unschedule', $hook );

				return 1;
			}
		);
		Functions\when( 'wp_schedule_single_event' )->alias(
			function ( int $timestamp, string $hook, array $arguments = array() ): bool {
				$this->cron_calls[] = array( 'single', $timestamp, $hook, $arguments );

				return true;
			}
		);
		Functions\when( 'wp_remote_post' )->alias(
			fn( string $url, array $arguments ): array => $this->answer( $url, $arguments )
		);
	}

	/**
	 * Returns the Unix time of a local moment in the site's zone.
	 *
	 * @param string $local A local date and time, Y-m-d H:i:s.
	 */
	private static function at( string $local ): int {
		return ( new DateTimeImmutable( $local, new DateTimeZone( self::ZONE ) ) )->getTimestamp();
	}

	/**
	 * Returns a timestamp as the local time of day it is.
	 *
	 * @param int $timestamp The Unix time.
	 */
	private static function local( int $timestamp ): string {
		return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( new DateTimeZone( self::ZONE ) )->format( 'Y-m-d H:i' );
	}

	/**
	 * Answers one request the way its host would.
	 *
	 * Google's answer is chosen by how many reports the call asked for, as the
	 * real API's would be, so a test may run the whole chain twice.
	 *
	 * @param string               $url       The URL the run posted to.
	 * @param array<string, mixed> $arguments The request arguments.
	 * @return array{response: array{code: int}, body: string}
	 */
	private function answer( string $url, array $arguments ): array {
		$this->posted[] = $url;

		if ( false !== strpos( $url, 'api.telegram.org' ) ) {
			return $this->fixture( 'telegram/send-message-success.written.json' );
		}

		$body     = (array) json_decode( (string) $arguments['body'], true );
		$requests = isset( $body['requests'] ) && is_array( $body['requests'] ) ? count( $body['requests'] ) : 0;

		return $this->fixture( 'ga/batch-run-reports-daily-call-' . ( $requests > 1 ? '1' : '2' ) . '.json' );
	}

	/**
	 * Reads a fixture and shapes it the way wp_remote_post returns.
	 *
	 * @param string $name The fixture path under tests/fixtures/.
	 * @return array{response: array{code: int}, body: string}
	 */
	private function fixture( string $name ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a test fixture on disk, no WP_Filesystem in unit tests.
		$raw     = (string) file_get_contents( __DIR__ . '/../fixtures/' . $name );
		$fixture = (array) json_decode( $raw, true );

		return array(
			'response' => array( 'code' => (int) $fixture['status'] ),
			'body'     => (string) wp_json_encode( $fixture['body'] ),
		);
	}

	/**
	 * Returns the URLs a run posted to Telegram.
	 *
	 * @return list<string>
	 */
	private function telegram_calls(): array {
		return array_values( array_filter( $this->posted, static fn( string $url ): bool => false !== strpos( $url, 'api.telegram.org' ) ) );
	}

	/**
	 * A time of day that has not come yet belongs to today.
	 */
	public function test_the_next_occurrence_of_a_time_still_to_come_is_today(): void {
		$next = Scheduler::next_occurrence(
			new DateTimeZone( self::ZONE ),
			'09:00',
			self::at( '2026-09-09 06:00:00' )
		);

		$this->assertSame( self::at( '2026-09-09 09:00:00' ), $next );
	}

	/**
	 * One that has passed belongs to tomorrow — and so does the moment that is
	 * exactly it, or an event registered while the settings are saved at the
	 * send time would fire twice.
	 */
	public function test_a_time_that_has_passed_or_is_exactly_now_belongs_to_tomorrow(): void {
		$zone = new DateTimeZone( self::ZONE );

		$this->assertSame(
			self::at( '2026-09-10 09:00:00' ),
			Scheduler::next_occurrence( $zone, '09:00', self::at( '2026-09-09 09:00:01' ) ),
			'a second late is a day late'
		);
		$this->assertSame(
			self::at( '2026-09-10 09:00:00' ),
			Scheduler::next_occurrence( $zone, '09:00', self::at( '2026-09-09 09:00:00' ) ),
			'and so is exactly now'
		);
	}

	/**
	 * On the night the clock jumps forward the report still goes out at 09:00
	 * local — which is 22 hours after 10:00 the previous morning, not 23.
	 */
	public function test_the_hour_lost_in_spring_does_not_move_the_local_send_time(): void {
		$now = self::at( '2026-03-28 10:00:00' );

		$next = Scheduler::next_occurrence( new DateTimeZone( self::ZONE ), '09:00', $now );

		$this->assertSame( '2026-03-29 09:00', self::local( $next ) );
		$this->assertSame( 22 * 3600, $next - $now, 'the hour the country skips is skipped here too' );
	}

	/**
	 * And on the night it goes back, 09:00 local is 24 real hours after 10:00
	 * the previous morning, though only 23 hours show on the wall.
	 */
	public function test_the_hour_gained_in_autumn_does_not_move_the_local_send_time(): void {
		$now = self::at( '2026-10-24 10:00:00' );

		$next = Scheduler::next_occurrence( new DateTimeZone( self::ZONE ), '09:00', $now );

		$this->assertSame( '2026-10-25 09:00', self::local( $next ) );
		$this->assertSame( 24 * 3600, $next - $now, 'the hour the country repeats is waited out' );
	}

	/**
	 * A send time just after midnight is minutes away, not a day.
	 */
	public function test_a_send_time_just_after_midnight_is_minutes_away(): void {
		$now = self::at( '2026-09-09 23:50:00' );

		$next = Scheduler::next_occurrence( new DateTimeZone( self::ZONE ), '00:30', $now );

		$this->assertSame( self::at( '2026-09-10 00:30:00' ), $next );
		$this->assertSame( 40 * 60, $next - $now );
	}

	/**
	 * A local time that does not exist on the day the clock jumps resolves
	 * forward instead of throwing: 03:30 becomes 04:30.
	 */
	public function test_a_local_time_that_does_not_exist_resolves_forward(): void {
		$next = Scheduler::next_occurrence(
			new DateTimeZone( self::ZONE ),
			'03:30',
			self::at( '2026-03-29 00:10:00' )
		);

		$this->assertSame( self::at( '2026-03-29 04:30:00' ), $next );
		$this->assertSame( '2026-03-29 04:30', self::local( $next ) );
	}

	/**
	 * A save replaces the event: it is cleared first, then registered once, at
	 * the configured time of day.
	 */
	public function test_a_save_replaces_the_event_instead_of_adding_a_second(): void {
		Scheduler::reschedule();

		$this->assertCount( 2, $this->cron_calls, 'one clear, one schedule' );
		$this->assertSame( array( 'clear', 'gatb_daily_report' ), $this->cron_calls[0] );

		$scheduled = $this->cron_calls[1];
		$this->assertSame( 'schedule', $scheduled[0] );
		$this->assertSame( 'daily', $scheduled[2] );
		$this->assertSame( 'gatb_daily_report', $scheduled[3] );
		$this->assertGreaterThan( time(), $scheduled[1], 'the first run is still to come' );
		$this->assertStringEndsWith( '07:15', self::local( (int) $scheduled[1] ), 'at the configured time, site-local' );
	}

	/**
	 * Deactivation takes both events away, the retry one included — whatever
	 * arguments its events carry, which wp_clear_scheduled_hook() would not.
	 */
	public function test_deactivation_clears_both_events(): void {
		Scheduler::clear();

		$this->assertSame(
			array(
				array( 'unschedule', 'gatb_daily_report' ),
				array( 'unschedule', 'gatb_retry_report' ),
			),
			$this->cron_calls
		);
	}

	/**
	 * A retry is one single event an hour out, carrying the day it is for.
	 */
	public function test_a_retry_is_booked_an_hour_later_with_its_day(): void {
		$now = self::at( '2026-09-09 07:15:00' );

		Scheduler::schedule_retry( '2026-09-08', $now );

		$this->assertSame(
			array(
				array( 'single', $now + 3600, 'gatb_retry_report', array( '2026-09-08' ) ),
			),
			$this->cron_calls
		);
		$this->assertSame( '2026-09-09 08:15', self::local( $now + 3600 ) );
	}

	/**
	 * The retry run says a retry asked, and reports the day it was booked for.
	 */
	public function test_the_retry_run_reports_the_day_it_was_booked_for(): void {
		$yesterday = ( new DateTimeImmutable( 'now', new DateTimeZone( 'Europe/Kiev' ) ) )->modify( '-1 day' )->format( 'Y-m-d' );

		Scheduler::run_retry( $yesterday );

		$entries = RunLog::entries();
		$this->assertCount( 1, $entries );
		$this->assertSame( 'retry', $entries[0]['trigger'] );
		$this->assertSame( $yesterday, $entries[0]['date'], 'the day the event carried' );
		$this->assertSame( 'sent', $entries[0]['status'] );
		$this->assertCount( 1, $this->telegram_calls() );
	}

	/**
	 * An event scheduled without its day is a run, not a fatal error.
	 */
	public function test_a_retry_event_without_its_day_still_runs(): void {
		Scheduler::run_retry();

		$entries = RunLog::entries();
		$this->assertCount( 1, $entries );
		$this->assertSame( 'retry', $entries[0]['trigger'] );
		$this->assertSame( 'sent', $entries[0]['status'] );
	}

	/**
	 * The next run is WP-Cron's own answer, and "nothing scheduled" is a state
	 * the screen can print rather than a zero.
	 */
	public function test_the_next_run_is_what_wp_cron_says(): void {
		$this->assertNull( Scheduler::next_run() );

		$this->next_scheduled = self::at( '2026-09-10 09:00:00' );

		$this->assertSame( self::at( '2026-09-10 09:00:00' ), Scheduler::next_run() );
	}

	/**
	 * The scheduled run says it was the schedule that asked, and the day it
	 * delivered is not delivered again — a duplicate firing sends nothing and
	 * logs nothing, because nothing happened.
	 */
	public function test_the_scheduled_run_is_logged_as_cron_and_the_day_is_not_sent_twice(): void {
		Scheduler::run_daily();

		$entries = RunLog::entries();
		$this->assertCount( 1, $entries );
		$this->assertSame( 'cron', $entries[0]['trigger'] );
		$this->assertSame( 'sent', $entries[0]['status'] );
		$this->assertCount( 1, $this->telegram_calls() );

		Scheduler::run_daily();

		$this->assertCount( 1, RunLog::entries(), 'the second firing logged nothing' );
		$this->assertCount( 1, $this->telegram_calls(), 'and sent nothing' );
	}

	/**
	 * A day that has not gone out yet does go out, even though another one has:
	 * the guard is about the day in hand, not about having run before.
	 */
	public function test_a_day_that_was_never_sent_is_sent_although_an_earlier_one_was(): void {
		RunLog::mark_sent( '2020-01-01' );

		Scheduler::run_daily();

		$entries = RunLog::entries();
		$this->assertCount( 1, $entries );
		$this->assertSame( 'sent', $entries[0]['status'] );
		$this->assertCount( 1, $this->telegram_calls() );
		$this->assertNotSame( '2020-01-01', RunLog::last_report_date(), 'the newer day is what is remembered' );
	}

	/**
	 * Only a WP-Cron request counts as one.
	 */
	public function test_only_a_cron_request_records_a_cron_hit(): void {
		Scheduler::note_cron_hit();

		$this->assertSame( 0, RunLog::last_cron_hit(), 'an ordinary page view is not a cron hit' );

		$this->doing_cron = true;
		Scheduler::note_cron_hit();

		$this->assertGreaterThanOrEqual( time() - 5, RunLog::last_cron_hit() );
	}

	/**
	 * The warning belongs to a site that switched WP-Cron off and put nothing
	 * in its place — never to one where WP-Cron is doing its job.
	 */
	public function test_the_warning_is_only_for_a_site_with_nothing_calling_wp_cron(): void {
		$now = self::at( '2026-09-09 09:00:00' );

		$this->assertTrue( Scheduler::external_cron_missing( true, 0, $now ), 'switched off and never visited' );
		$this->assertTrue( Scheduler::external_cron_missing( true, $now - 86401, $now ), 'and not visited for a day' );
		$this->assertFalse( Scheduler::external_cron_missing( true, $now - 3600, $now ), 'an hour ago is a working cron' );
		$this->assertFalse( Scheduler::external_cron_missing( false, 0, $now ), 'WP-Cron on: silence only means nothing was due' );
		$this->assertFalse( Scheduler::external_cron_missing( false, $now - 999999, $now ) );
	}
}
