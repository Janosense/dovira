<?php
/**
 * Tests for dovira\CityPopup\Fallback.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\CityPopup;

use Brain\Monkey\Functions;
use dovira\CityPopup\Fallback;
use dovira\CityPopup\Pages;
use dovira\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname( __DIR__, 3 ) . '/inc/features/city-popup/Pages.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/city-popup/Fallback.php';

/**
 * Only a 404 under the service base of the request's language that carries
 * city-popup=1 is sent to the services page of that language.
 */
final class FallbackTest extends TestCase {

	private const HOME = 'https://dovira.ddev.site/';

	/** Ukrainian pages by slug, as the local install has them. */
	private const PAGES = [ 'services' => 12, 'contacts' => 219 ];

	/** Their Russian translations. */
	private const RU = [ 12 => 2012, 219 => 2219 ];

	private const PERMALINKS = [
		12   => 'https://dovira.ddev.site/services/',
		219  => 'https://dovira.ddev.site/contacts/',
		2012 => 'https://dovira.ddev.site/ru/uslugi/',
		2219 => 'https://dovira.ddev.site/ru/kontakty/',
	];

	private const MARKED = [ 'city-popup' => '1' ];

	/** The request's language. */
	private string $lang = 'uk';

	private bool $is_404 = true;

	/** @var array<string, int> */
	private array $pages = self::PAGES;

	private ?object $service_type;

	protected function setUp(): void {
		parent::setUp();

		$this->service_type = (object) [ 'rewrite' => [ 'slug' => 'services', 'with_front' => false ] ];

		Functions\when( 'is_404' )->alias( fn() => $this->is_404 );
		Functions\when( 'get_page_by_path' )->alias(
			fn( string $slug ) => isset( $this->pages[ $slug ] ) ? (object) [ 'ID' => $this->pages[ $slug ] ] : null
		);
		Functions\when( 'pll_get_post' )->alias(
			fn( int $id ) => 'uk' === $this->lang ? $id : ( self::RU[ $id ] ?? false )
		);
		Functions\when( 'pll_home_url' )->alias(
			fn() => 'uk' === $this->lang ? self::HOME : self::HOME . 'ru/'
		);
		Functions\when( 'get_permalink' )->alias( fn( int $id ) => self::PERMALINKS[ $id ] ?? false );
		Functions\when( 'get_post_type_object' )->alias(
			fn( string $type ) => 'service' === $type ? $this->service_type : null
		);
	}

	private function target( array $query, string $request_uri ): ?string {
		return ( new Fallback( new Pages( true ) ) )->target( $query, $request_uri );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function uk_service_paths(): array {
		return [
			'a service'     => [ '/services/no-such-service/?city-popup=1' ],
			'a sub-service' => [ '/services/surgery/no-such/?city-popup=1' ],
			'no slash'      => [ '/services/no-such-service?city-popup=1' ],
		];
	}

	#[DataProvider( 'uk_service_paths' )]
	public function test_a_marked_404_under_the_uk_service_base_goes_to_the_services_list( string $request_uri ): void {
		$this->assertSame( 'https://dovira.ddev.site/services/', $this->target( self::MARKED, $request_uri ) );
	}

	public function test_a_marked_404_under_the_ru_service_base_goes_to_the_russian_services_list(): void {
		$this->lang = 'ru';

		$this->assertSame(
			'https://dovira.ddev.site/ru/uslugi/',
			$this->target( self::MARKED, '/ru/services/no-such-service/?city-popup=1' )
		);
	}

	public function test_a_404_without_the_marker_stays(): void {
		$this->assertNull( $this->target( [], '/services/no-such-service/' ) );
		$this->assertNull( $this->target( [ 'other' => '1' ], '/services/no-such-service/?other=1' ) );
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function other_marker_values(): array {
		return [
			'0'        => [ '0' ],
			'true'     => [ 'true' ],
			'empty'    => [ '' ],
			'an array' => [ [ '1' ] ],
			'an int'   => [ 1 ],
		];
	}

	#[DataProvider( 'other_marker_values' )]
	public function test_a_marker_other_than_1_is_no_marker( mixed $value ): void {
		$this->assertNull( $this->target( [ 'city-popup' => $value ], '/services/no-such-service/' ) );
	}

	public function test_a_page_that_is_not_a_404_is_left_alone(): void {
		$this->is_404 = false;

		$this->assertNull( $this->target( self::MARKED, '/services/diagnostics/?city-popup=1' ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function paths_outside_the_service_base(): array {
		return [
			'another page'             => [ '/no-such-page/?city-popup=1' ],
			'an article'               => [ '/news/no-such/?city-popup=1' ],
			'a path that begins alike' => [ '/services-old/x/?city-popup=1' ],
			'the Russian base in uk'   => [ '/ru/services/no-such-service/?city-popup=1' ],
		];
	}

	#[DataProvider( 'paths_outside_the_service_base' )]
	public function test_a_marked_404_outside_the_service_base_stays( string $request_uri ): void {
		$this->assertNull( $this->target( self::MARKED, $request_uri ) );
	}

	public function test_a_uk_service_path_on_a_russian_request_stays(): void {
		$this->lang = 'ru';

		$this->assertNull( $this->target( self::MARKED, '/services/no-such-service/?city-popup=1' ) );
	}

	public function test_without_a_services_page_nothing_happens(): void {
		unset( $this->pages['services'] );

		$this->assertNull( $this->target( self::MARKED, '/services/no-such-service/?city-popup=1' ) );
	}

	public function test_without_a_service_base_nothing_happens(): void {
		$this->service_type = (object) [ 'rewrite' => false ];

		$this->assertNull( $this->target( self::MARKED, '/services/no-such-service/?city-popup=1' ) );
	}
}
