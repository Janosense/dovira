<?php
/**
 * PHPUnit bootstrap for the dovira theme: Composer autoloading only, which
 * also loads Brain\Monkey's API. WordPress is never loaded; every WordPress
 * function a test needs is stubbed with Brain\Monkey (docs/TESTING.md).
 */

declare( strict_types=1 );

require_once __DIR__ . '/vendor/autoload.php';
