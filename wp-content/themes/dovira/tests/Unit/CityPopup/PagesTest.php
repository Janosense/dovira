<?php
/**
 * Tests for dovira\CityPopup\Pages.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\CityPopup;

use Brain\Monkey\Functions;
use dovira\CityPopup\Pages;
use dovira\Tests\TestCase;

require_once dirname( __DIR__, 3 ) . '/inc/features/city-popup/Pages.php';

/**
 * The blog and the targets follow the request's language through Polylang,
 * and whatever does not exist is left out.
 */
final class PagesTest extends TestCase {

	private const HOME = 'https://dovira.ddev.site/';

	/** Ukrainian pages by slug, as the local install has them. */
	private const PAGES = [ 'news' => 211, 'services' => 12, 'contacts' => 219 ];

	/** Their Russian translations. */
	private const RU = [ 211 => 2211, 12 => 2012, 219 => 2219 ];

	private const PERMALINKS = [
		211  => 'https://dovira.ddev.site/news/',
		12   => 'https://dovira.ddev.site/services/',
		219  => 'https://dovira.ddev.site/contacts/',
		2211 => 'https://dovira.ddev.site/ru/blog/',
		2012 => 'https://dovira.ddev.site/ru/uslugi/',
		2219 => 'https://dovira.ddev.site/ru/kontakty/',
	];

	/** The request's language. */
	private string $lang = 'uk';

	/** @var array<string, int> */
	private array $pages = self::PAGES;

	/** @var array<int, int> */
	private array $ru = self::RU;

	/** The page being viewed; 0 for none. */
	private int $viewing = 0;

	private bool $viewing_post = false;

	private ?object $service_type;

	/** @var list<mixed> Every id is_page() was asked about. */
	private array $is_page_calls = [];

	protected function setUp(): void {
		parent::setUp();

		$this->service_type = (object) [ 'rewrite' => [ 'slug' => 'services', 'with_front' => false ] ];

		Functions\when( 'get_page_by_path' )->alias(
			fn( string $slug ) => isset( $this->pages[ $slug ] ) ? (object) [ 'ID' => $this->pages[ $slug ] ] : null
		);
		Functions\when( 'pll_get_post' )->alias(
			fn( int $id ) => 'uk' === $this->lang ? $id : ( $this->ru[ $id ] ?? false )
		);
		Functions\when( 'pll_home_url' )->alias(
			fn() => 'uk' === $this->lang ? self::HOME : self::HOME . 'ru/'
		);
		Functions\when( 'home_url' )->alias( fn( string $path = '' ) => rtrim( self::HOME, '/' ) . $path );
		Functions\when( 'get_permalink' )->alias( fn( int $id ) => self::PERMALINKS[ $id ] ?? false );
		Functions\when( 'get_post_type_object' )->alias(
			fn( string $type ) => 'service' === $type ? $this->service_type : null
		);
		Functions\when( 'is_singular' )->alias( fn( $type = '' ) => $this->viewing_post && 'post' === $type );
		Functions\when( 'is_page' )->alias(
			function ( $id = '' ) {
				$this->is_page_calls[] = $id;

				return $this->viewing > 0 && $id === $this->viewing;
			}
		);
	}

	public function test_the_blog_page_is_the_blog(): void {
		$this->viewing = 211;

		$this->assertTrue( ( new Pages( true ) )->is_blog() );
	}

	public function test_the_russian_blog_page_is_the_blog_on_a_russian_request(): void {
		$this->lang = 'ru';
		$this->viewing = 2211;

		$this->assertTrue( ( new Pages( true ) )->is_blog() );
	}

	public function test_an_article_is_the_blog(): void {
		$this->viewing_post = true;

		$this->assertTrue( ( new Pages( true ) )->is_blog() );
	}

	public function test_the_front_page_and_a_service_are_not_the_blog(): void {
		$this->viewing = 9;
		$front_page = ( new Pages( true ) )->is_blog();

		// A single service: not a page, not a post.
		$this->viewing = 0;
		$service = ( new Pages( true ) )->is_blog();

		$this->assertFalse( $front_page );
		$this->assertFalse( $service );
	}

	public function test_without_a_blog_page_nothing_asks_is_page_with_an_empty_id(): void {
		unset( $this->pages['news'] );
		$this->viewing = 9;

		$this->assertFalse( ( new Pages( true ) )->is_blog() );
		$this->assertSame( [], $this->is_page_calls );
	}

	public function test_a_blog_page_without_a_translation_is_not_the_blog(): void {
		$this->lang = 'ru';
		unset( $this->ru[211] );
		$this->viewing = 9;

		$this->assertFalse( ( new Pages( true ) )->is_blog() );
		$this->assertSame( [], $this->is_page_calls );
	}

	public function test_without_polylang_the_blog_page_itself_is_the_blog(): void {
		$this->viewing = 211;

		$this->assertTrue( ( new Pages( false ) )->is_blog() );
	}

	public function test_targets_in_ukrainian(): void {
		$this->assertSame(
			[
				'services' => 'https://dovira.ddev.site/services/',
				'contacts' => 'https://dovira.ddev.site/contacts/',
				'service'  => 'https://dovira.ddev.site/services/',
			],
			( new Pages( true ) )->targets()
		);
	}

	public function test_targets_in_russian(): void {
		$this->lang = 'ru';

		$this->assertSame(
			[
				'services' => 'https://dovira.ddev.site/ru/uslugi/',
				'contacts' => 'https://dovira.ddev.site/ru/kontakty/',
				'service'  => 'https://dovira.ddev.site/ru/services/',
			],
			( new Pages( true ) )->targets()
		);
	}

	public function test_a_missing_page_is_left_out(): void {
		unset( $this->pages['contacts'] );

		$this->assertSame(
			[
				'services' => 'https://dovira.ddev.site/services/',
				'service'  => 'https://dovira.ddev.site/services/',
			],
			( new Pages( true ) )->targets()
		);
	}

	public function test_a_page_without_a_translation_is_left_out(): void {
		$this->lang = 'ru';
		unset( $this->ru[12] );

		$this->assertSame(
			[
				'contacts' => 'https://dovira.ddev.site/ru/kontakty/',
				'service'  => 'https://dovira.ddev.site/ru/services/',
			],
			( new Pages( true ) )->targets()
		);
	}

	public function test_without_the_service_type_its_base_is_left_out(): void {
		$this->service_type = null;
		$without_type = ( new Pages( true ) )->targets();

		$this->service_type = (object) [ 'rewrite' => false ];
		$without_rewrite = ( new Pages( true ) )->targets();

		$this->assertArrayNotHasKey( 'service', $without_type );
		$this->assertArrayNotHasKey( 'service', $without_rewrite );
		$this->assertArrayHasKey( 'services', $without_type );
	}

	public function test_targets_without_polylang(): void {
		$this->assertSame(
			[
				'services' => 'https://dovira.ddev.site/services/',
				'contacts' => 'https://dovira.ddev.site/contacts/',
				'service'  => 'https://dovira.ddev.site/services/',
			],
			( new Pages( false ) )->targets()
		);
	}
}
