<?php
/**
 * Feature content-mcp: the MCP connector through which the clinic's people
 * manage the site's content from Claude. See docs/features/content-mcp/FEATURE.md.
 */

use dovira\ContentMcp\Abilities;
use dovira\ContentMcp\Dump;
use dovira\ContentMcp\Server;
use dovira\ContentMcp\Session;

// Without the core Abilities API or the mcp-adapter plugin the feature stays
// off and the site loads as before.
if ( ! function_exists( 'wp_register_ability' ) || ! class_exists( 'WP\MCP\Core\McpAdapter' ) ) {
	return;
}

require_once __DIR__ . '/Catalogue.php';
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/Confirmation.php';
require_once __DIR__ . '/Diff.php';
require_once __DIR__ . '/Dump.php';
require_once __DIR__ . '/Scope.php';
require_once __DIR__ . '/Schema.php';
require_once __DIR__ . '/FieldValues.php';
require_once __DIR__ . '/FieldInput.php';
require_once __DIR__ . '/CityPrice.php';
require_once __DIR__ . '/PostReader.php';
require_once __DIR__ . '/PostWriter.php';
require_once __DIR__ . '/ReadTools.php';
require_once __DIR__ . '/DescribeSite.php';
require_once __DIR__ . '/ListPosts.php';
require_once __DIR__ . '/GetPost.php';
require_once __DIR__ . '/PriceRows.php';
require_once __DIR__ . '/FindPriceRows.php';
require_once __DIR__ . '/ListTerms.php';
require_once __DIR__ . '/ListMedia.php';
require_once __DIR__ . '/WriteAbility.php';
require_once __DIR__ . '/UpdatePriceRow.php';
require_once __DIR__ . '/UpdatePost.php';
require_once __DIR__ . '/CreatePost.php';
require_once __DIR__ . '/Translator.php';
require_once __DIR__ . '/CreateTranslation.php';
require_once __DIR__ . '/MediaWriter.php';
require_once __DIR__ . '/UploadMedia.php';
require_once __DIR__ . '/TrashPost.php';
require_once __DIR__ . '/TermWriter.php';
require_once __DIR__ . '/CreateTerm.php';
require_once __DIR__ . '/UpdateTerm.php';
require_once __DIR__ . '/Abilities.php';
require_once __DIR__ . '/Server.php';

add_action( 'wp_abilities_api_categories_init', [ Abilities::class, 'register_category' ] );
add_action( 'wp_abilities_api_init', [ Abilities::class, 'register' ] );
add_action( 'mcp_adapter_init', [ Server::class, 'register' ] );
add_filter( 'rest_request_before_callbacks', [ Session::class, 'capture' ], 10, 3 );
add_action( 'init', [ Dump::class, 'schedule' ] );
add_action( Dump::HOOK, [ Dump::class, 'retain' ] );
