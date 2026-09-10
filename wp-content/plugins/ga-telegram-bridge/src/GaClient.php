<?php
/**
 * The minimal Google Analytics 4 Data API client.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge;

/**
 * Reads one GA4 property through the Data API, with one readable message per
 * way it can fail.
 *
 * The error shapes mapped here are the ones the Sprint 1 spike actually
 * received (docs/LEARNINGS.md, "Sprint 1 spike findings"); they are not the
 * shape the token endpoint uses, which GoogleAuth maps separately.
 */
final class GaClient {

	/**
	 * The Data API this client speaks to.
	 */
	private const ENDPOINT = 'https://analyticsdata.googleapis.com/v1beta';

	/**
	 * How long to wait for a report. The spike measured 610-923 ms.
	 */
	private const TIMEOUT = 20;

	/**
	 * Runs several reports in one call and returns them in request order.
	 *
	 * @param array<int, array<string, mixed>> $requests The report requests.
	 * @return array<int, array<string, mixed>>
	 * @throws GaClientException When the property, the account or Google refuses.
	 */
	public static function batch_run_reports( array $requests ): array {
		$body = self::request(
			'batchRunReports',
			array(
				'requests' => $requests,
			)
		);

		if ( ! isset( $body['reports'] ) || ! is_array( $body['reports'] ) ) {
			throw new GaClientException(
				esc_html__( 'Google Analytics answered without any report in it.', 'ga-telegram-bridge' )
			);
		}

		return array_values( $body['reports'] );
	}

	/**
	 * Asks the configured property for one row, to prove it can be read.
	 *
	 * The Data API is the only Google API this plugin needs; the reporting time
	 * zone it returns is what decides which day "yesterday" is, so it is worth
	 * showing to whoever configured the property.
	 *
	 * @return array{property_id: string, time_zone: string, currency_code: string}
	 * @throws GaClientException When the property, the account or Google refuses.
	 */
	public static function check_connection(): array {
		$body = self::request(
			'runReport',
			array(
				'metrics'    => array( array( 'name' => 'activeUsers' ) ),
				'dateRanges' => array(
					array(
						'startDate' => 'yesterday',
						'endDate'   => 'yesterday',
					),
				),
				'limit'      => 1,
			)
		);

		$metadata = isset( $body['metadata'] ) && is_array( $body['metadata'] ) ? $body['metadata'] : array();

		return array(
			'property_id'   => Settings::property_id(),
			'time_zone'     => isset( $metadata['timeZone'] ) && is_string( $metadata['timeZone'] ) ? $metadata['timeZone'] : '',
			'currency_code' => isset( $metadata['currencyCode'] ) && is_string( $metadata['currencyCode'] ) ? $metadata['currencyCode'] : '',
		);
	}

	/**
	 * Posts one Data API method for the configured property.
	 *
	 * @param string               $method  The method name after the colon.
	 * @param array<string, mixed> $payload The request body.
	 * @return array<string, mixed>
	 * @throws GaClientException When the property is unset, or the call fails.
	 */
	private static function request( string $method, array $payload ): array {
		$property_id = Settings::property_id();

		if ( '' === $property_id ) {
			throw new GaClientException(
				esc_html__( 'No GA4 property id is configured yet.', 'ga-telegram-bridge' )
			);
		}

		$response = wp_remote_post(
			self::ENDPOINT . '/properties/' . rawurlencode( $property_id ) . ':' . $method,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Authorization' => 'Bearer ' . GoogleAuth::access_token(),
					'Content-Type'  => 'application/json; charset=utf-8',
				),
				'body'    => (string) wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new GaClientException(
				sprintf(
					/* translators: %s: the reason the HTTP request failed. */
					esc_html__( 'This site could not reach Google Analytics: %s', 'ga-telegram-bridge' ),
					esc_html( $response->get_error_message() )
				)
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status ) {
			if ( 401 === $status ) {
				// The cached token is no longer accepted; the next run signs in again.
				GoogleAuth::forget_token();
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- client_error_message() escapes every value it takes from Google.
			throw new GaClientException( self::client_error_message( $status, $body ) );
		}

		if ( ! is_array( $body ) ) {
			throw new GaClientException(
				esc_html__( 'Google Analytics answered with something that is not a report.', 'ga-telegram-bridge' )
			);
		}

		return $body;
	}

	/**
	 * Turns a refused Data API call into a sentence about what to fix.
	 *
	 * The Data API answers `{"error": {"status": …, "message": …}}`, which is
	 * not the shape the token endpoint uses.
	 *
	 * @param int   $status The HTTP status.
	 * @param mixed $body   The decoded response body.
	 */
	public static function client_error_message( int $status, $body ): string {
		$error  = is_array( $body ) && isset( $body['error'] ) && is_array( $body['error'] ) ? $body['error'] : array();
		$state  = isset( $error['status'] ) && is_string( $error['status'] ) ? $error['status'] : '';
		$reason = isset( $error['message'] ) && is_string( $error['message'] ) ? $error['message'] : '';

		if ( 'INVALID_ARGUMENT' === $state || 400 === $status ) {
			return sprintf(
				/* translators: %s: the reason Google gave. */
				esc_html__( 'Google Analytics did not accept the request: %s Check the property id.', 'ga-telegram-bridge' ),
				esc_html( $reason )
			);
		}

		if ( 'PERMISSION_DENIED' === $state || 403 === $status ) {
			return sprintf(
				/* translators: %s: the service account's e-mail address. */
				esc_html__( 'The service account cannot read this property. In Google Analytics, give %s at least Viewer access to it — and check that the property id is the right one.', 'ga-telegram-bridge' ),
				esc_html( self::service_account_address() )
			);
		}

		if ( 'UNAUTHENTICATED' === $state || 401 === $status ) {
			return esc_html__( 'Google refused the access token. It has been discarded; try again, and check the service-account key if it happens twice.', 'ga-telegram-bridge' );
		}

		if ( 'RESOURCE_EXHAUSTED' === $state || 429 === $status ) {
			return esc_html__( 'Google Analytics is rate-limiting this project. Wait a while and try again; the daily report uses only a few requests.', 'ga-telegram-bridge' );
		}

		if ( $status >= 500 ) {
			return sprintf(
				/* translators: %d: the HTTP status Google answered with. */
				esc_html__( 'Google Analytics is not answering right now (HTTP %d). This is on Google\'s side; try again later.', 'ga-telegram-bridge' ),
				$status
			);
		}

		if ( '' === $reason ) {
			return sprintf(
				/* translators: %d: the HTTP status Google answered with. */
				esc_html__( 'Google Analytics refused the request (HTTP %d) and gave no reason.', 'ga-telegram-bridge' ),
				$status
			);
		}

		return sprintf(
			/* translators: 1: the HTTP status Google answered with, 2: the reason Google gave. */
			esc_html__( 'Google Analytics refused the request (HTTP %1$d): %2$s', 'ga-telegram-bridge' ),
			$status,
			esc_html( $reason )
		);
	}

	/**
	 * Returns the service account's address, which the user must grant access to.
	 *
	 * It identifies the account rather than authenticating it, so it is safe to
	 * show — unlike the key it belongs to.
	 */
	private static function service_account_address(): string {
		try {
			return GoogleAuth::parse_service_account( Settings::service_account_json() )['client_email'];
		} catch ( GoogleAuthException $exception ) {
			return __( 'the service account', 'ga-telegram-bridge' );
		}
	}
}
