<?php
/**
 * Tests for the "Send now" button and the run log it fills.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge\Tests\Unit;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use GaTelegramBridge\Admin;
use GaTelegramBridge\RunLog;
use GaTelegramBridge\Settings;
use GaTelegramBridge\Tests\TestCase;

/**
 * The button is exercised end to end apart from the network and the redirect.
 */
final class AdminSendNowTest extends TestCase {

	/**
	 * What the stubbed redirect throws, so the handler stops where wp-admin
	 * would leave the request.
	 */
	private const REDIRECTED = 'gatb-redirected';

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
	 * How Telegram answers this test.
	 *
	 * @var string
	 */
	private string $telegram_answer = 'send-message-success.written.json';

	/**
	 * Stubs the WordPress helpers the screen and the handler go through.
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'esc_url' )->returnArg();
		// The run log names the build it is running and links to the readme.
		Functions\when( 'get_file_data' )->justReturn( array( 'Version' => '0.1.0' ) );
		Functions\when( 'plugins_url' )->justReturn( 'https://dovira.vet/wp-content/plugins/ga-telegram-bridge/readme.txt' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'get_transient' )->justReturn( 'a-cached-access-token' );
		Functions\when( 'wp_timezone_string' )->justReturn( 'Europe/Kyiv' );
		Functions\when( 'home_url' )->justReturn( 'https://dovira.vet' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		// No post names the recorded pages here; naming them is ReportBuilderTest's.
		Functions\when( 'url_to_postid' )->justReturn( 0 );
		Functions\when( 'number_format_i18n' )->alias(
			static fn( $number ): string => number_format( (float) $number )
		);
		Functions\when( 'wp_date' )->alias(
			static function ( string $format, ?int $timestamp = null, $timezone = null ): string {
				$zone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone( 'UTC' );

				return ( new DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( $zone )->format( $format );
			}
		);
		Functions\when( 'admin_url' )->alias(
			static fn( string $path = '' ): string => 'https://example.test/wp-admin/' . $path
		);
		Functions\when( 'add_query_arg' )->justReturn( 'https://example.test/wp-admin/options-general.php?page=gatb-settings&settings-updated=true' );
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
		$this->telegram_answer = 'send-message-success.written.json';

		Functions\when( 'get_option' )->alias(
			fn( string $option, $default_value = false ) => $this->option( $option, $default_value )
		);
		Functions\when( 'update_option' )->alias(
			function ( string $option, $value ): bool {
				$this->options[ $option ] = $value;

				return true;
			}
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
	 * Lets the run reach both hosts, each answering as this test wants.
	 *
	 * @param bool $stub_redirect Whether to stub the redirect too.
	 */
	private function given_a_configured_site( bool $stub_redirect = true ): void {
		Functions\when( 'check_admin_referer' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_settings_errors' )->justReturn( array() );

		Functions\when( 'wp_remote_post' )->alias(
			function ( string $url, array $arguments ): array {
				$this->posted[] = $url;

				if ( false !== strpos( $url, 'api.telegram.org' ) ) {
					return $this->fixture( 'telegram/' . $this->telegram_answer );
				}

				$body     = (array) json_decode( (string) $arguments['body'], true );
				$requests = isset( $body['requests'] ) && is_array( $body['requests'] ) ? count( $body['requests'] ) : 0;

				return $this->fixture( 'ga/batch-run-reports-daily-call-' . ( $requests > 1 ? '1' : '2' ) . '.json' );
			}
		);

		// Not stubbed when the test sets its own expectation on the redirect
		// tail: a when() would shadow the expect() and it would never be met.
		if ( $stub_redirect ) {
			Functions\when( 'set_transient' )->justReturn( true );
			Functions\when( 'wp_safe_redirect' )->alias(
				static function (): void {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- a test marker, never rendered.
					throw new \RuntimeException( self::REDIRECTED );
				}
			);
		}
	}

	/**
	 * Runs the handler up to the redirect that ends it.
	 *
	 * @throws \RuntimeException When the handler failed for any other reason.
	 */
	private function run_handler(): void {
		try {
			Admin::handle_send_now();
		} catch ( \RuntimeException $stopped ) {
			if ( self::REDIRECTED !== $stopped->getMessage() ) {
				throw $stopped;
			}

			$this->assertSame( self::REDIRECTED, $stopped->getMessage() );
		}
	}

	/**
	 * The section offers sending as a fourth form with its own nonce.
	 */
	public function test_the_section_offers_send_now_with_its_own_nonce(): void {
		Functions\expect( 'wp_nonce_field' )->once()->with( 'gatb_check_ga' );
		Functions\expect( 'wp_nonce_field' )->once()->with( 'gatb_check_telegram' );
		Functions\expect( 'wp_nonce_field' )->once()->with( 'gatb_preview' );
		Functions\expect( 'wp_nonce_field' )->once()->with( 'gatb_send_now' );
		Functions\when( 'submit_button' )->alias(
			static function ( string $text ): void {
				echo '<button>' . esc_html( $text ) . '</button>';
			}
		);

		ob_start();
		Admin::render_connection_section();
		$markup = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="action" value="gatb_send_now"', $markup );
		$this->assertStringContainsString( '<button>Send now</button>', $markup );
		$this->assertStringContainsString( 'really sends it', $markup, 'the button says that it sends' );
	}

	/**
	 * The button sends the report and says so, once.
	 */
	public function test_the_button_sends_the_report_and_logs_it(): void {
		$this->given_a_configured_site();

		Functions\expect( 'add_settings_error' )
			->once()
			->with(
				'gatb_settings',
				'gatb_send_now',
				\Mockery::type( 'string' ),
				'success'
			);

		$this->run_handler();

		$entries = RunLog::entries();

		$this->assertCount( 1, $entries );
		$this->assertSame( 'sent', $entries[0]['status'] );
		$this->assertSame( 'manual', $entries[0]['trigger'], 'the log says a person asked for it' );
	}

	/**
	 * A second press sends again: the date guard is for the schedule, not for
	 * the person pressing the button.
	 */
	public function test_a_second_press_sends_the_same_day_again(): void {
		$this->given_a_configured_site();
		Functions\when( 'add_settings_error' )->justReturn( null );

		$this->run_handler();
		$this->run_handler();

		$entries = RunLog::entries();

		$this->assertCount( 2, $entries );
		$this->assertSame( 'sent', $entries[0]['status'] );
		$this->assertSame( 'sent', $entries[1]['status'] );
	}

	/**
	 * A refused send becomes an error notice, not a fatal.
	 */
	public function test_a_refused_send_is_reported_as_an_error(): void {
		$this->given_a_configured_site();
		$this->telegram_answer = 'error-chat-not-found.written.json';

		Functions\expect( 'add_settings_error' )
			->once()
			->with(
				'gatb_settings',
				'gatb_send_now',
				\Mockery::on(
					function ( string $message ): bool {
						$this->assertStringContainsString( 'cannot find that chat', $message );
						$this->assertStringNotContainsString( self::TOKEN, $message );

						return true;
					}
				),
				'error'
			);

		$this->run_handler();

		$this->assertSame( 'failed', RunLog::entries()[0]['status'] );
	}

	/**
	 * Negative check: without the capability nothing is sent and nothing logged.
	 */
	public function test_a_user_without_the_capability_sends_nothing(): void {
		$this->given_a_configured_site();
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'wp_die' )->alias(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);
		Functions\expect( 'add_settings_error' )->never();

		try {
			Admin::handle_send_now();
			$this->fail( 'the handler should have died' );
		} catch ( \RuntimeException $died ) {
			$this->assertSame( 'wp_die', $died->getMessage() );
		}

		$this->assertSame( array(), $this->posted, 'no host was contacted' );
		$this->assertSame( array(), RunLog::entries() );
	}

	/**
	 * Negative check: a request without a valid nonce sends nothing either.
	 */
	public function test_a_request_without_a_valid_nonce_sends_nothing(): void {
		$this->given_a_configured_site();
		Functions\when( 'check_admin_referer' )->alias(
			static function (): void {
				throw new \RuntimeException( 'wp_nonce_ays' );
			}
		);
		Functions\expect( 'add_settings_error' )->never();

		try {
			Admin::handle_send_now();
			$this->fail( 'the handler should have been stopped' );
		} catch ( \RuntimeException $stopped ) {
			$this->assertSame( 'wp_nonce_ays', $stopped->getMessage() );
		}

		$this->assertSame( array(), $this->posted );
		$this->assertSame( array(), RunLog::entries() );
	}

	/**
	 * The table shows one row per run, newest first, as text.
	 */
	public function test_the_log_table_shows_every_run(): void {
		$markup = $this->render_log(
			array(
				array(
					'time'    => 1757505600,
					'trigger' => 'manual',
					'date'    => '2025-09-09',
					'status'  => 'sent',
					'attempt' => 1,
					'message' => 'The report for 2025-09-09 was sent to chat -1001234567890.',
				),
				array(
					'time'    => 1757419200,
					'trigger' => 'cron',
					'date'    => '2025-09-08',
					'status'  => 'failed',
					'attempt' => 2,
					'message' => 'Telegram cannot find that chat.',
				),
			)
		);

		$this->assertStringContainsString( '<h2>Run log</h2>', $markup );
		$this->assertStringContainsString( '2025-09-10 12:00', $markup, 'the time of the run, in the site\'s zone' );
		$this->assertStringContainsString( '<td>Send now</td>', $markup );
		$this->assertStringContainsString( '<td>Schedule</td>', $markup );
		$this->assertStringContainsString( '<td>Sent</td>', $markup );
		$this->assertStringContainsString( '<td>Failed</td>', $markup );
		$this->assertStringContainsString( 'The report for 2025-09-09 was sent', $markup );
		$this->assertSame( 2, substr_count( $markup, '<tr>' ) - 1, 'two runs and one header row' );
	}

	/**
	 * A site that has never run says so, in the table rather than beside it.
	 */
	public function test_an_empty_log_says_nothing_has_been_sent(): void {
		$markup = $this->render_log( array() );

		$this->assertStringContainsString( 'Nothing has been sent yet.', $markup );
	}

	/**
	 * Whatever a message holds, the table prints it as text.
	 */
	public function test_the_table_escapes_what_it_prints(): void {
		$markup = $this->render_log(
			array(
				array(
					'time'    => 1757505600,
					'trigger' => 'manual',
					'date'    => '2025-09-09',
					'status'  => 'failed',
					'message' => '<script>alert(1)</script>',
					'attempt' => 1,
				),
			)
		);

		$this->assertStringNotContainsString( '<script>', $markup );
		$this->assertStringContainsString( '&lt;script&gt;', $markup );
	}

	/**
	 * Renders the log table and returns its markup.
	 *
	 * @param list<array{time: int, trigger: string, date: string, status: string, attempt: int, message: string}> $entries The runs.
	 */
	private function render_log( array $entries ): string {
		ob_start();
		Admin::render_log_section( $entries );

		return (string) ob_get_clean();
	}
}
