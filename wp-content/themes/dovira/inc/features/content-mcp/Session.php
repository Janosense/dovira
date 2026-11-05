<?php

namespace dovira\ContentMcp;

/**
 * The MCP session of the current request: the `Mcp-Session-Id` header of a
 * request to the `dovira` server.
 *
 * The adapter (0.7.1) gives an ability only its input, never the session, so
 * the header is taken from the REST request of `/mcp/dovira`, where the
 * adapter has already checked it against the user's own sessions (protocol
 * 2025-11-25 and the legacy revisions it maps there). Every other request —
 * REST `wp-abilities/v1`, WP-CLI, the sessionless revision 2026-07-28 — has
 * no session: `''`.
 */
final class Session {

	private static string $id = '';

	/**
	 * On `rest_request_before_callbacks`: keeps the session id of a request to
	 * the `dovira` server. The response passes through unchanged.
	 *
	 * @param mixed  $response
	 * @param mixed  $handler
	 * @param object $request `WP_REST_Request`.
	 * @return mixed
	 */
	public static function capture( $response, $handler, $request ) {
		if ( is_object( $request ) && method_exists( $request, 'get_route' ) && self::route() === rtrim( (string) $request->get_route(), '/' ) ) {
			$header   = (string) $request->get_header( 'mcp-session-id' );
			self::$id = (string) preg_replace( '/[^A-Za-z0-9\-]/', '', substr( $header, 0, 128 ) );
		}

		return $response;
	}

	public static function id(): string {
		return self::$id;
	}

	/**
	 * Forgets the session (tests; a new request starts without one anyway).
	 */
	public static function reset(): void {
		self::$id = '';
	}

	private static function route(): string {
		return '/' . Server::ROUTE_NAMESPACE . '/' . Server::ROUTE;
	}
}
