<?php
/**
 * Feature search-stats: records what visitors search for on the site.
 * See docs/features/search-stats/FEATURE.md.
 */

use dovira\SearchStats\Purge;
use dovira\SearchStats\Schema;

require_once __DIR__ . '/Schema.php';
require_once __DIR__ . '/Purge.php';
require_once __DIR__ . '/Normalizer.php';
require_once __DIR__ . '/Repository.php';
// Called by search.php, which prints the count for the browser module.
require_once __DIR__ . '/ResultsCount.php';
// Registered in inc/rest-api.php with the theme's other controllers.
require_once __DIR__ . '/RecordController.php';

add_action( 'after_switch_theme', [ Schema::class, 'install' ] );
add_action( 'init', [ Schema::class, 'maybe_upgrade' ] );

add_action( 'init', [ Purge::class, 'schedule' ] );
// No arguments: do_action() without any passes '', which would reach Purge::run( ?int ).
add_action( Purge::HOOK, [ Purge::class, 'run' ], 10, 0 );
