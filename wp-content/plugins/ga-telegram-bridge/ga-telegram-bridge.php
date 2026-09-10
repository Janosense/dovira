<?php
/**
 * Plugin Name:       Google Analytics → Telegram bridge
 * Plugin URI:        https://dovira.vet
 * Description:       Sends a short daily Google Analytics 4 report to one Telegram chat.
 * Version:           0.1.0
 * Requires at least: 7.1
 * Requires PHP:      8.1
 * Author:            Dovira
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ga-telegram-bridge
 * Domain Path:       /languages
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GATB_PLUGIN_FILE', __FILE__ );

/**
 * Loads the plugin's classes from src/ without Composer: the plugin ships with
 * zero runtime dependencies (DECISIONS "Plugin structure, storage and secrets").
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = __NAMESPACE__ . '\\';

		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( $prefix ) );
		$path           = __DIR__ . '/src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Plugin::class, 'deactivate' ) );

Plugin::boot();
