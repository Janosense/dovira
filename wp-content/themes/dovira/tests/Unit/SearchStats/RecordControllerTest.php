<?php
/**
 * Tests for dovira\SearchStats\RecordController, with Normalizer and Repository running for real.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\SearchStats;

use Brain\Monkey\Functions;
use dovira\SearchStats\RecordController;
use dovira\Tests\TestCase;
use dovira\Tests\WpdbDouble;
use PHPUnit\Framework\Attributes\DataProvider;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/Schema.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/Normalizer.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/Repository.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/RecordController.php';

/**
 * The route's registration, the row each level writes, and every refusal leaving no row.
 * Requests are WordPress's own WP_REST_Request, so the JSON body is parsed as WordPress parses it.
 */
final class RecordControllerTest extends TestCase {

	private const NOW = '2026-09-23 12:00:00';

	private const FORMATS = [ '%s', '%s', '%d', '%d', '%s' ];

	/**
	 * What get_post_status() and get_post_type() answer, by id. Id 0 answers as
	 * a published page, as get_post( 0 ) would with a global post set.
	 */
	private const POSTS = [
		0   => [ 'publish', 'page' ],
		101 => [ 'publish', 'page' ],
		202 => [ 'publish', 'service' ],
		303 => [ 'draft', 'service' ],
	];

	private WpdbDouble $wpdb;

	/**
	 * Every current_time() call, in order: its arguments.
	 *
	 * @var list<list<mixed>>
	 */
	private array $time_calls = [];

	protected function setUp(): void {
		parent::setUp();

		$this->wpdb      = new WpdbDouble( 'wp_' );
		$GLOBALS['wpdb'] = $this->wpdb;

		Functions\stubTranslationFunctions();
		Functions\when( 'wp_is_json_media_type' )->alias( static fn ( string $type ): bool => 'application/json' === $type );
		Functions\when( 'get_post_status' )->alias( static fn ( $id ) => self::POSTS[ $id ][0] ?? false );
		Functions\when( 'get_post_type' )->alias( static fn ( $id ) => self::POSTS[ $id ][1] ?? false );
		Functions\when( 'current_time' )->alias(
			function ( ...$args ): string {
				$this->time_calls[] = $args;

				return self::NOW;
			}
		);
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	private static function request( string $content_type, string $body ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/dovira/v1/search-stats/record' );
		$request->set_header( 'Content-Type', $content_type );
		$request->set_body( $body );

		return $request;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function record( array $payload ): WP_REST_Response|WP_Error {
		$body = json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR );

		return ( new RecordController() )->record( self::request( 'application/json', $body ) );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function assertOneRow( array $data ): void {
		$this->assertSame( [ [ 'wp_dovira_search_queries', $data, self::FORMATS ] ], $this->wpdb->inserts );
	}

	private function assertNoContent( WP_REST_Response|WP_Error $response ): void {
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 204, $response->get_status() );
		$this->assertNull( $response->get_data() );
	}

	private function assertRefused( WP_REST_Response|WP_Error $response, string $code, int $status ): void {
		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( $code, $response->get_error_code() );
		$this->assertSame( [ 'status' => $status ], $response->get_error_data() );
	}

	public function test_the_route_is_registered_as_a_public_post(): void {
		$calls = [];
		Functions\when( 'register_rest_route' )->alias(
			function ( ...$args ) use ( &$calls ): bool {
				$calls[] = $args;

				return true;
			}
		);
		$controller = new RecordController();

		$controller->register_routes();

		$this->assertSame(
			[
				[
					'dovira/v1',
					'/search-stats/record',
					[
						'methods'             => 'POST',
						'callback'            => [ $controller, 'record' ],
						'permission_callback' => '__return_true',
					],
				],
			],
			$calls
		);
	}

	public function test_a_site_search_is_recorded_with_its_results_and_no_context(): void {
		$response = $this->record( [ 'level' => 'site', 'query' => '  Вакцинація  ', 'results' => 0, 'context_id' => 55 ] );

		$this->assertNoContent( $response );
		$this->assertOneRow(
			[ 'level' => 'site', 'query_text' => 'вакцинація', 'context_id' => 0, 'results' => 0, 'created_at' => self::NOW ]
		);
		$this->assertSame( [ [ 'mysql', true ] ], $this->time_calls );
	}

	public function test_a_services_search_is_recorded_with_its_page_and_no_results(): void {
		$response = $this->record( [ 'level' => 'services', 'query' => 'Вакц', 'context_id' => 101 ] );

		$this->assertNoContent( $response );
		$this->assertOneRow(
			[ 'level' => 'services', 'query_text' => 'вакц', 'context_id' => 101, 'results' => null, 'created_at' => self::NOW ]
		);
	}

	public function test_a_service_search_is_recorded_with_its_service_and_no_results(): void {
		$response = $this->record( [ 'level' => 'service', 'query' => 'кіт', 'context_id' => 202 ] );

		$this->assertNoContent( $response );
		$this->assertOneRow(
			[ 'level' => 'service', 'query_text' => 'кіт', 'context_id' => 202, 'results' => null, 'created_at' => self::NOW ]
		);
	}

	/**
	 * @return array<string, array{array<string, mixed>, array<string, mixed>}>
	 */
	public static function accepted_edges(): array {
		$hundred = str_repeat( 'а', 100 );

		return [
			'a one-character site query'          => [
				[ 'level' => 'site', 'query' => 'a', 'results' => 3 ],
				[ 'level' => 'site', 'query_text' => 'a', 'context_id' => 0, 'results' => 3, 'created_at' => self::NOW ],
			],
			'a 100-character Cyrillic query'      => [
				[ 'level' => 'services', 'query' => $hundred, 'context_id' => 101 ],
				[ 'level' => 'services', 'query_text' => $hundred, 'context_id' => 101, 'results' => null, 'created_at' => self::NOW ],
			],
			'length measured after normalization' => [
				[ 'level' => 'service', 'query' => str_repeat( ' ', 75 ) . 'КІТ' . str_repeat( ' ', 75 ), 'context_id' => 202 ],
				[ 'level' => 'service', 'query_text' => 'кіт', 'context_id' => 202, 'results' => null, 'created_at' => self::NOW ],
			],
		];
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param array<string, mixed> $row
	 */
	#[DataProvider( 'accepted_edges' )]
	public function test_a_request_at_the_edge_is_recorded( array $payload, array $row ): void {
		$response = $this->record( $payload );

		$this->assertNoContent( $response );
		$this->assertOneRow( $row );
	}

	/**
	 * @return array<string, array{array<string, mixed>, string}>
	 */
	public static function refusals(): array {
		$level   = 'search_stats_invalid_level';
		$query   = 'search_stats_invalid_query';
		$context = 'search_stats_invalid_context';
		$results = 'search_stats_invalid_results';

		return [
			'level missing'                       => [ [ 'query' => 'кіт', 'results' => 0 ], $level ],
			'level unknown'                       => [ [ 'level' => 'x', 'query' => 'кіт', 'results' => 0 ], $level ],
			'level not a string'                  => [ [ 'level' => 1, 'query' => 'кіт', 'results' => 0 ], $level ],

			'query missing'                       => [ [ 'level' => 'site', 'results' => 0 ], $query ],
			'query not a string'                  => [ [ 'level' => 'site', 'query' => 123, 'results' => 0 ], $query ],
			'query empty'                         => [ [ 'level' => 'site', 'query' => '', 'results' => 0 ], $query ],
			'query empty after normalization'     => [ [ 'level' => 'site', 'query' => "  \t ", 'results' => 0 ], $query ],
			'query too short for a filter'        => [ [ 'level' => 'services', 'query' => 'ва', 'context_id' => 101 ], $query ],
			'query too short after normalization' => [ [ 'level' => 'service', 'query' => '  ві  ', 'context_id' => 202 ], $query ],
			'query too long'                      => [ [ 'level' => 'site', 'query' => str_repeat( 'а', 101 ), 'results' => 0 ], $query ],

			'context missing'                     => [ [ 'level' => 'services', 'query' => 'кіт' ], $context ],
			'context not an integer'              => [ [ 'level' => 'services', 'query' => 'кіт', 'context_id' => '101' ], $context ],
			'context zero'                        => [ [ 'level' => 'services', 'query' => 'кіт', 'context_id' => 0 ], $context ],
			'context unknown'                     => [ [ 'level' => 'services', 'query' => 'кіт', 'context_id' => 999 ], $context ],
			'context unpublished'                 => [ [ 'level' => 'service', 'query' => 'кіт', 'context_id' => 303 ], $context ],
			'context not a service'               => [ [ 'level' => 'service', 'query' => 'кіт', 'context_id' => 101 ], $context ],

			'results missing for site'            => [ [ 'level' => 'site', 'query' => 'кіт' ], $results ],
			'results negative'                    => [ [ 'level' => 'site', 'query' => 'кіт', 'results' => -1 ], $results ],
			'results a string'                    => [ [ 'level' => 'site', 'query' => 'кіт', 'results' => '3' ], $results ],
			'results a float'                     => [ [ 'level' => 'site', 'query' => 'кіт', 'results' => 3.5 ], $results ],
			'results present for services'        => [ [ 'level' => 'services', 'query' => 'кіт', 'context_id' => 101, 'results' => 0 ], $results ],
			'results null for service'            => [ [ 'level' => 'service', 'query' => 'кіт', 'context_id' => 202, 'results' => null ], $results ],
		];
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	#[DataProvider( 'refusals' )]
	public function test_an_invalid_request_is_refused_before_any_write( array $payload, string $code ): void {
		$response = $this->record( $payload );

		$this->assertRefused( $response, $code, 400 );
		$this->assertSame( [], $this->wpdb->inserts );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function bodies_that_are_not_json(): array {
		return [
			'a form body'    => [ 'application/x-www-form-urlencoded', 'level=site&query=кіт&results=0' ],
			'an empty body'  => [ 'application/json', '' ],
			'a JSON string'  => [ 'application/json', '"кіт"' ],
		];
	}

	#[DataProvider( 'bodies_that_are_not_json' )]
	public function test_a_body_that_is_not_a_json_object_is_refused( string $content_type, string $body ): void {
		$response = ( new RecordController() )->record( self::request( $content_type, $body ) );

		$this->assertRefused( $response, 'search_stats_not_json', 400 );
		$this->assertSame( [], $this->wpdb->inserts );
	}

	public function test_a_failed_insert_answers_500(): void {
		$this->wpdb->insert_result = false;

		$response = $this->record( [ 'level' => 'site', 'query' => 'кіт', 'results' => 2 ] );

		$this->assertRefused( $response, 'search_stats_not_recorded', 500 );
		$this->assertCount( 1, $this->wpdb->inserts );
	}
}
