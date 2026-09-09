<?php
/**
 * Tests for the "Preview" button: what it builds, what it shows, and the one
 * thing it must never do.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge\Tests\Unit;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use GaTelegramBridge\Admin;
use GaTelegramBridge\Tests\TestCase;

/**
 * The handler is exercised end to end apart from the network and the redirect.
 */
final class AdminPreviewTest extends TestCase {

	/**
	 * What the stubbed redirect throws, so the handler stops where wp-admin
	 * would leave the request.
	 */
	private const REDIRECTED = 'gatb-redirected';

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
		Functions\when( 'wp_timezone_string' )->justReturn( 'Europe/Kyiv' );
		Functions\when( 'get_transient' )->justReturn( 'a-cached-access-token' );
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
	 * Configures a site whose property answers with the recorded day.
	 */
	private function given_a_configured_property(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'property_id' => '533779496',
				'blocks'      => array(
					'visitors' => true,
					'pages'    => true,
					'channels' => true,
					'cities'   => true,
					'devices'  => true,
				),
			)
		);
		Functions\when( 'check_admin_referer' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_settings_errors' )->justReturn( array() );
		Functions\when( 'set_transient' )->justReturn( true );
		$this->stub_redirect();

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
			Admin::handle_preview();
		} catch ( \RuntimeException $stopped ) {
			if ( self::REDIRECTED !== $stopped->getMessage() ) {
				throw $stopped;
			}

			$this->assertSame( self::REDIRECTED, $stopped->getMessage() );
		}
	}

	/**
	 * The section offers the preview as a third form with its own nonce.
	 */
	public function test_the_section_offers_a_preview_button_with_its_own_nonce(): void {
		Functions\expect( 'wp_nonce_field' )->once()->with( 'gatb_check_ga' );
		Functions\expect( 'wp_nonce_field' )->once()->with( 'gatb_check_telegram' );
		Functions\expect( 'wp_nonce_field' )->once()->with( 'gatb_preview' );
		Functions\when( 'submit_button' )->alias(
			static function ( string $text ): void {
				echo '<button>' . esc_html( $text ) . '</button>';
			}
		);

		ob_start();
		Admin::render_connection_section();
		$markup = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="action" value="gatb_preview"', $markup );
		$this->assertStringContainsString( '<button>Preview</button>', $markup );
		$this->assertStringContainsString( 'Sends nothing', $markup, 'the button says what it does not do' );
	}

	/**
	 * The preview is the real message, built from the real property.
	 */
	public function test_the_preview_holds_the_message_the_report_would_send(): void {
		$this->given_a_configured_property();

		Functions\expect( 'add_settings_error' )
			->once()
			->with(
				'gatb_settings',
				'gatb_preview',
				\Mockery::on(
					function ( string $message ): bool {
						// The handler reads the real clock, so the day is
						// whatever today's yesterday is; what matters here is
						// that the whole message arrived, not which day it is.
						$this->assertStringStartsWith( '📊 <b>dovira.vet — ', $message );
						$this->assertStringContainsString( 'Yesterday: 69 (▲ 23% to the 7-day average)', $message );
						$this->assertStringContainsString( '<b>Cities over 28 days</b>', $message );

						return true;
					}
				),
				'info'
			);

		$this->run_handler();
	}

	/**
	 * Negative check: previewing sends nothing to Telegram.
	 */
	public function test_previewing_posts_nothing_to_telegram(): void {
		$this->given_a_configured_property();
		Functions\when( 'add_settings_error' )->justReturn( null );

		$posted = array();

		Functions\when( 'wp_remote_post' )->alias(
			function ( string $url ) use ( &$posted ): array {
				$posted[] = $url;

				return $this->fixture( 'batch-run-reports-daily-call-1.json' );
			}
		);

		try {
			Admin::handle_preview();
		} catch ( \RuntimeException $stopped ) {
			$this->assertSame( self::REDIRECTED, $stopped->getMessage() );
		}

		$this->assertNotEmpty( $posted, 'Google was asked' );

		foreach ( $posted as $url ) {
			$this->assertStringNotContainsString( 'api.telegram.org', $url );
		}
	}

	/**
	 * A property that cannot be read is reported, not fatal.
	 */
	public function test_a_property_that_cannot_be_read_is_reported_as_an_error(): void {
		$this->given_a_configured_property();
		Functions\when( 'wp_remote_post' )->justReturn( $this->fixture( 'error-no-access-property.json' ) );

		Functions\expect( 'add_settings_error' )
			->once()
			->with(
				'gatb_settings',
				'gatb_preview_failed',
				\Mockery::type( 'string' ),
				'error'
			);

		$this->run_handler();
	}

	/**
	 * Negative check: without the capability nothing is read and nothing shown.
	 */
	public function test_a_user_without_the_capability_previews_nothing(): void {
		$this->given_a_configured_property();
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'wp_die' )->alias(
			static function (): void {
				throw new \RuntimeException( 'wp_die' );
			}
		);
		Functions\expect( 'wp_remote_post' )->never();
		Functions\expect( 'add_settings_error' )->never();

		$this->expectException( \RuntimeException::class );

		Admin::handle_preview();
	}

	/**
	 * Negative check: a request without a valid nonce previews nothing either.
	 */
	public function test_a_request_without_a_valid_nonce_previews_nothing(): void {
		$this->given_a_configured_property();
		Functions\when( 'check_admin_referer' )->alias(
			static function (): void {
				throw new \RuntimeException( 'wp_nonce_ays' );
			}
		);
		Functions\expect( 'wp_remote_post' )->never();
		Functions\expect( 'add_settings_error' )->never();

		$this->expectException( \RuntimeException::class );

		Admin::handle_preview();
	}

	/**
	 * The screen shows the message as text, and shows it exactly once.
	 *
	 * It is printed in a block of its own rather than inside a notice, so the
	 * entry is taken out of WordPress's notice list before the notices print.
	 */
	public function test_the_screen_prints_the_preview_as_text_and_not_as_a_notice(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- arranging the notice list wp-admin would hand the screen.
		$GLOBALS['wp_settings_errors'] = array(
			array(
				'setting' => 'gatb_settings',
				'code'    => 'gatb_preview',
				'message' => "📊 <b>dovira.vet — 9 September (Tuesday)</b>\n<b>Visitors</b>",
				'type'    => 'info',
			),
			array(
				'setting' => 'gatb_settings',
				'code'    => 'gatb_check_ga',
				'message' => 'Google answered for property 533779496.',
				'type'    => 'success',
			),
		);

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_settings_errors' )->justReturn( array() );
		Functions\when( 'wp_nonce_field' )->justReturn( null );
		Functions\when( 'settings_errors' )->justReturn( null );
		Functions\when( 'settings_fields' )->justReturn( null );
		Functions\when( 'do_settings_sections' )->justReturn( null );
		Functions\when( 'submit_button' )->justReturn( null );

		ob_start();
		Admin::render_page();
		$markup = (string) ob_get_clean();

		$this->assertStringContainsString( '<h2>Preview</h2>', $markup );
		$this->assertStringContainsString( '<pre>📊 &lt;b&gt;dovira.vet — 9 September (Tuesday)&lt;/b&gt;', $markup );
		$this->assertSame(
			array( 'gatb_check_ga' ),
			array_column( (array) $GLOBALS['wp_settings_errors'], 'code' ),
			'the preview is gone from the notices, the check result is not'
		);

		unset( $GLOBALS['wp_settings_errors'] );
	}

	/**
	 * With no preview in the notices the screen prints no preview block.
	 */
	public function test_the_screen_prints_no_preview_when_none_was_built(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- arranging the notice list wp-admin would hand the screen.
		$GLOBALS['wp_settings_errors'] = array();

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_settings_errors' )->justReturn( array() );
		Functions\when( 'wp_nonce_field' )->justReturn( null );
		Functions\when( 'settings_errors' )->justReturn( null );
		Functions\when( 'settings_fields' )->justReturn( null );
		Functions\when( 'do_settings_sections' )->justReturn( null );
		Functions\when( 'submit_button' )->justReturn( null );

		ob_start();
		Admin::render_page();
		$markup = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'Preview</h2>', $markup );
		$this->assertStringNotContainsString( '<pre>', $markup );

		unset( $GLOBALS['wp_settings_errors'] );
	}
}
