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
	public const INSTRUCTIONS = 'Dovira veterinary clinic site (one city install). Call dovira-describe-site first in every conversation. Every write is previewed first: call the write tool without "confirmation", show the person the returned diff, and call it again with the returned confirmation only after they agree. Ukrainian is the source language; after changing a Ukrainian post, offer the same change for its Russian copy under the same rule.';

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
