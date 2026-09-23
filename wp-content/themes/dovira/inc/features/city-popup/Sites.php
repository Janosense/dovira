<?php

namespace dovira\CityPopup;

/**
 * The two city installs as this install sees them, derived from its own site
 * URL and nothing else (DECISIONS "The other install's host and the cookie
 * domain are derived from the site URL"; root invariant 4).
 *
 * The Kharkiv host is the own host without a leading "kyiv."; the Kyiv host is
 * "kyiv." + the Kharkiv host; the cookie both installs share is scoped to the
 * Kharkiv host. So dovira.vet / kyiv.dovira.vet in production, and dev or DDEV
 * pair up with a Kyiv host of their own and never write production's cookie.
 *
 * Pure: it parses the URL it is given (home_url() at the call site).
 */
final class Sites {

	public const KHARKIV = 'kharkiv';
	public const KYIV = 'kyiv';

	private const KYIV_PREFIX = 'kyiv.';

	private string $url;
	private string $scheme;
	private string $kharkiv_host;
	private string $port;

	public function __construct( string $url ) {
		$parts = parse_url( $url );
		$host = strtolower( (string) ( $parts['host'] ?? '' ) );

		$this->url = $url;
		$this->scheme = strtolower( (string) ( $parts['scheme'] ?? 'https' ) );
		$this->kharkiv_host = str_starts_with( $host, self::KYIV_PREFIX ) ? substr( $host, strlen( self::KYIV_PREFIX ) ) : $host;
		$this->port = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
	}

	/**
	 * The city of this install: Kyiv when the site URL contains "kyiv", as the
	 * header switcher decides it.
	 */
	public function city(): string {
		return str_contains( $this->url, self::KYIV ) ? self::KYIV : self::KHARKIV;
	}

	/**
	 * Scheme, Kharkiv host and port; the site URL's path is never part of it.
	 */
	public function kharkiv_origin(): string {
		return $this->scheme . '://' . $this->kharkiv_host . $this->port;
	}

	public function kyiv_origin(): string {
		return $this->scheme . '://' . self::KYIV_PREFIX . $this->kharkiv_host . $this->port;
	}

	/**
	 * The Domain of the city cookies: the Kharkiv host, which kyiv.* is under.
	 * A cookie domain carries no port.
	 */
	public function cookie_domain(): string {
		return $this->kharkiv_host;
	}
}
