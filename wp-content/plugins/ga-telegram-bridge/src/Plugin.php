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
		add_action( 'admin_init', array( Settings::class, 'register' ) );
		add_action( 'admin_menu', array( Admin::class, 'add_page' ) );
		add_action( 'admin_init', array( Admin::class, 'add_fields' ) );
		add_action( 'admin_post_' . Admin::CHECK_GA_ACTION, array( Admin::class, 'handle_check_ga' ) );
		add_action( 'admin_post_' . Admin::CHECK_TELEGRAM_ACTION, array( Admin::class, 'handle_check_telegram' ) );
		add_action( 'admin_post_' . Admin::PREVIEW_ACTION, array( Admin::class, 'handle_preview' ) );
		add_action( 'admin_post_' . Admin::SEND_NOW_ACTION, array( Admin::class, 'handle_send_now' ) );
		add_action( 'all_admin_notices', array( Admin::class, 'take_preview' ) );
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
	 * Activation callback: refuses activation when a requirement is missing and
	 * otherwise creates the settings option.
	 *
	 * WordPress passes $network_wide to activation callbacks; the plugin has no
	 * network behaviour and ignores it.
	 */
	public static function activate(): void {
		$message = self::activation_blocked_message( extension_loaded( 'openssl' ) );

		if ( null !== $message ) {
			deactivate_plugins( plugin_basename( GATB_PLUGIN_FILE ) );

			wp_die(
				esc_html( $message ),
				'',
				array( 'back_link' => true )
			);
		}

		// Created here so that it is never autoloaded: a settings save through
		// options.php would create it with the default autoload instead.
		add_option( Settings::OPTION, Settings::defaults(), '', false );
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
