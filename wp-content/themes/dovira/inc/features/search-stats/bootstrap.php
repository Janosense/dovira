<?php
/**
 * Feature search-stats: records what visitors search for on the site.
 * See docs/features/search-stats/FEATURE.md.
 */

use dovira\SearchStats\Purge;
use dovira\SearchStats\Renderer;
use dovira\SearchStats\Schema;

require_once __DIR__ . '/Schema.php';
require_once __DIR__ . '/Purge.php';
require_once __DIR__ . '/Normalizer.php';
require_once __DIR__ . '/Repository.php';
// Called by search.php, which prints the count for the browser module.
require_once __DIR__ . '/ResultsCount.php';
// Registered in inc/rest-api.php with the theme's other controllers.
require_once __DIR__ . '/RecordController.php';
// The daily report's reading side, used by the gatb_extra_blocks hook below.
require_once __DIR__ . '/Periods.php';
require_once __DIR__ . '/TopQuery.php';
require_once __DIR__ . '/SearchStats.php';
require_once __DIR__ . '/Stats.php';

add_action( 'after_switch_theme', [ Schema::class, 'install' ] );
add_action( 'init', [ Schema::class, 'maybe_upgrade' ] );

add_action( 'init', [ Purge::class, 'schedule' ] );
// No arguments: do_action() without any passes '', which would reach Purge::run( ?int ).
add_action( Purge::HOOK, [ Purge::class, 'run' ], 10, 0 );

// The search blocks of the daily report, only when the GA → Telegram plugin is
// loaded (plugins load before the theme): without it, nothing more is loaded or hooked.
if ( class_exists( \GaTelegramBridge\Plugin::class ) ) {
	require_once __DIR__ . '/Renderer.php';
	add_filter( 'gatb_extra_blocks', [ Renderer::class, 'add_to' ] );
}
