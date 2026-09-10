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
		// The run log names the build it is running and links to the readme.
		Functions\when( 'get_file_data' )->justReturn( array( 'Version' => '0.1.0' ) );
		Functions\when( 'plugins_url' )->justReturn( 'https://dovira.vet/wp-content/plugins/ga-telegram-bridge/readme.txt' );
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
	 * The message is taken out of the notices and printed as text of its own.
	 *
	 * The settings errors of every screen under Settings are printed by
	 * wp-admin itself, before the screen callback runs, so the preview has to
	 * be taken out of that list earlier — on all_admin_notices — or the whole
	 * message appears as one bold paragraph above the form.
	 */
	public function test_the_preview_is_taken_out_of_the_notices_and_printed_as_text(): void {
		$this->given_the_notices_of_a_preview();
		$this->given_the_settings_screen();

		Admin::take_preview();

		$this->assertSame(
			array( 'gatb_check_ga' ),
			array_column( (array) $GLOBALS['wp_settings_errors'], 'code' ),
			'the preview is gone from the notices, the check result is not'
		);

		$markup = $this->render_screen();

		$this->assertStringContainsString( '<h2>Preview</h2>', $markup );
		$this->assertStringContainsString( '<pre>📊 &lt;b&gt;dovira.vet — 9 September (Tuesday)&lt;/b&gt;', $markup );

		$this->forget_the_notices();
	}

	/**
	 * Negative check: the screen never prints the notices a second time.
	 *
	 * They are printed by wp-admin itself (admin-header.php requires
	 * options-head.php for every screen whose parent is Settings), and a call
	 * here showed each of them twice.
	 */
	public function test_the_screen_does_not_print_the_notices_itself(): void {
		$this->given_the_notices_of_a_preview();
		$this->given_the_settings_screen();
		Functions\expect( 'settings_errors' )->never();

		Admin::take_preview();
		$markup = $this->render_screen();

		$this->assertStringNotContainsString( 'Google answered for property', $markup );

		$this->forget_the_notices();
	}

	/**
	 * Negative check: on any other admin screen nothing is taken and nothing
	 * is printed — the hook fires everywhere, the preview belongs to one page.
	 */
	public function test_another_admin_screen_keeps_its_notices(): void {
		$this->given_the_notices_of_a_preview();
		$this->given_the_settings_screen( 'edit-post' );

		Admin::take_preview();

		$this->assertSame(
			array( 'gatb_preview', 'gatb_check_ga' ),
			array_column( (array) $GLOBALS['wp_settings_errors'], 'code' ),
			'the notice list of another screen is left alone'
		);
		$this->assertStringNotContainsString( '<pre>', $this->render_screen() );

		$this->forget_the_notices();
	}

	/**
	 * With no preview in the notices the screen prints no preview block.
	 */
	public function test_the_screen_prints_no_preview_when_none_was_built(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- arranging the notice list wp-admin would hand the screen.
		$GLOBALS['wp_settings_errors'] = array();
		$this->given_the_settings_screen();

		Admin::take_preview();
		$markup = $this->render_screen();

		$this->assertStringNotContainsString( 'Preview</h2>', $markup );
		$this->assertStringNotContainsString( '<pre>', $markup );

		$this->forget_the_notices();
	}

	/**
	 * Arranges the notice list a preview leaves behind, next to a check result.
	 */
	private function given_the_notices_of_a_preview(): void {
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
	}

	/**
	 * Says which screen wp-admin is printing.
	 *
	 * @param string $id The screen id; the settings screen by default.
	 */
	private function given_the_settings_screen( string $id = 'settings_page_gatb-settings' ): void {
		Functions\when( 'get_current_screen' )->justReturn(
			new class( $id ) {
				/**
				 * The screen id wp-admin reports.
				 *
				 * @var string
				 */
				public $id;

				/**
				 * Holds one screen id.
				 *
				 * @param string $id The screen id.
				 */
				public function __construct( string $id ) {
					$this->id = $id;
				}
			}
		);
		Functions\when( 'get_settings_errors' )->justReturn( array() );
	}

	/**
	 * Renders the settings screen and returns its markup.
	 */
	private function render_screen(): string {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'wp_nonce_field' )->justReturn( null );
		Functions\when( 'settings_fields' )->justReturn( null );
		Functions\when( 'do_settings_sections' )->justReturn( null );
		Functions\when( 'submit_button' )->justReturn( null );

		ob_start();
		Admin::render_page();

		return (string) ob_get_clean();
	}

	/**
	 * Leaves no notices behind for the next test.
	 */
	private function forget_the_notices(): void {
		unset( $GLOBALS['wp_settings_errors'] );
	}
}
