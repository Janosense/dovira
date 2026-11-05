<?php
/**
 * Tests for dovira\ContentMcp\Session: the MCP session of the current request.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\ContentMcp;

use dovira\ContentMcp\Session;
use dovira\Tests\TestCase;

require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/Server.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/Session.php';

/**
 * Only a request to the dovira server carries a session; everything else has none.
 */
final class SessionTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Session::reset();
	}

	public function test_the_header_of_a_request_to_the_dovira_server_is_kept_and_the_response_passes_through(): void {
		$response = new \stdClass();

		$this->assertSame( $response, Session::capture( $response, [], self::request( '/mcp/dovira', '3f2a9c1e-7b4d-4e0f-9a51-2c6d8e7f0a12' ) ) );
		$this->assertSame( '3f2a9c1e-7b4d-4e0f-9a51-2c6d8e7f0a12', Session::id() );
	}

	public function test_another_routes_header_is_ignored(): void {
		Session::capture( null, [], self::request( '/wp-abilities/v1/abilities/dovira/update-price-row/run', 'forged' ) );
		Session::capture( null, [], self::request( '/mcp/mcp-adapter-default-server', 'other' ) );

		$this->assertSame( '', Session::id() );
	}

	public function test_no_header_is_no_session(): void {
		Session::capture( null, [], self::request( '/mcp/dovira', null ) );

		$this->assertSame( '', Session::id() );
	}

	public function test_anything_but_an_id_is_stripped(): void {
		Session::capture( null, [], self::request( '/mcp/dovira', "abc-123\n<x>" ) );

		$this->assertSame( 'abc-123x', Session::id() );
	}

	private static function request( string $route, ?string $session ): \WP_REST_Request {
		$request = new \WP_REST_Request( 'POST', $route );
		if ( null !== $session ) {
			$request->set_header( 'Mcp-Session-Id', $session );
		}

		return $request;
	}
}
