<?php
/**
 * Feature content-mcp: the MCP connector through which the clinic's people
 * manage the site's content from Claude. See docs/features/content-mcp/FEATURE.md.
 */

use dovira\ContentMcp\Abilities;
use dovira\ContentMcp\Server;

// Without the core Abilities API or the mcp-adapter plugin the feature stays
// off and the site loads as before.
if ( ! function_exists( 'wp_register_ability' ) || ! class_exists( 'WP\MCP\Core\McpAdapter' ) ) {
	return;
}

require_once __DIR__ . '/Catalogue.php';
require_once __DIR__ . '/Scope.php';
require_once __DIR__ . '/Schema.php';
require_once __DIR__ . '/DescribeSite.php';
require_once __DIR__ . '/Abilities.php';
require_once __DIR__ . '/Server.php';

add_action( 'wp_abilities_api_categories_init', [ Abilities::class, 'register_category' ] );
add_action( 'wp_abilities_api_init', [ Abilities::class, 'register' ] );
add_action( 'mcp_adapter_init', [ Server::class, 'register' ] );
