<?php

require_once TEMPLATE_DIR . '/inc/utils/conversation.php';
require_once TEMPLATE_DIR . '/inc/utils/application.php';
require_once TEMPLATE_DIR . '/inc/utils/questionary.php';
require_once TEMPLATE_DIR . '/inc/utils/polylang-string-translations.php';

/**
 * Vite manifest parser which returns a path to the entry file
 *
 * @param string $entry
 *
 * @return string
 * @throws JsonException
 */
function starter_theme_vite_asset( string $entry ): string {
	static $manifest;
	static $manifest_path;

	if ( ! $manifest_path ) {
		$manifest_path = get_theme_file_path( '/assets/.vite/manifest.json' );
	}

	if ( ! file_exists( $manifest_path ) ) {
		show_error( sprintf( __( 'Error locating <code>%s</code>.' ), get_theme_file_path( '/assets/manifest.json' ) ), 'File not found' );
	}

	if ( ! $manifest ) {
		// @codingStandardsIgnoreLine
		$manifest = json_decode( file_get_contents( $manifest_path ), true, 512, JSON_THROW_ON_ERROR );
	}

	if ( strpos( $entry, '.css' ) > 0 ) {
		$entry = str_replace( '.css', '.js', $entry );
		if ( isset( $manifest[ $entry ] ) && isset( $manifest[ $entry ]['css'] ) ) {
			return TEMPLATE_DIR_URI . '/assets/' . $manifest[ $entry ]['css'][0];
		}
	}

	return isset( $manifest[ $entry ] )
		? TEMPLATE_DIR_URI . '/assets/' . $manifest[ $entry ]['file']
		: TEMPLATE_DIR_URI . '/assets/' . $entry;
}

/**
 * Renders the error
 *
 * @param $message
 * @param string $subtitle
 * @param string $title
 *
 * @return void
 */
function show_error( $message, string $subtitle = '', string $title = '' ): void {
	$title   = $title ?: __( 'Error' );
	$message = "<h1>{$title}<br><small>{$subtitle}</small></h1><p>{$message}</p>";

	if ( ! is_admin() ) {
		wp_die( wp_kses_post( $message ), wp_kses_post( $title ) );
	}
}

/**
 * Check if the current page is the login page
 *
 * @return bool
 */
function starter_theme_is_login_page(): bool {
	return in_array(
		$GLOBALS['pagenow'],
		array( 'wp-login.php', 'wp-register.php' ),
		true
	);
}
