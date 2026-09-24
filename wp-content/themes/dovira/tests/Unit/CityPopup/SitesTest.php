<?php
/**
 * Tests for dovira\CityPopup\Sites.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\CityPopup;

use dovira\CityPopup\Sites;
use dovira\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname( __DIR__, 3 ) . '/inc/features/city-popup/Sites.php';

/**
 * Every install derives the same pair of origins and the same cookie domain
 * from its own URL: production, dev and DDEV alike.
 */
final class SitesTest extends TestCase {

	/**
	 * @return array<string, array{string, string, string, string, string}>
	 */
	public static function urls(): array {
		return [
			'Kharkiv production'   => [ 'https://dovira.vet', 'kharkiv', 'https://dovira.vet', 'https://kyiv.dovira.vet', 'dovira.vet' ],
			'Kyiv production'      => [ 'https://kyiv.dovira.vet', 'kyiv', 'https://dovira.vet', 'https://kyiv.dovira.vet', 'dovira.vet' ],
			'dev'                  => [ 'https://dev.dovira.vet', 'kharkiv', 'https://dev.dovira.vet', 'https://kyiv.dev.dovira.vet', 'dev.dovira.vet' ],
			'DDEV'                 => [ 'https://dovira.ddev.site', 'kharkiv', 'https://dovira.ddev.site', 'https://kyiv.dovira.ddev.site', 'dovira.ddev.site' ],
			'a path stays out'     => [ 'https://kyiv.dovira.ddev.site/some/path/', 'kyiv', 'https://dovira.ddev.site', 'https://kyiv.dovira.ddev.site', 'dovira.ddev.site' ],
			'a port stays in'      => [ 'https://dovira.ddev.site:8443', 'kharkiv', 'https://dovira.ddev.site:8443', 'https://kyiv.dovira.ddev.site:8443', 'dovira.ddev.site' ],
			'scheme kept, host lowercased' => [ 'http://Dovira.DDEV.site', 'kharkiv', 'http://dovira.ddev.site', 'http://kyiv.dovira.ddev.site', 'dovira.ddev.site' ],
		];
	}

	#[DataProvider( 'urls' )]
	public function test_derives_the_city_both_origins_and_the_cookie_domain(
		string $url,
		string $city,
		string $kharkiv_origin,
		string $kyiv_origin,
		string $cookie_domain
	): void {
		$sites = new Sites( $url );

		$this->assertSame( $city, $sites->city() );
		$this->assertSame( $kharkiv_origin, $sites->kharkiv_origin() );
		$this->assertSame( $kyiv_origin, $sites->kyiv_origin() );
		$this->assertSame( $cookie_domain, $sites->cookie_domain() );
	}
}
