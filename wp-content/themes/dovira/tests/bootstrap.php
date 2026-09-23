<?php
/**
 * PHPUnit bootstrap for the dovira theme: Composer autoloading, which also
 * loads Brain\Monkey's API. WordPress is never loaded; every WordPress
 * function a test needs is stubbed with Brain\Monkey (docs/TESTING.md).
 */

declare( strict_types=1 );

require_once __DIR__ . '/vendor/autoload.php';

// WordPress's own REST classes, from the core committed in this repository, so
// tests run the real request parsing. None of these files runs code when loaded.
foreach ( [
	'class-wp-error.php',
	'class-wp-http-response.php',
	'rest-api/class-wp-rest-response.php',
	'rest-api/class-wp-rest-request.php',
	'rest-api/endpoints/class-wp-rest-controller.php',
] as $dovira_core_file ) {
	require_once dirname( __DIR__, 4 ) . '/wp-includes/' . $dovira_core_file;
}

// WordPress's own value; its constants do not exist without WordPress.
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
