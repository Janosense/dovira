<?php
/**
 * Tests for dovira\ContentMcp\WriteAbility — the write protocol — and
 * dovira/update-price-row, the first ability behind it.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\ContentMcp;

use Brain\Monkey\Functions;
use dovira\ContentMcp\Diff;
use dovira\ContentMcp\Session;
use dovira\ContentMcp\UpdatePriceRow;
use dovira\ContentMcp\WriteAbility;
use dovira\Tests\TestCase;

foreach ( [ 'Catalogue', 'Server', 'Session', 'Confirmation', 'Diff', 'Dump', 'CityPrice', 'DescribeSite', 'PriceRows', 'WriteAbility', 'UpdatePriceRow' ] as $dovira_class ) {
	require_once dirname( __DIR__, 3 ) . "/inc/features/content-mcp/{$dovira_class}.php";
}

/**
 * No write without a preview's token for the same payload, user and session;
 * no write before the session's dump; a failed dump writes nothing.
 */
final class WriteAbilityTest extends TestCase {

	/** @var array<string, mixed> */
	private array $transients = [];

	/** @var list<string> What happened, in order. */
	private array $events = [];

	private int $user = 7;

	protected function setUp(): void {
		parent::setUp();
		Session::reset();
		Session::capture( null, null, self::session_request( 'session-a' ) );

		Functions\when( 'get_current_user_id' )->alias( fn() => $this->user );
		Functions\when( 'set_transient' )->alias(
			function ( string $key, $value ): bool {
				$this->transients[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'get_transient' )->alias( fn( string $key ) => $this->transients[ $key ] ?? false );
		Functions\when( 'delete_transient' )->alias(
			function ( string $key ): bool {
				unset( $this->transients[ $key ] );
				return true;
			}
		);
	}

	protected function tearDown(): void {
		Session::reset();
		parent::tearDown();
	}

	public function test_without_a_token_nothing_is_written_and_the_preview_carries_a_token(): void {
		$result = $this->ability()->run( [ 'price' => 550 ] );

		$this->assertSame( [ 'target' => [ 'id' => 18, 'lang' => 'uk' ], 'field' => 'prices[0].price', 'from' => 450, 'to' => 550 ], $result['diff'][0] );
		$this->assertSame( [], $result['warnings'] );
		$this->assertIsString( $result['confirmation'] );
		$this->assertNotSame( '', $result['confirmation'] );
		$this->assertSame( [ 'preview' ], $this->events );
	}

	public function test_the_previews_token_dumps_then_writes(): void {
		$token = $this->ability()->run( [ 'price' => 550 ] )['confirmation'];

		$result = $this->ability()->run( [ 'price' => 550, 'confirmation' => $token ] );

		$this->assertSame( [ 'written' => [ 18 ], 'dump' => 'dovira-dev.dovira.vet-2026-11-05-101500.sql.gz', 'warnings' => [] ], $result );
		$this->assertSame( [ 'preview', 'preview', 'dump:session-a', 'write' ], $this->events );
	}

	public function test_what_a_write_returns_besides_the_protocols_keys_reaches_the_result_and_never_replaces_them(): void {
		$token = $this->ability()->run( [ 'price' => 550, 'extra' => true ] )['confirmation'];

		$result = $this->ability()->run( [ 'price' => 550, 'extra' => true, 'confirmation' => $token ] );

		$this->assertSame( [ 'id' => 5 ], $result['media'] );
		$this->assertSame( 'dovira-dev.dovira.vet-2026-11-05-101500.sql.gz', $result['dump'] );
		$this->assertSame( [ 18 ], $result['written'] );
	}

	public function test_a_token_of_another_payload_writes_nothing_and_takes_no_dump(): void {
		$token = $this->ability()->run( [ 'price' => 550 ] )['confirmation'];

		$result = $this->ability()->run( [ 'price' => 560, 'confirmation' => $token ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'dovira_confirmation_invalid', $result->get_error_code() );
		$this->assertNotContains( 'write', $this->events );
		$this->assertNotContains( 'dump:session-a', $this->events );
	}

	public function test_a_token_is_bound_to_the_user_and_the_session_and_writes_once(): void {
		$token = $this->ability()->run( [ 'price' => 550 ] )['confirmation'];

		$this->user = 8;
		$this->assertInstanceOf( \WP_Error::class, $this->ability()->run( [ 'price' => 550, 'confirmation' => $token ] ) );

		$this->user = 7;
		Session::capture( null, null, self::session_request( 'session-b' ) );
		$this->assertInstanceOf( \WP_Error::class, $this->ability()->run( [ 'price' => 550, 'confirmation' => $token ] ) );

		Session::capture( null, null, self::session_request( 'session-a' ) );
		$this->assertIsArray( $this->ability()->run( [ 'price' => 550, 'confirmation' => $token ] ) );
		$this->assertInstanceOf( \WP_Error::class, $this->ability()->run( [ 'price' => 550, 'confirmation' => $token ] ), 'once' );
		$this->assertSame( 1, count( array_keys( $this->events, 'write', true ) ) );
	}

	public function test_a_made_up_token_writes_nothing(): void {
		$result = $this->ability()->run( [ 'price' => 550, 'confirmation' => 'abc' ] );

		$this->assertSame( 'dovira_confirmation_invalid', $result->get_error_code() );
		$this->assertSame( [ 'preview' ], $this->events );
	}

	public function test_a_failed_dump_writes_nothing(): void {
		$token = $this->ability()->run( [ 'price' => 550 ] )['confirmation'];

		$ability = $this->ability(
			function ( string $session ): string {
				$this->events[] = "dump:{$session}";
				throw new \RuntimeException( 'Папка дампів /x недоступна для запису.' );
			}
		);
		$result  = $ability->run( [ 'price' => 550, 'confirmation' => $token ] );

		$this->assertSame( 'dovira_dump_failed', $result->get_error_code() );
		$this->assertStringContainsString( 'недоступна для запису', $result->get_error_message() );
		$this->assertNotContains( 'write', $this->events );
	}

	public function test_an_invalid_payload_issues_no_token(): void {
		$result = $this->ability()->run( [ 'price' => -1 ] );

		$this->assertSame( 'dovira_price_invalid', $result->get_error_code() );
		$this->assertSame( [], $this->transients );
	}

	public function test_a_change_that_changes_nothing_issues_no_token(): void {
		$result = $this->ability()->run( [ 'price' => 450 ] );

		$this->assertSame( [], $result['diff'] );
		$this->assertNull( $result['confirmation'] );
		$this->assertNotEmpty( $result['warnings'] );
		$this->assertSame( [], $this->transients );
	}

	public function test_without_a_session_the_token_still_binds_and_the_dump_is_asked_for_an_empty_session(): void {
		Session::reset();
		$token = $this->ability()->run( [ 'price' => 550 ] )['confirmation'];

		$this->ability()->run( [ 'price' => 550, 'confirmation' => $token ] );

		$this->assertContains( 'dump:', $this->events );
	}

	public function test_update_price_row_needs_edit_post_and_lets_a_missing_id_through(): void {
		Functions\when( 'get_post' )->alias( static fn( int $id ) => 18 === $id ? (object) [ 'ID' => 18, 'post_type' => 'service' ] : null );
		Functions\when( 'current_user_can' )->justReturn( false );

		$this->assertFalse( UpdatePriceRow::can( [ 'service_id' => 18 ] ) );
		$this->assertTrue( UpdatePriceRow::can( [ 'service_id' => 99 ] ) );

		Functions\when( 'current_user_can' )->alias( static fn( string $cap, int $id ) => 'edit_post' === $cap && 18 === $id );
		$this->assertTrue( UpdatePriceRow::can( [ 'service_id' => 18 ] ) );
	}

	public function test_update_price_row_refuses_a_missing_post_and_a_non_service_before_any_token(): void {
		Functions\when( 'get_post' )->alias( static fn( int $id ) => 3 === $id ? (object) [ 'ID' => 3, 'post_type' => 'page' ] : null );

		$missing = ( new UpdatePriceRow( null, $this->dump_spy() ) )->run( [ 'service_id' => 99, 'row_index' => 0, 'price' => 550 ] );
		$page    = ( new UpdatePriceRow( null, $this->dump_spy() ) )->run( [ 'service_id' => 3, 'row_index' => 0, 'price' => 550 ] );

		$this->assertSame( 'dovira_post_not_found', $missing->get_error_code() );
		$this->assertSame( 'dovira_not_a_service', $page->get_error_code() );
		$this->assertSame( [], $this->transients );
	}

	public function test_update_price_row_previews_then_writes_the_row_through_acf(): void {
		$raw     = [ [ 'field_t' => 'Екстрений прийом', 'field_p' => '450', 'field_g' => '1', 'field_cities' => [ 'city_4' ] ] ];
		$updated = [];
		Functions\when( 'get_post' )->justReturn( (object) [ 'ID' => 18, 'post_type' => 'service' ] );
		Functions\when( 'get_site_url' )->justReturn( 'https://dev.dovira.vet' );
		Functions\when( 'get_term_by' )->justReturn( (object) [ 'term_id' => 4 ] );
		Functions\when( 'get_field_object' )->justReturn(
			[
				'key'        => 'field_prices',
				'sub_fields' => [
					[ 'key' => 'field_t', 'name' => 'title' ],
					[ 'key' => 'field_p', 'name' => 'price' ],
					[ 'key' => 'field_g', 'name' => 'is_price_general' ],
					[ 'key' => 'field_cities', 'name' => 'cities' ],
				],
			]
		);
		Functions\when( 'get_field' )->justReturn( $raw );
		Functions\when( 'update_field' )->alias(
			static function ( string $key, $rows, int $id ) use ( &$updated ): bool {
				$updated[] = [ $key, $rows, $id ];
				return true;
			}
		);
		$ability = fn() => new UpdatePriceRow( new \dovira\ContentMcp\PriceRows( false, static fn() => [] ), $this->dump_spy() );
		$input   = [ 'service_id' => 18, 'row_index' => 0, 'price' => 550 ];

		$preview = $ability()->run( $input );
		$this->assertSame( [], $updated );

		$result = $ability()->run( $input + [ 'confirmation' => $preview['confirmation'] ] );

		$this->assertSame( [ 18 ], $result['written'] );
		$this->assertSame( 'dovira-dev.dovira.vet-2026-11-05-101500.sql.gz', $result['dump'] );
		$raw[0]['field_p'] = '550';
		$this->assertSame( [ [ 'field_prices', $raw, 18 ] ], $updated );
		$this->assertSame( [ 'dump:session-a' ], array_values( array_filter( $this->events, static fn( $e ) => str_starts_with( $e, 'dump' ) ) ) );
	}

	public function test_update_price_row_is_a_write_with_the_confirmation_input(): void {
		$args = UpdatePriceRow::args();

		$this->assertFalse( $args['meta']['annotations']['readonly'] );
		$this->assertTrue( $args['meta']['annotations']['destructive'] );
		$this->assertFalse( $args['meta']['mcp']['public'] );
		$this->assertArrayHasKey( 'confirmation', $args['input_schema']['properties'] );
		$this->assertSame( [ 'service_id', 'row_index' ], $args['input_schema']['required'] );
	}

	/**
	 * A write ability over one fixed price: 450 in row 0 of service 18 (uk).
	 */
	private function ability( ?callable $dump = null ): WriteAbility {
		$events = &$this->events;

		return new class( $dump ?? $this->dump_spy(), $events ) extends WriteAbility {
			/** @param list<string> $events */
			public function __construct( callable $dump, private array &$events ) {
				parent::__construct( $dump );
			}

			protected function preview( array $payload ) {
				$this->events[] = 'preview';
				if ( ! is_numeric( $payload['price'] ?? null ) || 0 >= $payload['price'] ) {
					return new \WP_Error( 'dovira_price_invalid', 'Ціна — число, більше за 0.' );
				}
				return ( new Diff() )->add( 18, 'uk', 'prices[0].price', 450, $payload['price'] );
			}

			protected function write( array $payload ) {
				$this->events[] = 'write';
				return [
					'written'  => [ 18 ],
					'warnings' => [],
				] + ( empty( $payload['extra'] ) ? [] : [
					'media' => [ 'id' => 5 ],
					'dump'  => 'forged',
				] );
			}
		};
	}

	private function dump_spy(): callable {
		return function ( string $session ): string {
			$this->events[] = "dump:{$session}";
			return 'dovira-dev.dovira.vet-2026-11-05-101500.sql.gz';
		};
	}

	private static function session_request( string $session ): \WP_REST_Request {
		$request = new \WP_REST_Request( 'POST', '/mcp/dovira' );
		$request->set_header( 'Mcp-Session-Id', $session );

		return $request;
	}
}
