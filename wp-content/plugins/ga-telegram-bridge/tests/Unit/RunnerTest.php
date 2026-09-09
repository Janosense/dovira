<?php
/**
 * Tests for one whole run: Google, Telegram, the log and the date guard.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge\Tests\Unit;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use GaTelegramBridge\RunLog;
use GaTelegramBridge\Runner;
use GaTelegramBridge\Settings;
use GaTelegramBridge\Tests\TestCase;

/**
 * The chain runs for real against the recorded responses of the live property
 * (docs/TESTING.md → Never mocked); only the network boundary is stubbed, one
 * answer per host, and the options behave like the database.
 */
final class RunnerTest extends TestCase {

	/**
	 * A moment in the middle of a day, so "yesterday" is unambiguous.
	 */
	private const NOON = 1757505600;

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
	 * Every URL the run posted to.
	 *
	 * @var list<string>
	 */
	private array $posted = array();

	/**
	 * Every attempt booked with WP-Cron, in order.
	 *
	 * @var list<array{int, string, array<int, mixed>}>
	 */
	private array $booked = array();

	/**
	 * How Telegram answers this test.
	 *
	 * @var string
	 */
	private string $telegram_answer = 'send-message-success.written.json';

	/**
	 * How Google answers this test.
	 *
	 * @var string|null
	 */
	private ?string $google_error = null;

	/**
	 * Stubs the WordPress helpers and the two hosts the run talks to.
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

		$this->options         = array(
			Settings::OPTION => array(
				'property_id'        => '533779496',
				'telegram_bot_token' => self::TOKEN,
				'telegram_chat_id'   => self::CHAT_ID,
				'blocks'             => array_fill_keys( Settings::BLOCKS, true ),
			),
		);
		$this->posted          = array();
		$this->booked          = array();
		$this->telegram_answer = 'send-message-success.written.json';
		$this->google_error    = null;

		Functions\when( 'wp_schedule_single_event' )->alias(
			function ( int $timestamp, string $hook, array $arguments = array() ): bool {
				$this->booked[] = array( $timestamp, $hook, $arguments );

				return true;
			}
		);

		Functions\when( 'get_option' )->alias(
			fn( string $option, $default_value = false ) => $this->option( $option, $default_value )
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
		Functions\when( 'wp_remote_post' )->alias(
			fn( string $url, array $arguments ): array => $this->answer( $url, $arguments )
		);
	}

	/**
	 * Returns what the stubbed database holds for an option.
	 *
	 * @param string $option        The option name.
	 * @param mixed  $default_value What get_option() was asked to fall back to.
	 * @return mixed
	 */
	private function option( string $option, $default_value ) {
		return array_key_exists( $option, $this->options ) ? $this->options[ $option ] : $default_value;
	}

	/**
	 * Answers one request the way its host would.
	 *
	 * Google's answer is chosen by how many reports the call asked for, as the
	 * real API's would be — five is the first recorded call, one the second —
	 * so a test may run the whole chain twice; which fixture Telegram returns
	 * is what each test varies.
	 *
	 * @param string               $url       The URL the run posted to.
	 * @param array<string, mixed> $arguments The request arguments.
	 * @return array{response: array{code: int}, body: string}
	 */
	private function answer( string $url, array $arguments ): array {
		$this->posted[] = $url;

		if ( false !== strpos( $url, 'api.telegram.org' ) ) {
			return $this->fixture( 'telegram/' . $this->telegram_answer );
		}

		if ( null !== $this->google_error ) {
			return $this->fixture( 'ga/' . $this->google_error );
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
	 * Returns the URLs the run posted to Telegram.
	 *
	 * @return list<string>
	 */
	private function telegram_calls(): array {
		return array_values( array_filter( $this->posted, static fn( string $url ): bool => false !== strpos( $url, 'api.telegram.org' ) ) );
	}

	/**
	 * A run that reaches Telegram is logged and remembered.
	 */
	public function test_a_delivered_report_is_logged_and_the_day_remembered(): void {
		$entry = Runner::run( 'manual', false, self::NOON );

		$this->assertIsArray( $entry );
		$this->assertSame( 'manual', $entry['trigger'] );
		$this->assertSame( 'sent', $entry['status'] );
		$this->assertSame( '2025-09-09', $entry['date'], 'the property\'s day, not the server\'s' );
		$this->assertSame( 1, $entry['attempt'] );
		$this->assertStringContainsString( self::CHAT_ID, $entry['message'] );

		$this->assertSame( '2025-09-09', RunLog::last_report_date() );
		$this->assertSame( 0, RunLog::attempt() );
		$this->assertSame( array( $entry ), RunLog::entries() );
		$this->assertCount( 1, $this->telegram_calls(), 'one message, not one per block' );
	}

	/**
	 * The message Telegram receives is the rendered report.
	 */
	public function test_what_is_sent_is_the_report(): void {
		$sent = null;

		Functions\when( 'wp_remote_post' )->alias(
			function ( string $url, array $arguments ) use ( &$sent ): array {
				if ( false !== strpos( $url, 'api.telegram.org' ) ) {
					$sent = json_decode( (string) $arguments['body'], true );
				}

				return $this->answer( $url, $arguments );
			}
		);

		Runner::run( 'manual', false, self::NOON );

		$this->assertIsArray( $sent );
		$this->assertSame( self::CHAT_ID, $sent['chat_id'] );
		$this->assertSame( 'HTML', $sent['parse_mode'] );
		$this->assertStringContainsString( '📊 <b>dovira.vet — 9 September (Tuesday)</b>', $sent['text'] );
		$this->assertStringContainsString( 'Yesterday: 69', $sent['text'] );
	}

	/**
	 * A refused send is logged, counted, and leaves the day unsent.
	 */
	public function test_a_refused_send_leaves_the_day_unsent(): void {
		$this->telegram_answer = 'error-chat-not-found.written.json';

		$entry = Runner::run( 'manual', false, self::NOON );

		$this->assertIsArray( $entry );
		$this->assertSame( 'failed', $entry['status'] );
		$this->assertSame( '2025-09-09', $entry['date'] );
		$this->assertStringContainsString( 'cannot find that chat', $entry['message'] );

		$this->assertSame( '', RunLog::last_report_date(), 'the day was not delivered, so it is not remembered' );
		$this->assertSame( 1, RunLog::attempt() );
	}

	/**
	 * A second failure counts as the second attempt.
	 */
	public function test_a_second_failure_is_the_second_attempt(): void {
		$this->telegram_answer = 'error-chat-not-found.written.json';

		Runner::run( 'manual', false, self::NOON );
		$second = Runner::run( 'manual', false, self::NOON );

		$this->assertIsArray( $second );
		$this->assertSame( 2, $second['attempt'] );
		$this->assertSame( 2, RunLog::attempt() );
	}

	/**
	 * A report that cannot be read is a failure, and Telegram is never called.
	 */
	public function test_a_report_that_cannot_be_read_never_reaches_telegram(): void {
		$this->google_error = 'error-no-access-property.json';

		$entry = Runner::run( 'cron', false, self::NOON );

		$this->assertIsArray( $entry );
		$this->assertSame( 'failed', $entry['status'] );
		$this->assertSame( 'cron', $entry['trigger'] );
		$this->assertStringContainsString( 'cannot read this property', $entry['message'] );
		$this->assertSame( array(), $this->telegram_calls(), 'nothing is sent when there is nothing to send' );
		$this->assertNotSame( '', $entry['date'], 'the log still says which day the run was about' );
	}

	/**
	 * A failed scheduled run books one more attempt an hour later, for the day
	 * it was about.
	 */
	public function test_a_failed_scheduled_run_books_another_attempt_in_an_hour(): void {
		$this->google_error = 'error-no-access-property.json';

		$entry = Runner::run( 'cron', false, self::NOON );

		$this->assertIsArray( $entry );
		$this->assertSame( 1, $entry['attempt'] );
		$this->assertSame(
			array( array( self::NOON + 3600, 'gatb_retry_report', array( $entry['date'] ) ) ),
			$this->booked,
			'one single event, carrying the day the attempt is for'
		);
	}

	/**
	 * A failed *Send now* books nothing: the person who pressed it is looking
	 * at the reason and can press again.
	 */
	public function test_a_failed_manual_run_books_nothing(): void {
		$this->google_error = 'error-no-access-property.json';

		Runner::run( 'manual', false, self::NOON );

		$this->assertSame( array(), $this->booked );
		$this->assertSame( 1, RunLog::attempt(), 'the day still failed once' );
	}

	/**
	 * The last attempt a day is allowed books nothing more, tells the chat and
	 * lets the counting start again.
	 */
	public function test_the_last_attempt_gives_the_day_up_and_tells_the_chat(): void {
		$this->options[ Settings::OPTION ]['max_attempts'] = 2;
		$this->google_error                                = 'error-no-access-property.json';

		Runner::run( 'cron', false, self::NOON );
		$this->assertCount( 1, $this->booked, 'the first attempt still has one left' );
		$this->assertSame( array(), $this->telegram_calls(), 'and the chat is not told yet' );

		$this->booked = array();
		$second       = Runner::run( 'retry', false, self::NOON, '2025-09-09' );

		$this->assertIsArray( $second );
		$this->assertSame( 2, $second['attempt'] );
		$this->assertSame( '2025-09-09', $second['date'], 'the day the retry was booked for' );
		$this->assertSame( array(), $this->booked, 'nothing more is booked' );
		$this->assertCount( 1, $this->telegram_calls(), 'the notice is the only thing sent' );
		$this->assertStringContainsString( 'No attempts are left', $second['message'] );
		$this->assertSame( 0, RunLog::attempt(), 'the next day starts at one again' );
		$this->assertSame( '', RunLog::last_report_date(), 'and the day is still unsent' );
	}

	/**
	 * What the chat receives is the failure notice for that day.
	 */
	public function test_the_notice_names_the_day_that_could_not_be_reported(): void {
		$this->options[ Settings::OPTION ]['max_attempts'] = 1;
		$this->google_error                                = 'error-no-access-property.json';
		$sent = null;

		Functions\when( 'wp_remote_post' )->alias(
			function ( string $url, array $arguments ) use ( &$sent ): array {
				if ( false !== strpos( $url, 'api.telegram.org' ) ) {
					$sent = json_decode( (string) $arguments['body'], true );
				}

				return $this->answer( $url, $arguments );
			}
		);

		Runner::run( 'cron', false, self::NOON, '2025-09-09' );

		$this->assertIsArray( $sent );
		$this->assertSame( self::CHAT_ID, $sent['chat_id'] );
		$this->assertStringContainsString( '⚠️', $sent['text'] );
		$this->assertStringContainsString( '9 September', $sent['text'] );
	}

	/**
	 * A notice that cannot be delivered either is only logged — in the same row
	 * as the failure it was about.
	 */
	public function test_a_notice_that_is_refused_is_logged_and_nothing_more(): void {
		$this->options[ Settings::OPTION ]['max_attempts'] = 1;
		$this->telegram_answer                             = 'error-chat-not-found.written.json';

		$entry = Runner::run( 'cron', false, self::NOON );

		$this->assertIsArray( $entry );
		$this->assertCount( 1, RunLog::entries(), 'one run is one row' );
		$this->assertStringContainsString( 'cannot find that chat', $entry['message'], 'the reason the report failed' );
		$this->assertStringContainsString( 'the chat could not be told either', $entry['message'] );
		$this->assertSame( 0, RunLog::attempt() );
	}

	/**
	 * A retry that wakes up after the property's midnight reports the day it
	 * was booked for, gives it up and sends nothing but the notice.
	 */
	public function test_a_retry_whose_day_has_passed_gives_that_day_up(): void {
		$entry = Runner::run( 'retry', false, self::NOON, '2025-09-08' );

		$this->assertIsArray( $entry );
		$this->assertSame( 'failed', $entry['status'] );
		$this->assertSame( '2025-09-08', $entry['date'], 'the day the attempt was for, not the day Google now has' );
		$this->assertStringContainsString( 'can no longer be built', $entry['message'] );
		$this->assertStringContainsString( '2025-09-09', $entry['message'], 'and what the property calls yesterday now' );
		$this->assertSame( array(), $this->booked, 'no later attempt could do better' );
		$this->assertCount( 1, $this->telegram_calls(), 'only the notice' );
		$this->assertSame( '', RunLog::last_report_date(), 'the report it happened to hold is not this run\'s to send' );
	}

	/**
	 * The same day is not sent twice by itself.
	 */
	public function test_the_date_guard_stops_a_second_run_for_the_same_day(): void {
		Runner::run( 'cron', false, self::NOON );
		$telegram_calls = count( $this->telegram_calls() );

		$second = Runner::run( 'cron', false, self::NOON );

		$this->assertNull( $second, 'nothing happened, so nothing is logged' );
		$this->assertCount( 1, RunLog::entries() );
		$this->assertCount( $telegram_calls, $this->telegram_calls(), 'Telegram was not called again' );
	}

	/**
	 * "Send now" sends anyway, and says that a person asked for it.
	 */
	public function test_a_manual_run_sends_the_same_day_again(): void {
		Runner::run( 'cron', false, self::NOON );

		$second = Runner::run( 'manual', true, self::NOON );

		$this->assertIsArray( $second );
		$this->assertSame( 'sent', $second['status'] );
		$this->assertSame( 'manual', $second['trigger'] );
		$this->assertCount( 2, RunLog::entries() );
		$this->assertCount( 2, $this->telegram_calls() );
	}

	/**
	 * Negative check: nothing the failure notice sends or leaves behind carries
	 * a secret — not the bot token, not a fragment of the key.
	 */
	public function test_the_failure_notice_carries_no_secret(): void {
		$this->options[ Settings::OPTION ]['max_attempts']         = 1;
		$this->options[ Settings::OPTION ]['service_account_json'] = '{"private_key":"-----BEGIN PRIVATE KEY-----NOTAKEY-----END PRIVATE KEY-----"}';
		$this->google_error                                        = 'error-no-access-property.json';
		$bodies = array();

		Functions\when( 'wp_remote_post' )->alias(
			function ( string $url, array $arguments ) use ( &$bodies ): array {
				$bodies[] = (string) $arguments['body'];

				return $this->answer( $url, $arguments );
			}
		);

		Runner::run( 'cron', false, self::NOON );

		$this->assertNotSame( array(), $bodies, 'the notice was sent' );

		foreach ( $bodies as $body ) {
			$this->assertStringNotContainsString( self::TOKEN, $body );
			$this->assertStringNotContainsString( 'NOTAKEY', $body );
		}

		$stored = (string) wp_json_encode( RunLog::entries() );
		$this->assertStringNotContainsString( self::TOKEN, $stored );
		$this->assertStringNotContainsString( 'NOTAKEY', $stored );
	}

	/**
	 * Negative check: the bot token is never written to the database.
	 *
	 * The token travels in the request URL, so a transport error can quote it
	 * back; anything the log stores has been scrubbed of it first.
	 */
	public function test_the_bot_token_never_reaches_the_log(): void {
		$this->telegram_answer = 'error-unauthorized.json';

		$entry = Runner::run( 'manual', false, self::NOON );

		$this->assertIsArray( $entry );
		$this->assertStringNotContainsString( self::TOKEN, $entry['message'] );
		$this->assertStringNotContainsString( self::TOKEN, (string) wp_json_encode( RunLog::entries() ) );
	}
}
