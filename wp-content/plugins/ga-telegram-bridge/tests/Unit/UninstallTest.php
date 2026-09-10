<?php
/**
 * Tests for what deleting the plugin takes with it — and what it leaves alone.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge\Tests\Unit;

use Brain\Monkey\Functions;
use GaTelegramBridge\GoogleAuth;
use GaTelegramBridge\RunLog;
use GaTelegramBridge\Scheduler;
use GaTelegramBridge\Settings;
use GaTelegramBridge\Tests\TestCase;

/**
 * `uninstall.php` is run here the way core runs it: `WP_UNINSTALL_PLUGIN` is
 * defined and the file is included, with nothing of the plugin loaded
 * (`wp-admin/includes/plugin.php` → `uninstall_plugin()`). That is why the file
 * spells its names out instead of reading the class constants — and why every
 * assertion below names the constant the literal has to equal, so renaming an
 * option without touching `uninstall.php` fails the gate.
 *
 * The `exit` that guards the file against being requested directly is not
 * asserted here: an `exit` inside the suite would end the run. The manual
 * verification guide checks it over HTTP instead.
 */
final class UninstallTest extends TestCase {

	/**
	 * Every option deleted during one test, in order.
	 *
	 * @var list<string>
	 */
	private array $deleted_options = array();

	/**
	 * Every transient deleted during one test, in order.
	 *
	 * @var list<string>
	 */
	private array $deleted_transients = array();

	/**
	 * Every hook unscheduled during one test, in order.
	 *
	 * @var list<string>
	 */
	private array $unscheduled = array();

	/**
	 * Records what the file does instead of letting it reach a database.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->deleted_options    = array();
		$this->deleted_transients = array();
		$this->unscheduled        = array();

		Functions\when( 'delete_option' )->alias(
			function ( string $option ): bool {
				$this->deleted_options[] = $option;

				return true;
			}
		);
		Functions\when( 'delete_transient' )->alias(
			function ( string $transient ): bool {
				$this->deleted_transients[] = $transient;

				return true;
			}
		);
		Functions\when( 'wp_unschedule_hook' )->alias(
			function ( string $hook ): int {
				$this->unscheduled[] = $hook;

				return 1;
			}
		);
	}

	/**
	 * Runs the file the way WordPress runs it.
	 *
	 * It declares no function and no class, so including it once per test is
	 * safe — which is also what makes each test a full run of the real file
	 * rather than of a copy of its list.
	 */
	private function uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- core's own constant, defined here because core defines it before including the file.
			define( 'WP_UNINSTALL_PLUGIN', 'ga-telegram-bridge/ga-telegram-bridge.php' );
		}

		include dirname( __DIR__, 2 ) . '/uninstall.php';
	}

	/**
	 * The three options the plugin owns are deleted.
	 */
	public function test_every_option_the_plugin_owns_is_deleted(): void {
		$this->uninstall();

		$this->assertEqualsCanonicalizing(
			array( Settings::OPTION, RunLog::STATE_OPTION, RunLog::LOG_OPTION ),
			$this->deleted_options
		);
	}

	/**
	 * The cached Google access token goes too.
	 */
	public function test_the_cached_access_token_is_deleted(): void {
		$this->uninstall();

		$this->assertSame( array( GoogleAuth::TRANSIENT ), $this->deleted_transients );
	}

	/**
	 * Both cron events are unscheduled — by hook, so the retry goes with the
	 * day it carries.
	 */
	public function test_both_cron_events_are_unscheduled(): void {
		$this->uninstall();

		$this->assertEqualsCanonicalizing(
			array( Scheduler::DAILY_HOOK, Scheduler::RETRY_HOOK ),
			$this->unscheduled
		);
	}

	/**
	 * Negative check: nothing that is not the plugin's is touched.
	 *
	 * The dangerous mistake here is a list that grows: `settings_errors` is
	 * WordPress's own transient — the screen writes it exactly as
	 * `wp-admin/options.php` does, and it expires in 30 seconds — and every
	 * other row in `wp_options` belongs to the site or to another plugin.
	 */
	public function test_nothing_that_is_not_the_plugins_is_deleted(): void {
		$this->uninstall();

		$this->assertCount( 3, $this->deleted_options, 'three options, and no fourth' );
		$this->assertCount( 1, $this->deleted_transients );
		$this->assertCount( 2, $this->unscheduled );
		$this->assertNotContains( 'settings_errors', $this->deleted_transients );

		foreach ( $this->deleted_options as $option ) {
			$this->assertStringStartsWith( 'gatb_', $option );
		}
	}

	/**
	 * Negative check: the function that would walk past an event carrying
	 * arguments is not the one used.
	 */
	public function test_the_hooks_are_not_cleared_by_arguments(): void {
		$cleared = array();

		Functions\when( 'wp_clear_scheduled_hook' )->alias(
			static function ( string $hook ) use ( &$cleared ): int {
				$cleared[] = $hook;

				return 1;
			}
		);

		$this->uninstall();

		$this->assertSame( array(), $cleared, 'wp_clear_scheduled_hook() would walk past the retry' );
		$this->assertCount( 2, $this->unscheduled, 'both hooks went the other way' );
	}
}
