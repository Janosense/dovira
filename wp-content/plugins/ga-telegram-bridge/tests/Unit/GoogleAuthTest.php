<?php
/**
 * Tests for signing in to Google: the JWT, the token exchange and its cache.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge\Tests\Unit;

use Brain\Monkey\Functions;
use GaTelegramBridge\GoogleAuth;
use GaTelegramBridge\GoogleAuthException;
use GaTelegramBridge\Tests\TestCase;
use GaTelegramBridge\Tests\TestKey;

/**
 * The signature is made and verified for real (docs/TESTING.md → Never mocked);
 * only the network boundary and the WordPress functions are stubbed.
 */
final class GoogleAuthTest extends TestCase {

	/**
	 * Stubs the WordPress helpers every path here goes through.
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static fn( array $response ): int => (int) $response['response']['code']
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static fn( array $response ): string => (string) $response['body']
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
	 * Splits a JWT into what was signed and the signature itself.
	 *
	 * @param string $jwt The token.
	 * @return array{0: string, 1: string}
	 */
	private function split_jwt( string $jwt ): array {
		$parts = explode( '.', $jwt );

		return array(
			$parts[0] . '.' . $parts[1],
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- base64url is how a JWT is encoded, not obfuscation.
			(string) base64_decode( strtr( $parts[2], '-_', '+/' ), true ),
		);
	}

	/**
	 * Decodes one base64url part of a JWT.
	 *
	 * @param string $part The part.
	 * @return array<string, mixed>
	 */
	private function decode_part( string $part ): array {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- base64url is how a JWT is encoded, not obfuscation.
		$json = (string) base64_decode( strtr( $part, '-_', '+/' ), true );

		return (array) json_decode( $json, true );
	}

	/**
	 * The header says what Google expects: an RS256-signed JWT.
	 */
	public function test_the_jwt_header_declares_rs256(): void {
		$jwt   = GoogleAuth::build_jwt( TestKey::service_account(), 1_757_000_000 );
		$parts = explode( '.', $jwt );

		$this->assertCount( 3, $parts );
		$this->assertSame(
			array(
				'alg' => 'RS256',
				'typ' => 'JWT',
			),
			$this->decode_part( $parts[0] )
		);
	}

	/**
	 * The claims are the ones the token endpoint was proven to accept.
	 */
	public function test_the_jwt_claims_ask_for_read_only_analytics_for_one_hour(): void {
		$now   = 1_757_000_000;
		$jwt   = GoogleAuth::build_jwt( TestKey::service_account(), $now );
		$parts = explode( '.', $jwt );

		$this->assertSame(
			array(
				'iss'   => 'reporter@example.iam.gserviceaccount.com',
				'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
				'aud'   => 'https://oauth2.googleapis.com/token',
				'iat'   => $now,
				'exp'   => $now + 3600,
			),
			$this->decode_part( $parts[1] )
		);
	}

	/**
	 * The signature is a real RS256 signature over the first two parts.
	 */
	public function test_the_signature_verifies_against_the_public_key(): void {
		$jwt = GoogleAuth::build_jwt( TestKey::service_account(), time() );

		list( $signing_input, $signature ) = $this->split_jwt( $jwt );

		$this->assertSame(
			1,
			openssl_verify( $signing_input, $signature, TestKey::public_pem(), OPENSSL_ALGO_SHA256 )
		);
	}

	/**
	 * Negative check: one changed byte and the signature no longer verifies.
	 */
	public function test_a_tampered_signature_does_not_verify(): void {
		$jwt = GoogleAuth::build_jwt( TestKey::service_account(), time() );

		list( $signing_input, $signature ) = $this->split_jwt( $jwt );

		$signature[10] = "\x00" === $signature[10] ? "\x01" : "\x00";

		$this->assertSame(
			0,
			openssl_verify( $signing_input, $signature, TestKey::public_pem(), OPENSSL_ALGO_SHA256 )
		);
	}

	/**
	 * A key file that is not usable is refused before anything is signed.
	 *
	 * @param string $json   The key file as configured.
	 * @param string $expect A fragment the message must contain.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'provide_unusable_keys' )]
	public function test_an_unusable_key_file_is_refused( string $json, string $expect ): void {
		$this->expectException( GoogleAuthException::class );
		$this->expectExceptionMessageMatches( '/' . preg_quote( $expect, '/' ) . '/' );

		GoogleAuth::parse_service_account( $json );
	}

	/**
	 * Key files that must be refused, and what the message must say.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function provide_unusable_keys(): array {
		return array(
			'nothing configured' => array( '   ', 'No Google service-account key' ),
			'not json'           => array( 'paste it here', 'not valid JSON' ),
			'a json list'        => array( '["client_email"]', 'client_email' ),
			'no client_email'    => array( '{"private_key":"-----BEGIN PRIVATE KEY-----"}', 'client_email' ),
			'no private_key'     => array( '{"client_email":"a@b.iam.gserviceaccount.com"}', 'private_key' ),
		);
	}

	/**
	 * A key that openssl cannot read is reported, without echoing the key.
	 */
	public function test_a_private_key_openssl_cannot_read_is_reported_without_quoting_it(): void {
		$key = TestKey::service_account( array( 'private_key' => '-----BEGIN PRIVATE KEY-----NOTAKEY-----END PRIVATE KEY-----' ) );

		try {
			GoogleAuth::build_jwt( $key, time() );
			$this->fail( 'A key openssl cannot read must not produce a JWT.' );
		} catch ( GoogleAuthException $exception ) {
			$this->assertStringContainsString( 'private key', $exception->getMessage() );
			$this->assertStringNotContainsString( 'NOTAKEY', $exception->getMessage() );
		}
	}

	/**
	 * The key file's own token_uri is used, and it is what the JWT is made for.
	 */
	public function test_the_key_files_token_uri_is_honoured(): void {
		$key = GoogleAuth::parse_service_account(
			(string) wp_json_encode( TestKey::service_account( array( 'token_uri' => 'https://oauth2.example.test/token' ) ) )
		);

		$this->assertSame( 'https://oauth2.example.test/token', $key['token_uri'] );

		$parts = explode( '.', GoogleAuth::build_jwt( $key, time() ) );

		$this->assertSame( 'https://oauth2.example.test/token', $this->decode_part( $parts[1] )['aud'] );
	}

	/**
	 * A cached token is used as it is: no signing, no request.
	 */
	public function test_a_cached_token_is_returned_without_calling_google(): void {
		Functions\expect( 'get_transient' )->once()->with( 'gatb_google_access_token' )->andReturn( 'cached-token' );
		Functions\expect( 'wp_remote_post' )->never();

		$this->assertSame( 'cached-token', GoogleAuth::access_token() );
	}

	/**
	 * Without a cached token, the JWT is exchanged and the answer is cached.
	 */
	public function test_a_token_is_fetched_and_cached_for_its_lifetime_less_a_minute(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn(
			array( 'service_account_json' => (string) wp_json_encode( TestKey::service_account() ) )
		);

		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				'https://oauth2.googleapis.com/token',
				\Mockery::on(
					function ( array $arguments ): bool {
						$this->assertSame( 15, $arguments['timeout'] );
						$this->assertSame(
							'urn:ietf:params:oauth:grant-type:jwt-bearer',
							$arguments['body']['grant_type']
						);

						list( $signing_input, $signature ) = $this->split_jwt( $arguments['body']['assertion'] );

						$this->assertSame(
							1,
							openssl_verify( $signing_input, $signature, TestKey::public_pem(), OPENSSL_ALGO_SHA256 ),
							'the assertion sent to Google is signed with the configured key'
						);

						return true;
					}
				)
			)
			->andReturn( $this->fixture( 'token-success.written.json' ) );

		Functions\expect( 'set_transient' )
			->once()
			->with( 'gatb_google_access_token', 'ya29.EXAMPLE-ACCESS-TOKEN-FOR-TESTS-ONLY', 3539 );

		$this->assertSame( 'ya29.EXAMPLE-ACCESS-TOKEN-FOR-TESTS-ONLY', GoogleAuth::access_token() );
	}

	/**
	 * A refused signature is reported as a key problem, not as a bug.
	 */
	public function test_a_refused_jwt_becomes_a_readable_failure(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn(
			array( 'service_account_json' => (string) wp_json_encode( TestKey::service_account() ) )
		);
		Functions\when( 'wp_remote_post' )->justReturn( $this->fixture( 'token-error-tampered-signature.json' ) );
		Functions\expect( 'set_transient' )->never();

		try {
			GoogleAuth::access_token();
			$this->fail( 'A refused JWT must not yield a token.' );
		} catch ( GoogleAuthException $exception ) {
			$this->assertStringContainsString( 'refused the service-account key', $exception->getMessage() );
			$this->assertStringContainsString( 'Invalid JWT Signature.', $exception->getMessage() );
		}
	}

	/**
	 * No failure message ever repeats the key, the JWT or the token.
	 */
	public function test_no_token_failure_message_carries_key_material(): void {
		$messages = array(
			GoogleAuth::token_error_message(
				400,
				array(
					'error'             => 'invalid_grant',
					'error_description' => 'Invalid JWT Signature.',
				)
			),
			GoogleAuth::token_error_message( 401, array( 'error' => 'invalid_client' ) ),
			GoogleAuth::token_error_message( 503, array() ),
			GoogleAuth::token_error_message( 418, array() ),
		);

		foreach ( $messages as $message ) {
			$this->assertNotSame( '', $message );
			$this->assertStringNotContainsString( 'PRIVATE KEY', $message );
			$this->assertStringNotContainsString( 'ya29.', $message );
			$this->assertStringNotContainsString( TestKey::private_pem(), $message );
		}
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
				return 'cURL error 28: Operation timed out';
			}
		};

		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn(
			array( 'service_account_json' => (string) wp_json_encode( TestKey::service_account() ) )
		);
		Functions\when( 'wp_remote_post' )->justReturn( $wp_error );
		Functions\when( 'is_wp_error' )->justReturn( true );

		$this->expectException( GoogleAuthException::class );
		$this->expectExceptionMessageMatches( '/could not reach Google.*Operation timed out/' );

		GoogleAuth::access_token();
	}

	/**
	 * Forgetting the token is what makes the next call sign in again.
	 */
	public function test_forgetting_the_token_deletes_the_transient(): void {
		Functions\expect( 'delete_transient' )->once()->with( 'gatb_google_access_token' );

		GoogleAuth::forget_token();

		$this->assertSame( 'gatb_google_access_token', GoogleAuth::TRANSIENT );
	}
}
