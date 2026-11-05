<?php

namespace dovira\ContentMcp;

/**
 * The `dovira` MCP server at `/wp-json/mcp/dovira`, created on
 * `mcp_adapter_init` through the adapter's `create_server()`.
 */
final class Server {

	public const ID = 'dovira';
	public const ROUTE_NAMESPACE = 'mcp';
	public const ROUTE = 'dovira';
	public const VERSION = '0.1.0';

	/**
	 * What the agent is told at `initialize`. The adapter has no instructions
	 * argument: it sends the server description as `instructions`
	 * (FEATURE.md → Interfaces).
	 */
	public const INSTRUCTIONS = 'Dovira veterinary clinic site (one city install). Call dovira-describe-site first in every conversation. To change a price, find the row with dovira-find-price-rows, then change it with dovira-update-price-row; prices are never changed through dovira-update-post. To create a post, call dovira-create-post in exactly the status the person names (never assume one); a new category or tag is created first with dovira-create-term (dovira-update-term edits one), then named in the post. A Ukrainian post without a Russian copy gets one with dovira-create-translation, only when the person asks for it: you write the Russian title and every text (ACF text fields, block fields, price-row titles and descriptions); whatever you leave out is carried over in Ukrainian, prices always are; an existing Russian copy is changed with dovira-update-post. To edit a post, open it with dovira-get-post and call dovira-update-post with only what changes (in an acf/* block, name only the fields that change; the others stay; a repeater value replaces the whole repeater). Every write is previewed first: call the write tool without "confirmation", show the person the returned diff and warnings, and call it again with the same arguments and the returned confirmation only after they agree. If the person changes anything (another amount, another wording), call without confirmation again for a new preview; a confirmation is valid once, for exactly the previewed change, for 15 minutes. Ukrainian is the source language: a price is changed in the Ukrainian copy and follows into the Russian copy by itself; a post\'s Russian copy changes only when the person agrees to it, passed as "translation" with its own preview. Deleting is dovira-trash-post: administrators only, to the WordPress trash, never permanently.';

	private const TRANSPORT = 'WP\MCP\Transport\HttpTransport';
	private const ERROR_HANDLER = 'WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler';

	/**
	 * Creates the server on the adapter instance `mcp_adapter_init` passes.
	 *
	 * The transport permission is left null, so the adapter's own gate decides:
	 * a logged-in user with `read` (application password over Basic auth).
	 *
	 * @param object $adapter `WP\MCP\Core\McpAdapter`.
	 */
	public static function register( object $adapter ): void {
		$result = $adapter->create_server(
			self::ID,
			self::ROUTE_NAMESPACE,
			self::ROUTE,
			'Dovira',
			self::INSTRUCTIONS,
			self::VERSION,
			[ self::TRANSPORT ],
			self::ERROR_HANDLER,
			null,
			Catalogue::TOOLS,
			[],
			[],
			null
		);

		if ( is_wp_error( $result ) ) {
			error_log( 'dovira content-mcp: the MCP server was not created: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
