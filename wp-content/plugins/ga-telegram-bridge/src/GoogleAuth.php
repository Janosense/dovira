<?php
/**
 * Signing in to Google with the service-account key.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge;

/**
 * Builds and signs the JWT, exchanges it for an access token and caches it.
 *
 * Everything here follows what the Sprint 1 spike observed against the real
 * endpoint (docs/LEARNINGS.md, "Sprint 1 spike findings"), in particular: the
 * private key needs no newline repair, and the token endpoint answers with its
 * own error shape, not the Data API's.
 */
final class GoogleAuth {

	/**
	 * Where the access token is cached between runs.
	 */
	public const TRANSIENT = 'gatb_google_access_token';

	/**
	 * The read-only scope the reports need.
	 */
	public const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

	/**
	 * Where the JWT is exchanged when the key file names no other endpoint.
	 */
	private const TOKEN_URI = 'https://oauth2.googleapis.com/token';

	/**
	 * How long the JWT is valid. Google refuses more than an hour.
	 */
	private const LIFETIME = 3600;

	/**
	 * How long before the token expires it is treated as gone.
	 */
	private const EXPIRY_MARGIN = 60;

	/**
	 * How long to wait for the token endpoint. The spike measured 185-356 ms.
	 */
	private const TIMEOUT = 15;

	/**
	 * Returns an access token, from the cache when there is one.
	 *
	 * @throws GoogleAuthException When the key is unusable or Google refuses it.
	 */
	public static function access_token(): string {
		$cached = get_transient( self::TRANSIENT );

		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$key = self::parse_service_account( Settings::service_account_json() );

		$response = wp_remote_post(
			$key['token_uri'],
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => self::build_jwt( $key, time() ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new GoogleAuthException(
				sprintf(
					/* translators: %s: the reason the HTTP request failed. */
					esc_html__( 'This site could not reach Google to sign in: %s', 'ga-telegram-bridge' ),
					esc_html( $response->get_error_message() )
				)
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status || ! is_array( $body ) || ! isset( $body['access_token'] ) || ! is_string( $body['access_token'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- token_error_message() escapes every value it takes from Google.
			throw new GoogleAuthException( self::token_error_message( $status, $body ) );
		}

		$expires_in = isset( $body['expires_in'] ) && is_numeric( $body['expires_in'] )
			? (int) $body['expires_in']
			: self::LIFETIME;

		set_transient(
			self::TRANSIENT,
			$body['access_token'],
			max( self::EXPIRY_MARGIN, $expires_in - self::EXPIRY_MARGIN )
		);

		return $body['access_token'];
	}

	/**
	 * Drops the cached token, so the next call signs in again.
	 */
	public static function forget_token(): void {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * Reads the three fields the sign-in needs out of the key file.
	 *
	 * The private key is used exactly as JSON gave it: json_decode() already
	 * turns \n into real newlines, and the usual newline repair would corrupt
	 * the key (spike finding).
	 *
	 * @param string $json The service-account key file.
	 * @return array{client_email: string, private_key: string, token_uri: string}
	 * @throws GoogleAuthException When the file is missing or unusable.
	 */
	public static function parse_service_account( string $json ): array {
		if ( '' === trim( $json ) ) {
			throw new GoogleAuthException(
				esc_html__( 'No Google service-account key is configured yet.', 'ga-telegram-bridge' )
			);
		}

		$decoded = json_decode( $json, true );

		if ( ! is_array( $decoded ) ) {
			throw new GoogleAuthException(
				esc_html__( 'The service-account key is not valid JSON. Paste the key file Google generated, unchanged.', 'ga-telegram-bridge' )
			);
		}

		$key = array(
			'client_email' => '',
			'private_key'  => '',
			'token_uri'    => self::TOKEN_URI,
		);

		foreach ( array( 'client_email', 'private_key' ) as $field ) {
			if ( ! isset( $decoded[ $field ] ) || ! is_string( $decoded[ $field ] ) || '' === trim( $decoded[ $field ] ) ) {
				throw new GoogleAuthException(
					sprintf(
						/* translators: %s: the name of the missing field in the key file. */
						esc_html__( 'The service-account key has no %s in it. Paste the key file Google generated, unchanged.', 'ga-telegram-bridge' ),
						esc_html( $field )
					)
				);
			}

			$key[ $field ] = $decoded[ $field ];
		}

		if ( isset( $decoded['token_uri'] ) && is_string( $decoded['token_uri'] ) && '' !== trim( $decoded['token_uri'] ) ) {
			$key['token_uri'] = $decoded['token_uri'];
		}

		return $key;
	}

	/**
	 * Builds the signed JWT that is exchanged for an access token.
	 *
	 * @param array{client_email: string, private_key: string, token_uri: string} $key The parsed key file.
	 * @param int                                                                 $now The current Unix time.
	 * @throws GoogleAuthException When the private key cannot sign.
	 */
	public static function build_jwt( array $key, int $now ): string {
		$header = array(
			'alg' => 'RS256',
			'typ' => 'JWT',
		);

		$claims = array(
			'iss'   => $key['client_email'],
			'scope' => self::SCOPE,
			'aud'   => $key['token_uri'],
			'iat'   => $now,
			'exp'   => $now + self::LIFETIME,
		);

		$signing_input = self::base64url( (string) wp_json_encode( $header ) )
			. '.' . self::base64url( (string) wp_json_encode( $claims ) );

		$private_key = openssl_pkey_get_private( $key['private_key'] );

		if ( false === $private_key ) {
			throw new GoogleAuthException(
				esc_html__( 'The private key inside the service-account file could not be read. Paste the key file Google generated, unchanged.', 'ga-telegram-bridge' )
			);
		}

		$signature = '';

		if ( ! openssl_sign( $signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256 ) ) {
			throw new GoogleAuthException(
				esc_html__( 'This server could not sign the Google request with the service-account key.', 'ga-telegram-bridge' )
			);
		}

		return $signing_input . '.' . self::base64url( $signature );
	}

	/**
	 * Turns a refused token exchange into a sentence about what to fix.
	 *
	 * The token endpoint answers `{"error": …, "error_description": …}` — the
	 * Data API's shape is different and is mapped in GaClient.
	 *
	 * @param int   $status The HTTP status.
	 * @param mixed $body   The decoded response body.
	 */
	public static function token_error_message( int $status, $body ): string {
		$error  = is_array( $body ) && isset( $body['error'] ) && is_string( $body['error'] ) ? $body['error'] : '';
		$reason = is_array( $body ) && isset( $body['error_description'] ) && is_string( $body['error_description'] )
			? $body['error_description']
			: $error;

		if ( 'invalid_grant' === $error ) {
			return sprintf(
				/* translators: %s: the reason Google gave. */
				esc_html__( 'Google refused the service-account key: %s Check that the key is the current one for this service account and that the server clock is correct.', 'ga-telegram-bridge' ),
				esc_html( $reason )
			);
		}

		if ( 'invalid_client' === $error ) {
			return __( 'Google does not know this service account any more. It may have been deleted; create a new key and paste it here.', 'ga-telegram-bridge' );
		}

		if ( $status >= 500 ) {
			return sprintf(
				/* translators: %d: the HTTP status Google answered with. */
				esc_html__( 'Google could not issue an access token right now (HTTP %d). This is on Google\'s side; try again later.', 'ga-telegram-bridge' ),
				$status
			);
		}

		if ( '' === $reason ) {
			return sprintf(
				/* translators: %d: the HTTP status Google answered with. */
				esc_html__( 'Google refused to issue an access token (HTTP %d) and gave no reason.', 'ga-telegram-bridge' ),
				$status
			);
		}

		return sprintf(
			/* translators: 1: the HTTP status Google answered with, 2: the reason Google gave. */
			esc_html__( 'Google refused to issue an access token (HTTP %1$d): %2$s', 'ga-telegram-bridge' ),
			$status,
			esc_html( $reason )
		);
	}

	/**
	 * Encodes a string the way JWT wants it: base64, URL alphabet, no padding.
	 *
	 * @param string $raw The bytes to encode.
	 */
	private static function base64url( string $raw ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64url is how a JWT is encoded, not obfuscation.
		return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
	}
}
