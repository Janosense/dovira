<?php
/**
 * Tests for the Data API client, on the responses the spike recorded.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge\Tests\Unit;

use Brain\Monkey\Functions;
use GaTelegramBridge\GaClient;
use GaTelegramBridge\GaClientException;
use GaTelegramBridge\Tests\TestCase;
use GaTelegramBridge\Tests\TestKey;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The parsing and the error mapping run on the recorded payloads
 * (docs/TESTING.md → Never mocked); only the network boundary is stubbed.
 */
final class GaClientTest extends TestCase {

	/**
	 * Stubs the WordPress helpers every path here goes through.
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'get_transient' )->justReturn( 'a-cached-access-token' );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static fn( array $response ): int => (int) $response['response']['code']
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static fn( array $response ): string => (string) $response['body']
		);
		Functions\when( 'get_option' )->justReturn(
			array(
				'property_id'          => '533779496',
				'service_account_json' => (string) wp_json_encode( TestKey::service_account() ),
			)
		);
	}

	/**
	 * Reads a recorded response and shapes it the way wp_remote_post returns.
	 *
	 * @param string $name The fixture file name.
	 * @return array{response: array{code: int}, body: string}
	 */
	private function fixture( string $name ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a test fixture on disk, no WP_Filesystem in unit tests.
		$raw     = (string) file_get_contents( __DIR__ . '/../fixtures/ga/' . $name );
		$fixture = (array) json_decode( $raw, true );

		return array(
			'response' => array( 'code' => (int) $fixture['status'] ),
			'body'     => (string) wp_json_encode( $fixture['body'] ),
		);
	}

	/**
	 * The batch call goes to the configured property with the bearer token.
	 */
	public function test_a_batch_call_is_addressed_and_authorised_correctly(): void {
		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				'https://analyticsdata.googleapis.com/v1beta/properties/533779496:batchRunReports',
				\Mockery::on(
					function ( array $arguments ): bool {
						$this->assertSame( 20, $arguments['timeout'] );
						$this->assertSame( 'Bearer a-cached-access-token', $arguments['headers']['Authorization'] );
						$this->assertSame(
							array( 'requests' => array( array( 'metrics' => array( array( 'name' => 'activeUsers' ) ) ) ) ),
							json_decode( $arguments['body'], true )
						);

						return true;
					}
				)
			)
			->andReturn( $this->fixture( 'batch-run-reports-four-date-ranges.json' ) );

		$reports = GaClient::batch_run_reports(
			array( array( 'metrics' => array( array( 'name' => 'activeUsers' ) ) ) )
		);

		$this->assertCount( 1, $reports );
		$this->assertSame( 'dateRange', $reports[0]['dimensionHeaders'][0]['name'] );
		$this->assertCount( 4, $reports[0]['rows'], 'the four date ranges of the recorded response' );
	}

	/**
	 * The connection check reports the property's own reporting time zone.
	 */
	public function test_the_connection_check_reads_the_properties_time_zone(): void {
		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				'https://analyticsdata.googleapis.com/v1beta/properties/533779496:runReport',
				\Mockery::on(
					function ( array $arguments ): bool {
						$body = json_decode( $arguments['body'], true );

						$this->assertSame( 1, $body['limit'] );
						$this->assertSame( 'activeUsers', $body['metrics'][0]['name'] );
						$this->assertSame( 'yesterday', $body['dateRanges'][0]['startDate'] );

						return true;
					}
				)
			)
			->andReturn( $this->fixture( 'run-report-active-users-yesterday.json' ) );

		$this->assertSame(
			array(
				'property_id'   => '533779496',
				'time_zone'     => 'Europe/Kiev',
				'currency_code' => 'USD',
			),
			GaClient::check_connection()
		);
	}

	/**
	 * A property id that was never entered is refused before any request.
	 */
	public function test_an_unconfigured_property_is_refused_without_a_request(): void {
		Functions\when( 'get_option' )->justReturn( array( 'property_id' => '' ) );
		Functions\expect( 'wp_remote_post' )->never();

		$this->expectException( GaClientException::class );
		$this->expectExceptionMessageMatches( '/No GA4 property id/' );

		GaClient::check_connection();
	}

	/**
	 * Each recorded failure becomes the sentence that names what to fix.
	 *
	 * @param string $fixture The recorded response.
	 * @param string $expect  A fragment the message must contain.
	 */
	#[DataProvider( 'provide_recorded_failures' )]
	public function test_a_refused_call_is_reported_in_words( string $fixture, string $expect ): void {
		Functions\when( 'wp_remote_post' )->justReturn( $this->fixture( $fixture ) );
		Functions\when( 'delete_transient' )->justReturn( true );

		$this->expectException( GaClientException::class );
		$this->expectExceptionMessageMatches( '/' . preg_quote( $expect, '/' ) . '/' );

		GaClient::check_connection();
	}

	/**
	 * The failures the spike recorded, and what each must tell the reader.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function provide_recorded_failures(): array {
		return array(
			'the property id is not a property' => array( 'error-bad-property-id.json', 'Check the property id' ),
			'the account has no access'         => array( 'error-no-access-property.json', 'at least Viewer access' ),
			'the token was refused'             => array( 'error-bad-bearer-token.json', 'refused the access token' ),
			'the project is rate-limited'       => array( 'error-quota-exceeded.written.json', 'rate-limiting this project' ),
		);
	}

	/**
	 * The no-access message names the account the user has to grant access to.
	 */
	public function test_the_no_access_message_names_the_service_account(): void {
		Functions\when( 'wp_remote_post' )->justReturn( $this->fixture( 'error-no-access-property.json' ) );

		try {
			GaClient::check_connection();
			$this->fail( 'A property the account cannot read must not return a result.' );
		} catch ( GaClientException $exception ) {
			$this->assertStringContainsString( 'reporter@example.iam.gserviceaccount.com', $exception->getMessage() );
		}
	}

	/**
	 * A refused token is thrown away, so the next attempt signs in again.
	 */
	public function test_a_refused_token_is_dropped_from_the_cache(): void {
		Functions\when( 'wp_remote_post' )->justReturn( $this->fixture( 'error-bad-bearer-token.json' ) );
		Functions\expect( 'delete_transient' )->once()->with( 'gatb_google_access_token' );

		$this->expectException( GaClientException::class );

		GaClient::check_connection();
	}

	/**
	 * A token that is merely wrong for another property is not thrown away.
	 */
	public function test_a_permission_failure_keeps_the_cached_token(): void {
		Functions\when( 'wp_remote_post' )->justReturn( $this->fixture( 'error-no-access-property.json' ) );
		Functions\expect( 'delete_transient' )->never();

		$this->expectException( GaClientException::class );

		GaClient::check_connection();
	}

	/**
	 * Google being down is reported as Google being down.
	 */
	public function test_a_server_error_is_reported_as_googles_side(): void {
		Functions\when( 'wp_remote_post' )->justReturn(
			array(
				'response' => array( 'code' => 503 ),
				'body'     => '{"error":{"code":503,"message":"The service is currently unavailable.","status":"UNAVAILABLE"}}',
			)
		);

		$this->expectException( GaClientException::class );
		$this->expectExceptionMessageMatches( '/not answering right now \(HTTP 503\)/' );

		GaClient::check_connection();
	}

	/**
	 * A host that cannot reach Google says so, with the transport's reason.
	 */
	public function test_an_unreachable_google_is_reported_as_such(): void {
		$wp_error = new class() {
			/**
			 * The transport's own message.
			 */
			public function get_error_message(): string {
				return 'cURL error 6: Could not resolve host';
			}
		};

		Functions\when( 'wp_remote_post' )->justReturn( $wp_error );
		Functions\when( 'is_wp_error' )->justReturn( true );

		$this->expectException( GaClientException::class );
		$this->expectExceptionMessageMatches( '/could not reach Google Analytics.*Could not resolve host/' );

		GaClient::check_connection();
	}

	/**
	 * A 200 that carries no report is a failure, not an empty result.
	 */
	public function test_an_answer_without_reports_is_a_failure(): void {
		Functions\when( 'wp_remote_post' )->justReturn(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"kind":"analyticsData#batchRunReports"}',
			)
		);

		$this->expectException( GaClientException::class );
		$this->expectExceptionMessageMatches( '/without any report/' );

		GaClient::batch_run_reports( array( array( 'metrics' => array() ) ) );
	}

	/**
	 * No failure message ever repeats the key or the access token.
	 */
	public function test_no_failure_message_carries_key_material(): void {
		$messages = array(
			GaClient::client_error_message(
				400,
				array(
					'error' => array(
						'status'  => 'INVALID_ARGUMENT',
						'message' => 'Invalid property ID: abc.',
					),
				)
			),
			GaClient::client_error_message(
				403,
				array(
					'error' => array(
						'status'  => 'PERMISSION_DENIED',
						'message' => 'no',
					),
				)
			),
			GaClient::client_error_message(
				401,
				array(
					'error' => array(
						'status'  => 'UNAUTHENTICATED',
						'message' => 'no',
					),
				)
			),
			GaClient::client_error_message(
				429,
				array(
					'error' => array(
						'status'  => 'RESOURCE_EXHAUSTED',
						'message' => 'no',
					),
				)
			),
			GaClient::client_error_message( 500, array() ),
			GaClient::client_error_message( 418, array() ),
		);

		foreach ( $messages as $message ) {
			$this->assertNotSame( '', $message );
			$this->assertStringNotContainsString( 'PRIVATE KEY', $message );
			$this->assertStringNotContainsString( 'a-cached-access-token', $message );
			$this->assertStringNotContainsString( TestKey::private_pem(), $message );
		}
	}
}
