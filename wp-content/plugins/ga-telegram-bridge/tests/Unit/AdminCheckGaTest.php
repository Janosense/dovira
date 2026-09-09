<?php
/**
 * Tests for the "Check GA" button: the form, and what the administrator is
 * told afterwards.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge\Tests\Unit;

use Brain\Monkey\Functions;
use GaTelegramBridge\Admin;
use GaTelegramBridge\Tests\TestCase;
use GaTelegramBridge\Tests\TestKey;

/**
 * The handler is exercised end to end apart from the network and the redirect.
 */
final class AdminCheckGaTest extends TestCase {

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
	 * Reads a recorded response and shapes it the way wp_remote_post returns.
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
	 * Configures a site that has everything the check needs.
	 *
	 * @param bool $stub_redirect Whether to stub the redirect too; a test that
	 *                            asserts the redirect sets its own expectation.
	 */
	private function given_a_configured_site( bool $stub_redirect = true ): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'property_id'          => '533779496',
				'service_account_json' => (string) wp_json_encode( TestKey::service_account() ),
			)
		);
		Functions\when( 'get_transient' )->justReturn( 'a-cached-access-token' );
		Functions\when( 'check_admin_referer' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_settings_errors' )->justReturn( array() );

		if ( $stub_redirect ) {
			$this->stub_redirect();
		}
	}

	/**
	 * Makes wp_safe_redirect() end the run, as it does in wp-admin.
	 *
	 * The handler exits after redirecting, and exit cannot be caught, so the
	 * redirect itself is what stops the handler here.
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
			Admin::handle_check_ga();
		} catch ( \RuntimeException $stopped ) {
			if ( self::REDIRECTED !== $stopped->getMessage() ) {
				throw $stopped;
			}

			$this->assertSame( self::REDIRECTED, $stopped->getMessage() );
		}
	}

	/**
	 * The button posts to admin-post.php, names its action and carries a nonce.
	 */
	public function test_the_button_posts_to_admin_post_with_a_nonce(): void {
		Functions\expect( 'wp_nonce_field' )->once()->with( 'gatb_check_ga' );
		Functions\when( 'submit_button' )->alias(
			static function ( string $text ): void {
				echo '<button>' . esc_html( $text ) . '</button>';
			}
		);

		ob_start();
		Admin::render_connection_section();
		$markup = (string) ob_get_clean();

		$this->assertStringContainsString( 'action="https://example.test/wp-admin/admin-post.php" method="post"', $markup );
		$this->assertStringContainsString( 'name="action" value="gatb_check_ga"', $markup );
		$this->assertStringContainsString( '<button>Check GA</button>', $markup );
	}

	/**
	 * A working connection is reported with the property and its time zone.
	 */
	public function test_a_successful_check_names_the_property_and_its_time_zone(): void {
		$this->given_a_configured_site();
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'wp_remote_post' )->justReturn( $this->fixture( 'run-report-active-users-yesterday.json' ) );

		Functions\expect( 'add_settings_error' )
			->once()
			->with(
				'gatb_settings',
				'gatb_check_ga',
				\Mockery::on(
					function ( string $message ): bool {
						$this->assertStringContainsString( '533779496', $message );
						$this->assertStringContainsString( 'Europe/Kiev', $message );

						return true;
					}
				),
				'success'
			);

		$this->run_handler();
	}

	/**
	 * A refused property is reported as an error, in the client's words.
	 */
	public function test_a_failing_check_reports_the_mapped_reason(): void {
		$this->given_a_configured_site();
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'wp_remote_post' )->justReturn( $this->fixture( 'error-no-access-property.json' ) );

		Functions\expect( 'add_settings_error' )
			->once()
			->with(
				'gatb_settings',
				'gatb_check_ga',
				\Mockery::on(
					function ( string $message ): bool {
						$this->assertStringContainsString( 'at least Viewer access', $message );
						$this->assertStringNotContainsString( 'PRIVATE KEY', $message );

						return true;
					}
				),
				'error'
			);

		$this->run_handler();
	}

	/**
	 * An install with no key is told so, without a request going anywhere.
	 */
	public function test_an_unconfigured_site_is_told_before_anything_is_sent(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'check_admin_referer' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_settings_errors' )->justReturn( array() );
		Functions\when( 'set_transient' )->justReturn( true );
		$this->stub_redirect();
		Functions\expect( 'wp_remote_post' )->never();

		Functions\expect( 'add_settings_error' )
			->once()
			->with( 'gatb_settings', 'gatb_check_ga', \Mockery::type( 'string' ), 'error' );

		$this->run_handler();
	}

	/**
	 * The outcome is handed to the redirect the way WordPress carries notices.
	 */
	public function test_the_outcome_survives_the_redirect_as_a_settings_error(): void {
		$this->given_a_configured_site( false );
		Functions\when( 'wp_remote_post' )->justReturn( $this->fixture( 'run-report-active-users-yesterday.json' ) );
		Functions\when( 'add_settings_error' )->justReturn( null );
		Functions\when( 'get_settings_errors' )->justReturn( array( array( 'code' => 'gatb_check_ga' ) ) );

		Functions\expect( 'set_transient' )
			->once()
			->with( 'settings_errors', array( array( 'code' => 'gatb_check_ga' ) ), 30 );
		Functions\expect( 'wp_safe_redirect' )
			->once()
			->with( 'https://example.test/wp-admin/options-general.php?page=gatb-settings&settings-updated=true' )
			->andThrow( new \RuntimeException( self::REDIRECTED ) );

		$this->run_handler();
	}

	/**
	 * Negative check: without the capability nothing is asked of Google.
	 */
	public function test_a_user_without_the_capability_gets_no_check(): void {
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

		Admin::handle_check_ga();
	}

	/**
	 * Negative check: a request without a valid nonce never reaches Google.
	 */
	public function test_a_request_without_a_valid_nonce_never_reaches_google(): void {
		$this->given_a_configured_site();
		Functions\when( 'check_admin_referer' )->alias(
			static function (): void {
				throw new \RuntimeException( 'wp_nonce_ays' );
			}
		);
		Functions\expect( 'wp_remote_post' )->never();
		Functions\expect( 'add_settings_error' )->never();

		$this->expectException( \RuntimeException::class );

		Admin::handle_check_ga();
	}
}
