<?php

namespace dovira\CityPopup;

/**
 * A service the chosen install does not have leads to its services list
 * (DECISIONS "A service missing on the chosen install falls back to its
 * services list through a marker in the URL").
 *
 * Every move the browser module makes to the other install carries the
 * marker city-popup=1. On the install that receives it, a 404 under the
 * service base of the request's language that carries the marker gets a 302
 * to the services page in that language. Any other 404 stays a 404, so a
 * crawler or a stale link never gets a redirect. The decision rests on the URL
 * alone; no cookie is read.
 *
 * It runs after WordPress's own 404 redirects on template_redirect (the old
 * slug of a renamed service, the guess of a longer slug), so it answers only
 * what would otherwise be shown as a 404 page.
 */
final class Fallback {

	/** The query marker; the JS half is MARKER in source/scripts/features/city-popup/logic.js. */
	public const MARKER = 'city-popup';

	public function __construct( private Pages $pages ) {
	}

	/**
	 * The template_redirect callback.
	 */
	public static function redirect(): void {
		$url = ( new self( new Pages() ) )->target( $_GET, (string) ( $_SERVER['REQUEST_URI'] ?? '' ) );

		if ( null !== $url ) {
			wp_safe_redirect( $url, 302 );
			exit;
		}
	}

	/**
	 * The services page to send this request to, or null to leave it alone.
	 *
	 * @param array<mixed> $query       The request's query arguments.
	 * @param string       $request_uri The request's path and query.
	 */
	public function target( array $query, string $request_uri ): ?string {
		if ( ! is_404() || '1' !== ( $query[ self::MARKER ] ?? null ) ) {
			return null;
		}

		$targets = $this->pages->targets();

		if ( ! isset( $targets['services'], $targets['service'] ) ) {
			return null;
		}

		$path = parse_url( $request_uri, PHP_URL_PATH );
		$base = parse_url( $targets['service'], PHP_URL_PATH );

		if ( ! is_string( $path ) || ! is_string( $base ) ) {
			return null;
		}

		// "/services/x" counts like "/services/x/", as isTarget() compares them.
		return str_starts_with( self::with_slash( $path ), self::with_slash( $base ) ) ? $targets['services'] : null;
	}

	private static function with_slash( string $path ): string {
		return str_ends_with( $path, '/' ) ? $path : $path . '/';
	}
}
