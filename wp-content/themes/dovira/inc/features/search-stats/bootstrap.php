<?php
/**
 * Feature search-stats: records what visitors search for on the site.
 * See docs/features/search-stats/FEATURE.md.
 */

use dovira\SearchStats\Schema;

require_once __DIR__ . '/Schema.php';

add_action( 'after_switch_theme', [ Schema::class, 'install' ] );
add_action( 'init', [ Schema::class, 'maybe_upgrade' ] );
