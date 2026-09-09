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
use GaTelegramBridge\Plugin;
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
	 * Activation creates the settings option, and creates it not autoloaded.
	 */
	public function test_activation_creates_the_settings_option_without_autoloading_it(): void {
		Functions\expect( 'add_option' )
			->once()
			->with( 'gatb_settings', Settings::defaults(), '', false );

		Plugin::activate();

		$this->assertSame( 'gatb_settings', Settings::OPTION );
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
