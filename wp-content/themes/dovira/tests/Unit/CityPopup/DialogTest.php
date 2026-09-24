<?php
/**
 * Tests for dovira\CityPopup\Dialog.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\CityPopup;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use DOMDocument;
use DOMElement;
use DOMXPath;
use dovira\CityPopup\Dialog;
use dovira\CityPopup\Pages;
use dovira\CityPopup\Sites;
use dovira\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname( __DIR__, 3 ) . '/inc/features/city-popup/Sites.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/city-popup/Pages.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/city-popup/Dialog.php';

/**
 * One closed <dialog> on a blog page, nothing elsewhere; every value it
 * carries is escaped, and every string is a Polylang string.
 */
final class DialogTest extends TestCase {

	private const HOME = 'https://dovira.ddev.site/';

	private const RU = [
		'Яке місто вас цікавить?' => 'Какой город вас интересует?',
		'Закрити'                 => 'Закрыть',
		'Харків'                  => 'Харьков',
		'Київ'                    => 'Киев',
	];

	private bool $on_blog = true;

	/** @var array<string, string> Polylang's string translations for the request. */
	private array $strings = [];

	/** @var array<int, string> */
	private array $permalinks = [
		12  => 'https://dovira.ddev.site/services/',
		219 => 'https://dovira.ddev.site/contacts/',
	];

	private string $home_url = 'https://dovira.ddev.site';

	protected function setUp(): void {
		parent::setUp();

		Functions\stubEscapeFunctions();
		Functions\when( 'wp_json_encode' )->alias( fn( $data, int $flags = 0 ) => json_encode( $data, $flags ) );
		Functions\when( 'dovira_translate_string' )->alias( fn( string $s ) => $this->strings[ $s ] ?? $s );

		// Pages runs for real on stubbed WordPress: a single article is the blog.
		Functions\when( 'is_singular' )->alias( fn( $type = '' ) => $this->on_blog && 'post' === $type );
		Functions\when( 'is_page' )->justReturn( false );
		Functions\when( 'get_page_by_path' )->alias(
			fn( string $slug ) => [ 'news' => (object) [ 'ID' => 211 ], 'services' => (object) [ 'ID' => 12 ], 'contacts' => (object) [ 'ID' => 219 ] ][ $slug ] ?? null
		);
		Functions\when( 'pll_get_post' )->returnArg();
		Functions\when( 'pll_home_url' )->justReturn( self::HOME );
		Functions\when( 'get_permalink' )->alias( fn( int $id ) => $this->permalinks[ $id ] ?? false );
		Functions\when( 'get_post_type_object' )->justReturn( (object) [ 'rewrite' => [ 'slug' => 'services' ] ] );
		Functions\when( 'home_url' )->alias( fn( string $path = '' ) => $this->home_url . $path );
	}

	private function render(): string {
		ob_start();
		( new Dialog( new Sites( 'https://dovira.ddev.site' ), new Pages( true ) ) )->render();

		return (string) ob_get_clean();
	}

	private static function dialog( string $html ): DOMElement {
		$document = new DOMDocument();
		$errors = libxml_use_internal_errors( true );
		// libxml's HTML parser predates <dialog>: it keeps the element and reports it as unknown.
		$document->loadHTML( '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $errors );

		$dialog = $document->getElementsByTagName( 'dialog' )->item( 0 );
		self::assertInstanceOf( DOMElement::class, $dialog );

		return $dialog;
	}

	/**
	 * @return list<DOMElement>
	 */
	private static function all( DOMElement $dialog, string $query ): array {
		$nodes = ( new DOMXPath( $dialog->ownerDocument ) )->query( $query, $dialog );

		return array_values( array_filter( iterator_to_array( $nodes ), fn( $node ) => $node instanceof DOMElement ) );
	}

	public function test_prints_nothing_off_the_blog(): void {
		$this->on_blog = false;

		$this->assertSame( '', $this->render() );
	}

	public function test_prints_one_closed_dialog_with_its_data_on_the_blog(): void {
		$html = $this->render();
		$dialog = self::dialog( $html );

		$this->assertSame( 1, substr_count( $html, '<dialog' ) );
		$this->assertFalse( $dialog->hasAttribute( 'open' ), 'Hidden until the browser module opens it.' );
		$this->assertTrue( $dialog->hasAttribute( 'data-city-popup' ) );
		$this->assertSame( 'city-popup-title', $dialog->getAttribute( 'aria-labelledby' ) );
		$this->assertSame( 'kharkiv', $dialog->getAttribute( 'data-current-city' ) );
		$this->assertSame( 'https://dovira.ddev.site', $dialog->getAttribute( 'data-kharkiv-origin' ) );
		$this->assertSame( 'https://kyiv.dovira.ddev.site', $dialog->getAttribute( 'data-kyiv-origin' ) );
		$this->assertSame( 'dovira.ddev.site', $dialog->getAttribute( 'data-cookie-domain' ) );
		$this->assertSame( '90', $dialog->getAttribute( 'data-days' ) );
		$this->assertSame(
			[
				'services' => 'https://dovira.ddev.site/services/',
				'contacts' => 'https://dovira.ddev.site/contacts/',
				'service'  => 'https://dovira.ddev.site/services/',
			],
			json_decode( $dialog->getAttribute( 'data-targets' ), true )
		);
	}

	public function test_the_title_the_two_cities_in_order_and_the_close_button(): void {
		$dialog = self::dialog( $this->render() );
		$title = self::all( $dialog, './/h2[@id="city-popup-title"]' );
		$cities = self::all( $dialog, './/button[@data-city-popup-choice]' );
		$close = self::all( $dialog, './/button[@data-city-popup-close]' );

		$this->assertCount( 1, $title );
		$this->assertSame( 'Яке місто вас цікавить?', $title[0]->textContent );

		$this->assertSame( [ 'kharkiv', 'kyiv' ], array_map( fn( $b ) => $b->getAttribute( 'data-city-popup-choice' ), $cities ) );
		$this->assertSame( [ 'Харків', 'Київ' ], array_map( fn( $b ) => $b->textContent, $cities ) );
		$this->assertSame( [ true, false ], array_map( fn( $b ) => $b->hasAttribute( 'autofocus' ), $cities ) );
		$this->assertSame( [], self::all( $dialog, './/*[@data-city]' ), 'data-city belongs to links the module saves on.' );

		$this->assertCount( 1, $close );
		$this->assertSame( 'Закрити', $close[0]->getAttribute( 'aria-label' ) );
		$this->assertSame( 'button', $close[0]->getAttribute( 'type' ) );
	}

	public function test_every_string_is_a_polylang_string(): void {
		$this->strings = self::RU;
		$dialog = self::dialog( $this->render() );

		$this->assertSame( 'Какой город вас интересует?', self::all( $dialog, './/h2' )[0]->textContent );
		$this->assertSame(
			[ 'Харьков', 'Киев' ],
			array_map( fn( $b ) => $b->textContent, self::all( $dialog, './/button[@data-city-popup-choice]' ) )
		);
		$this->assertSame( 'Закрыть', self::all( $dialog, './/button[@data-city-popup-close]' )[0]->getAttribute( 'aria-label' ) );
	}

	public function test_attribute_values_are_escaped(): void {
		$this->permalinks[12] = 'https://dovira.ddev.site/services/?a=1&b="x"';
		$html = $this->render();
		$dialog = self::dialog( $html );

		$this->assertStringNotContainsString( 'b="x"', $html );
		$this->assertStringNotContainsString( '"services":', $html, 'The JSON quotes are entities inside the attribute.' );
		$this->assertSame(
			'https://dovira.ddev.site/services/?a=1&b="x"',
			json_decode( $dialog->getAttribute( 'data-targets' ), true )['services']
		);
	}

	/**
	 * @return array<string, array{mixed, string}>
	 */
	public static function filtered_days(): array {
		return [
			'a shorter period'    => [ 30, '30' ],
			'zero'                => [ 0, '90' ],
			'negative'            => [ -5, '90' ],
			'not a number'        => [ 'abc', '90' ],
			'a numeric string'    => [ '45', '45' ],
		];
	}

	#[DataProvider( 'filtered_days' )]
	public function test_the_days_come_from_the_filter( mixed $filtered, string $printed ): void {
		Filters\expectApplied( 'dovira_city_popup_days' )->once()->with( Dialog::DEFAULT_DAYS )->andReturn( $filtered );

		$this->assertSame( $printed, self::dialog( $this->render() )->getAttribute( 'data-days' ) );
	}

	public function test_the_footer_callback_reads_the_site_url(): void {
		$this->home_url = 'https://kyiv.dovira.vet';

		ob_start();
		Dialog::print_on_blog();
		$dialog = self::dialog( (string) ob_get_clean() );

		$this->assertSame( 'kyiv', $dialog->getAttribute( 'data-current-city' ) );
		$this->assertSame( 'https://dovira.vet', $dialog->getAttribute( 'data-kharkiv-origin' ) );
		$this->assertSame( 'https://kyiv.dovira.vet', $dialog->getAttribute( 'data-kyiv-origin' ) );
		$this->assertSame( 'dovira.vet', $dialog->getAttribute( 'data-cookie-domain' ) );
	}
}
