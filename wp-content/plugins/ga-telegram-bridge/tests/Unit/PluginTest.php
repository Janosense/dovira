<?php
/**
 * Tests for the plugin bootstrap and its activation guard.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use DateTimeZone;
use GaTelegramBridge\Admin;
use GaTelegramBridge\Plugin;
use GaTelegramBridge\Scheduler;
use GaTelegramBridge\Settings;
use GaTelegramBridge\Tests\TestCase;

/**
 * Smoke test of the harness (autoloader + WordPress stubs), of the hooks the
 * plugin registers, and of the one rule the skeleton already carries: no
 * OpenSSL, no activation.
 */
final class PluginTest extends TestCase {

	/**
	 * Boot registers the textdomain loader on init.
	 */
	public function test_boot_registers_the_textdomain_loader_on_init(): void {
		Actions\expectAdded( 'init' )
			->once()
			->with( array( Plugin::class, 'load_textdomain' ) );

		Plugin::boot();

		$this->assertNotFalse( Actions\has( 'init', array( Plugin::class, 'load_textdomain' ) ) );
	}

	/**
	 * Boot registers the settings option for the admin.
	 */
	public function test_boot_registers_the_settings_on_admin_init(): void {
		Plugin::boot();

		$this->assertNotFalse( Actions\has( 'admin_init', array( Settings::class, 'register' ) ) );
	}

	/**
	 * Boot registers the settings screen for the admin.
	 */
	public function test_boot_registers_the_settings_screen(): void {
		Plugin::boot();

		$this->assertNotFalse( Actions\has( 'admin_menu', array( Admin::class, 'add_page' ) ) );
		$this->assertNotFalse( Actions\has( 'admin_init', array( Admin::class, 'add_fields' ) ) );
	}

	/**
	 * Boot registers the handler behind the "Check GA" button.
	 */
	public function test_boot_registers_the_check_ga_handler(): void {
		Plugin::boot();

		$this->assertNotFalse(
			Actions\has( 'admin_post_gatb_check_ga', array( Admin::class, 'handle_check_ga' ) )
		);
	}

	/**
	 * Boot registers the handler behind the "Check Telegram" button.
	 */
	public function test_boot_registers_the_check_telegram_handler(): void {
		Plugin::boot();

		$this->assertNotFalse(
			Actions\has( 'admin_post_gatb_check_telegram', array( Admin::class, 'handle_check_telegram' ) )
		);
	}

	/**
	 * Boot registers the handler behind the "Preview" button.
	 */
	public function test_boot_registers_the_preview_handler(): void {
		Plugin::boot();

		$this->assertNotFalse(
			Actions\has( 'admin_post_gatb_preview', array( Admin::class, 'handle_preview' ) )
		);
	}

	/**
	 * Boot registers the handler behind the "Send now" button.
	 */
	public function test_boot_registers_the_send_now_handler(): void {
		Plugin::boot();

		$this->assertNotFalse(
			Actions\has( 'admin_post_gatb_send_now', array( Admin::class, 'handle_send_now' ) )
		);
	}

	/**
	 * Boot registers the reader that takes a preview out of the notices.
	 */
	public function test_boot_registers_the_preview_reader(): void {
		Plugin::boot();

		$this->assertNotFalse(
			Actions\has( 'all_admin_notices', array( Admin::class, 'take_preview' ) )
		);
	}

	/**
	 * Boot registers the callback of the daily event.
	 */
	public function test_boot_registers_the_daily_report_callback(): void {
		Plugin::boot();

		$this->assertNotFalse(
			Actions\has( 'gatb_daily_report', array( Scheduler::class, 'run_daily' ) )
		);
	}

	/**
	 * Boot registers the callback of the retry event, with the one argument
	 * that carries the day it is for.
	 */
	public function test_boot_registers_the_retry_callback_with_its_argument(): void {
		Actions\expectAdded( 'gatb_retry_report' )
			->once()
			->with( array( Scheduler::class, 'run_retry' ), 10, 1 );

		Plugin::boot();

		$this->assertNotFalse(
			Actions\has( 'gatb_retry_report', array( Scheduler::class, 'run_retry' ) )
		);
	}

	/**
	 * Boot re-registers the event whenever the settings are saved: the send
	 * time is only worth changing if the schedule follows it.
	 */
	public function test_boot_reschedules_when_the_settings_are_saved(): void {
		Plugin::boot();

		$this->assertNotFalse(
			Actions\has( 'update_option_gatb_settings', array( Scheduler::class, 'reschedule' ) )
		);
	}

	/**
	 * And on the save of an option that does not exist yet in the eyes of core.
	 *
	 * While the stored settings are still exactly the registered defaults,
	 * update_option() hands the write to add_option(), which fires only its own
	 * action — so a fresh install saving for the first time would otherwise
	 * schedule nothing.
	 */
	public function test_boot_reschedules_when_the_settings_are_first_written(): void {
		Plugin::boot();

		$this->assertNotFalse(
			Actions\has( 'add_option_gatb_settings', array( Scheduler::class, 'reschedule' ) )
		);
	}

	/**
	 * Boot registers the reader that notices a visit to wp-cron.php.
	 */
	public function test_boot_registers_the_cron_hit_reader(): void {
		Plugin::boot();

		$this->assertNotFalse(
			Actions\has( 'wp_loaded', array( Scheduler::class, 'note_cron_hit' ) )
		);
	}

	/**
	 * Activation creates the settings option, creates it not autoloaded, and
	 * registers the daily event.
	 */
	public function test_activation_creates_the_settings_option_without_autoloading_it(): void {
		$this->stub_cron();
		Functions\expect( 'add_option' )
			->once()
			->with( 'gatb_settings', Settings::defaults(), '', false );
		Functions\expect( 'wp_schedule_event' )
			->once()
			->with( \Mockery::type( 'int' ), 'daily', 'gatb_daily_report' );

		Plugin::activate();

		$this->assertSame( 'gatb_settings', Settings::OPTION );
	}

	/**
	 * Deactivation takes the schedule away and leaves everything else: the
	 * settings, the log and the state survive being switched off.
	 */
	public function test_deactivation_clears_the_schedule_and_nothing_else(): void {
		$cleared = array();
		Functions\when( 'wp_unschedule_hook' )->alias(
			function ( string $hook ) use ( &$cleared ): int {
				$cleared[] = $hook;

				return 1;
			}
		);
		Functions\expect( 'delete_option' )->never();

		Plugin::deactivate();

		$this->assertSame( array( 'gatb_daily_report', 'gatb_retry_report' ), $cleared );
	}

	/**
	 * Stubs what registering an event needs.
	 */
	private function stub_cron(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'wp_clear_scheduled_hook' )->justReturn( 1 );
		Functions\when( 'wp_timezone' )->alias(
			static fn (): DateTimeZone => new DateTimeZone( 'Europe/Kiev' )
		);
	}

	/**
	 * The version comes out of the plugin header, and from nowhere else.
	 *
	 * The stub is the point of the test: a number written into the markup would
	 * pass an assertion on the rendered screen just as well, and would then be
	 * free to disagree with the header a host reads.
	 */
	public function test_the_version_comes_from_the_plugin_header(): void {
		Functions\expect( 'get_file_data' )
			->once()
			->with( GATB_PLUGIN_FILE, array( 'Version' => 'Version' ) )
			->andReturn( array( 'Version' => '9.9.9' ) );

		$this->assertSame( '9.9.9', Plugin::version() );
	}

	/**
	 * The header and readme.txt name the same version.
	 *
	 * Both files are read as they are shipped: the plugin header is what
	 * WordPress shows on the Plugins screen and what the settings screen prints,
	 * `Stable tag` is what a reader of the readme believes. A release that bumps
	 * one and forgets the other fails here instead of on somebody's site.
	 */
	public function test_the_header_and_the_readme_agree_on_the_version(): void {
		$header = $this->first_match( GATB_PLUGIN_FILE, '/^ \* Version:\s+(\S+)/m' );
		$stable = $this->first_match( dirname( GATB_PLUGIN_FILE ) . '/readme.txt', '/^Stable tag:\s+(\S+)/m' );

		$this->assertNotSame( '', $header, 'the plugin header declares a version' );
		$this->assertSame(
			$header,
			$stable,
			'the plugin header and readme.txt must name the same version'
		);
	}

	/**
	 * Reads one shipped file and returns the first group its pattern captures.
	 *
	 * @param string $file    The file to read, as it is shipped.
	 * @param string $pattern The pattern whose first group is wanted.
	 */
	private function first_match( string $file, string $pattern ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a shipped file of this plugin, and unit tests have no WP_Filesystem.
		$contents = (string) file_get_contents( $file );

		$this->assertSame( 1, preg_match( $pattern, $contents, $matches ), 'no line matched ' . $pattern );

		return isset( $matches[1] ) ? $matches[1] : '';
	}

	/**
	 * With OpenSSL present there is no reason to block activation.
	 */
	public function test_activation_is_not_blocked_when_openssl_is_available(): void {
		$this->assertNull( Plugin::activation_blocked_message( true ) );
	}

	/**
	 * Without OpenSSL the guard returns a translated, readable reason.
	 */
	public function test_activation_is_blocked_without_openssl(): void {
		Functions\when( '__' )->returnArg();

		$message = Plugin::activation_blocked_message( false );

		$this->assertIsString( $message );
		$this->assertStringContainsString( 'OpenSSL', $message );
	}
}
