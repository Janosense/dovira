<?php

namespace dovira\CityPopup;

/**
 * Which page is the blog, and which URLs are the question's targets, in the
 * current language (DECISIONS "The blog, services and contacts pages are found
 * by their Ukrainian slugs and Polylang translations").
 *
 * The pages are found by their Ukrainian slugs and then through Polylang, so a
 * Russian request gets /ru/blog/, /ru/uslugi/ and /ru/kontakty/. The service
 * base is the service type's rewrite slug under the language's home URL: the
 * type has no archive, and its slug is the same in both languages.
 */
final class Pages {

	public const BLOG_SLUG = 'news';
	public const SERVICES_SLUG = 'services';
	public const CONTACTS_SLUG = 'contacts';

	private const SERVICE_TYPE = 'service';

	private bool $polylang;

	/**
	 * @param bool|null $polylang Whether Polylang's API is there; null asks.
	 *                            Tests pass false, since a function stubbed
	 *                            once stays declared for the whole run.
	 */
	public function __construct( ?bool $polylang = null ) {
		$this->polylang = $polylang ?? function_exists( 'pll_get_post' );
	}

	/**
	 * A single article, or the blog page in the current language.
	 */
	public function is_blog(): bool {
		if ( is_singular( 'post' ) ) {
			return true;
		}

		$blog = $this->page_id( self::BLOG_SLUG );

		// is_page() with an empty id is true on any page.
		return $blog > 0 && is_page( $blog );
	}

	/**
	 * The URLs a click on the blog is asked about, keyed by what they are; one
	 * that does not exist in the current language is left out.
	 *
	 * @return array<string, string>
	 */
	public function targets(): array {
		$targets = [];

		foreach ( [ 'services' => self::SERVICES_SLUG, 'contacts' => self::CONTACTS_SLUG ] as $key => $slug ) {
			$id = $this->page_id( $slug );
			$url = $id > 0 ? get_permalink( $id ) : false;

			if ( is_string( $url ) && '' !== $url ) {
				$targets[ $key ] = $url;
			}
		}

		$service_base = $this->service_base();

		if ( null !== $service_base ) {
			$targets['service'] = $service_base;
		}

		return $targets;
	}

	/**
	 * The id of the page at the Ukrainian slug in the current language, or 0.
	 */
	private function page_id( string $slug ): int {
		$page = get_page_by_path( $slug );

		if ( ! is_object( $page ) ) {
			return 0;
		}

		if ( ! $this->polylang ) {
			return (int) $page->ID;
		}

		return (int) pll_get_post( (int) $page->ID );
	}

	private function service_base(): ?string {
		$type = get_post_type_object( self::SERVICE_TYPE );
		$slug = is_object( $type ) && is_array( $type->rewrite ) ? (string) ( $type->rewrite['slug'] ?? '' ) : '';

		if ( '' === $slug ) {
			return null;
		}

		$home = $this->polylang ? pll_home_url() : home_url( '/' );

		return rtrim( $home, '/' ) . '/' . $slug . '/';
	}
}
