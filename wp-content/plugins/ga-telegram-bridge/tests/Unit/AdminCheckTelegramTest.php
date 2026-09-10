<?php
/**
 * Tests for the "Check Telegram" button: the form, what is sent, and what the
 * administrator is told afterwards.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge\Tests\Unit;

use Brain\Monkey\Functions;
use GaTelegramBridge\Admin;
use GaTelegramBridge\Tests\TestCase;

/**
 * The handler is exercised end to end apart from the network and the redirect.
 */
final class AdminCheckTelegramTest extends TestCase {

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
	 * Stubs the WordPress helpers the screen and the handler go through.
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'home_url' )->justReturn( 'https://dovira.vet' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
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
	}

	/**
	 * Reads a fixture and shapes it the way wp_remote_post returns.
	 *
	 * @param string $name The fixture file name.
	 * @return array{response: array{code: int}, body: string}
	 */
	private function fixture( string $name ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a test fixture on disk, no WP_Filesystem in unit tests.
		$raw     = (string) file_get_contents( __DIR__ . '/../fixtures/telegram/' . $name );
		$fixture = (array) json_decode( $raw, true );

		return array(
			'response' => array( 'code' => (int) $fixture['status'] ),
			'body'     => (string) wp_json_encode( $fixture['body'] ),
		);
	}

	/**
	 * Configures a site that has a bot token and a chat to post into.
	 *
	 * @param bool $stub_redirect Whether to stub the redirect too.
	 */
	private function given_a_configured_site( bool $stub_redirect = true ): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'telegram_bot_token' => self::TOKEN,
				'telegram_chat_id'   => self::CHAT_ID,
			)
		);
		Functions\when( 'check_admin_referer' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_settings_errors' )->justReturn( array() );

		// Not stubbed when the test sets its own expectation on the redirect
		// tail: a when() would shadow the expect() and it would never be met.
		if ( $stub_redirect ) {
			Functions\when( 'set_transient' )->justReturn( true );
			$this->stub_redirect();
		}
	}

	/**
	 * Makes wp_safe_redirect() end the run, as it does in wp-admin.
	 */
	private function stub_redirect(): void {
		Functions\when( 'wp_safe_redirect' )->alias(
			static function (): void {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- a test marker, never rendered.
				throw new \RuntimeException( self::REDIRECTED );
			}
		);
	}

	/**
	 * Runs the handler up to the redirect that ends it.
	 *
	 * @throws \RuntimeException When the handler failed for any other reason.
	 */
	private function run_handler(): void {
		try {
			Admin::handle_check_telegram();
		} catch ( \RuntimeException $stopped ) {
			if ( self::REDIRECTED !== $stopped->getMessage() ) {
				throw $stopped;
			}

			$this->assertSame( self::REDIRECTED, $stopped->getMessage() );
		}
	}

	/**
	 * The section offers both checks, each its own form with its own nonce.
	 */
	public function test_the_section_offers_both_checks_each_with_its_own_nonce(): void {
		Functions\expect( 'wp_nonce_field' )->once()->with( 'gatb_check_ga' );
		Functions\expect( 'wp_nonce_field' )->once()->with( 'gatb_check_telegram' );
		Functions\when( 'submit_button' )->alias(
			static function ( string $text ): void {
				echo '<button>' . esc_html( $text ) . '</button>';
			}
		);

		ob_start();
		Admin::render_connection_section();
		$markup = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="action" value="gatb_check_ga"', $markup );
		$this->assertStringContainsString( 'name="action" value="gatb_check_telegram"', $markup );
		$this->assertStringContainsString( '<button>Check Telegram</button>', $markup );
		$this->assertStringContainsString( 'Really posts a short test message', $markup, 'the button warns that it sends something' );
	}

	/**
	 * The test message goes to the configured chat, as HTML, naming the site.
	 */
	public function test_the_test_message_is_sent_to_the_configured_chat(): void {
		$this->given_a_configured_site();

		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				'https://api.telegram.org/bot' . self::TOKEN . '/sendMessage',
				\Mockery::on(
					function ( array $arguments ): bool {
						$body = json_decode( $arguments['body'], true );

						$this->assertSame( self::CHAT_ID, $body['chat_id'] );
						$this->assertSame( 'HTML', $body['parse_mode'] );
						$this->assertStringContainsString( '<b>dovira.vet</b>', $body['text'] );

						return true;
					}
				)
			)
			->andReturn( $this->fixture( 'send-message-success.written.json' ) );

		Functions\expect( 'add_settings_error' )
			->once()
			->with(
				'gatb_settings',
				'gatb_check_telegram',
				\Mockery::on(
					function ( string $message ): bool {
						$this->assertStringContainsString( self::CHAT_ID, $message );

						return true;
					}
				),
				'success'
			);

		$this->run_handler();
	}

	/**
	 * A refused send is reported as an error, in the client's words.
	 */
	public function test_a_failing_check_reports_the_mapped_reason(): void {
		$this->given_a_configured_site();
		Functions\when( 'wp_remote_post' )->justReturn( $this->fixture( 'error-chat-not-found.written.json' ) );

		Functions\expect( 'add_settings_error' )
			->once()
			->with(
				'gatb_settings',
				'gatb_check_telegram',
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
	}

	/**
	 * An install with no chat id is told so, without a request going anywhere.
	 */
	public function test_an_unconfigured_site_is_told_before_anything_is_sent(): void {
		Functions\when( 'get_option' )->justReturn( array( 'telegram_bot_token' => self::TOKEN ) );
		Functions\when( 'check_admin_referer' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_settings_errors' )->justReturn( array() );
		Functions\when( 'set_transient' )->justReturn( true );
		$this->stub_redirect();
		Functions\expect( 'wp_remote_post' )->never();

		Functions\expect( 'add_settings_error' )
			->once()
			->with( 'gatb_settings', 'gatb_check_telegram', \Mockery::type( 'string' ), 'error' );

		$this->run_handler();
	}

	/**
	 * The outcome is handed to the redirect the way WordPress carries notices.
	 */
	public function test_the_outcome_survives_the_redirect_as_a_settings_error(): void {
		$this->given_a_configured_site( false );
		Functions\when( 'wp_remote_post' )->justReturn( $this->fixture( 'send-message-success.written.json' ) );
		Functions\when( 'add_settings_error' )->justReturn( null );
		Functions\when( 'get_settings_errors' )->justReturn( array( array( 'code' => 'gatb_check_telegram' ) ) );

		Functions\expect( 'set_transient' )
			->once()
			->with( 'settings_errors', array( array( 'code' => 'gatb_check_telegram' ) ), 30 );
		Functions\expect( 'wp_safe_redirect' )
			->once()
			->with( 'https://example.test/wp-admin/options-general.php?page=gatb-settings&settings-updated=true' )
			->andThrow( new \RuntimeException( self::REDIRECTED ) );

		$this->run_handler();
	}

	/**
	 * Negative check: without the capability nothing is sent to Telegram.
	 */
	public function test_a_user_without_the_capability_sends_nothing(): void {
		$this->given_a_configured_site();
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'wp_die' )->alias(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);
		Functions\expect( 'wp_remote_post' )->never();
		Functions\expect( 'add_settings_error' )->never();

		$this->expectException( \RuntimeException::class );

		Admin::handle_check_telegram();
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
		Functions\expect( 'wp_remote_post' )->never();
		Functions\expect( 'add_settings_error' )->never();

		$this->expectException( \RuntimeException::class );

		Admin::handle_check_telegram();
	}
}
