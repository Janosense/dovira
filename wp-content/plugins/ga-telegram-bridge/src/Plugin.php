<?php
/**
 * Plugin bootstrap and activation requirements.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge;

/**
 * Registers the plugin's hooks and guards what it needs to run.
 */
final class Plugin {

	/**
	 * Registers the hooks the plugin needs on every request.
	 */
	public static function boot(): void {
		add_action( 'init', array( self::class, 'load_textdomain' ) );
	}

	/**
	 * Loads the translations shipped in languages/.
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain(
			'ga-telegram-bridge',
			false,
			dirname( plugin_basename( GATB_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Activation callback: refuses activation when a requirement is missing.
	 *
	 * WordPress passes $network_wide to activation callbacks; the plugin has no
	 * network behaviour and ignores it.
	 */
	public static function activate(): void {
		$message = self::activation_blocked_message( extension_loaded( 'openssl' ) );

		if ( null === $message ) {
			return;
		}

		deactivate_plugins( plugin_basename( GATB_PLUGIN_FILE ) );

		wp_die(
			esc_html( $message ),
			'',
			array( 'back_link' => true )
		);
	}

	/**
	 * Returns the reason the plugin must not activate, or null when it may.
	 *
	 * Kept free of WordPress state so the guard is testable on a host that has
	 * the extension: OpenSSL signs the Google service-account JWT.
	 *
	 * @param bool $has_openssl Whether the openssl extension is loaded.
	 */
	public static function activation_blocked_message( bool $has_openssl ): ?string {
		if ( $has_openssl ) {
			return null;
		}

		return __(
			'Google Analytics → Telegram bridge needs the PHP OpenSSL extension to sign Google API requests. Ask your host to enable it, then activate the plugin again.',
			'ga-telegram-bridge'
		);
	}
}
