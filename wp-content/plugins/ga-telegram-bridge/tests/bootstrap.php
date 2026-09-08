<?php
/**
 * PHPUnit bootstrap: Composer autoloading plus the constants the plugin file
 * defines at runtime. The plugin file itself is never loaded here — its
 * ABSPATH guard would exit.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'GATB_PLUGIN_FILE' ) ) {
	define( 'GATB_PLUGIN_FILE', dirname( __DIR__ ) . '/ga-telegram-bridge.php' );
}
