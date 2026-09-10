<?php
/**
 * What is left of the plugin after it is deleted: nothing.
 *
 * WordPress includes this file when an administrator deletes the plugin, with
 * the plugin itself unloaded — there is no autoloader here and no class to ask
 * for an option name, so every name is written out. `UninstallTest` holds each
 * of them against the constant it has to equal, so a renamed option cannot slip
 * past the gate.
 *
 * Deactivating the plugin is a different thing entirely and takes only the
 * schedule (`Plugin::deactivate()`): an administrator who switches the plugin
 * off does not lose the credentials they typed in.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// The three rows of docs/DATA-MODEL.md → "Plugin ga-telegram-bridge": what the
// administrator configured, what the plugin remembers about the days it has
// sent, and the log of its last runs.
delete_option( 'gatb_settings' );
delete_option( 'gatb_state' );
delete_option( 'gatb_log' );

// The cached Google access token. Google issues it for an hour, so this is
// housekeeping rather than a secret being removed — but it is the plugin's row
// and it goes with the rest.
delete_transient( 'gatb_google_access_token' );

// Both events, by hook rather than by hook and arguments: the retry carries the
// day it was booked for, and wp_clear_scheduled_hook() called with no arguments
// unschedules only the events that have none (wp-includes/cron.php).
wp_unschedule_hook( 'gatb_daily_report' );
wp_unschedule_hook( 'gatb_retry_report' );
