<?php
/**
 * Tests for dovira\ContentMcp\PostReader: one post in the connector's shape.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\ContentMcp;

use Brain\Monkey\Functions;
use dovira\ContentMcp\PostReader;
use dovira\Tests\TestCase;

require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/Scope.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/DescribeSite.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/FieldValues.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/CityPrice.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/PostReader.php';

/**
 * ACF by name, the price in this city, blocks by name, Polylang links.
 */
final class PostReaderTest extends TestCase {

	/** @var list<array{0: array<string, mixed>, 1: string}> */
	private array $setups = [];

	/** @var list<string> */
	private array $resets = [];

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'get_object_taxonomies' )->justReturn( [] );
		Functions\when( 'is_post_type_viewable' )->alias( static fn( string $type ) => ! in_array( $type, [ 'conversation', 'application' ], true ) );
		Functions\when( 'get_permalink' )->alias( static fn( int $id ) => "https://dovira.vet/?p={$id}" );
		Functions\when( 'get_site_url' )->justReturn( 'https://dovira.vet' );
		Functions\when( 'get_term_by' )->justReturn( (object) [ 'term_id' => 4 ] );
		Functions\when( 'wp_json_encode' )->alias( static fn( $v ) => json_encode( $v ) );
		Functions\when( 'acf_setup_meta' )->alias(
			function ( array $data, string $id ) {
				$this->setups[] = [ $data, $id ];
			}
		);
		Functions\when( 'acf_reset_meta' )->alias(
			function ( string $id ) {
				$this->resets[] = $id;
			}
		);
		Functions\when( 'parse_blocks' )->justReturn( [] );
	}

	public function test_the_post_shape_with_polylang_translations(): void {
		Functions\when( 'get_field_objects' )->justReturn( false );
		Functions\when( 'pll_is_translated_post_type' )->justReturn( true );
		Functions\when( 'pll_get_post_language' )->justReturn( 'uk' );
		Functions\when( 'pll_get_post_translations' )->justReturn( [ 'uk' => 18, 'ru' => '1641' ] );

		$read = ( new PostReader( true, true ) )->read( self::post( 18, 'service' ) );

		$this->assertSame( 18, $read['id'] );
		$this->assertSame( 'service', $read['type'] );
		$this->assertSame( 'uk', $read['lang'] );
		$this->assertEquals( (object) [ 'uk' => 18, 'ru' => 1641 ], $read['translations'] );
		$this->assertSame( 'https://dovira.vet/?p=18', $read['link'] );
		$this->assertEquals( new \stdClass(), $read['acf'] );
		$this->assertSame( [], $read['blocks'] );
	}

	public function test_a_service_price_is_the_price_in_this_city_and_the_columns_are_gone(): void {
		Functions\when( 'get_field_objects' )->justReturn(
			[
				'prices' => [
					'name'       => 'prices',
					'type'       => 'repeater',
					'sub_fields' => [],
					'value'      => [
						[ 'title' => 'Прийом', 'price' => '', 'city_prices' => [ 'price_city_4' => '550' ], 'cities' => [ 'city_4' ], 'is_price_general' => false ],
						[ 'title' => 'Аналіз', 'price' => '', 'city_prices' => [ 'price_city_4' => '' ], 'cities' => [ 'city_4' ], 'is_price_general' => false ],
					],
				],
			]
		);

		$prices = ( new PostReader( true, false ) )->read( self::post( 18, 'service' ) )['acf']->prices;

		$this->assertSame( 550, $prices[0]['price'] );
		$this->assertNull( $prices[1]['price'] );
		$this->assertArrayNotHasKey( 'city_prices', $prices[0] );
		$this->assertSame( [ 'city_4' ], $prices[0]['cities'] );
	}

	public function test_another_type_keeps_a_prices_field_as_acf_returns_it(): void {
		$value = [ [ 'price' => '', 'city_prices' => [ 'price_city_4' => '1' ] ] ];
		Functions\when( 'get_field_objects' )->justReturn( [ 'prices' => [ 'name' => 'prices', 'type' => 'repeater', 'value' => $value ] ] );

		$this->assertSame( $value, ( new PostReader( true, false ) )->read( self::post( 3, 'page' ) )['acf']->prices );
	}

	public function test_an_acf_block_is_read_by_name_through_acfs_block_meta(): void {
		$data = [
			'heading'        => 'Про нас',
			'_heading'       => 'field_about_heading',
			'years'          => 1,
			'_years'         => 'field_about_years',
			'years_0_year'   => '2014',
			'_years_0_year'  => 'field_about_years_year',
		];
		Functions\when( 'parse_blocks' )->justReturn( [ [ 'blockName' => 'acf/about', 'attrs' => [ 'name' => 'acf/about', 'data' => $data ] ] ] );
		Functions\when( 'get_field_objects' )->alias(
			static fn( $id ) => is_string( $id ) && str_starts_with( $id, 'block_' ) ? [
				'heading' => [ 'name' => 'heading', 'type' => 'text', 'value' => 'Про нас' ],
				'years'   => [ 'name' => 'years', 'type' => 'repeater', 'sub_fields' => [], 'value' => [ [ 'year' => '2014' ] ] ],
			] : false
		);

		$blocks = ( new PostReader( true, false ) )->read( self::post( 176, 'page', '<!-- wp:acf/about /-->' ) )['blocks'];

		$this->assertSame( 'acf/about', $blocks[0]['block'] );
		$this->assertSame( 'Про нас', $blocks[0]['fields']->heading );
		$this->assertSame( '2014', $blocks[0]['fields']->years[0]['year'] );
		$this->assertSame( $data, $this->setups[0][0] );
		$this->assertSame( [ $this->setups[0][1] ], $this->resets, 'the block meta is reset after reading' );
	}

	public function test_a_blog_post_made_of_acf_blocks_is_read_as_blocks_too(): void {
		Functions\when( 'get_field_objects' )->alias(
			static fn( $id ) => is_string( $id ) ? [ 'text' => [ 'name' => 'text', 'type' => 'wysiwyg', 'value' => '<p>Текст</p>' ] ] : false
		);
		Functions\when( 'parse_blocks' )->justReturn( [ [ 'blockName' => 'acf/rich-text', 'attrs' => [ 'data' => [ 'text' => '<p>Текст</p>', '_text' => 'field_rich-text_text' ] ] ] ] );

		$read = ( new PostReader( true, false ) )->read( self::post( 1498, 'post', '<!-- wp:acf/rich-text /-->' ) );

		$this->assertArrayNotHasKey( 'content_html', $read );
		$this->assertSame( '<p>Текст</p>', $read['blocks'][0]['fields']->text );
	}

	public function test_core_blocks_keep_their_markup_and_freeform_text_is_core_freeform(): void {
		Functions\when( 'get_field_objects' )->justReturn( false );
		$paragraph = [ 'blockName' => 'core/paragraph', 'attrs' => [], 'innerHTML' => '<p>Так</p>' ];
		Functions\when( 'parse_blocks' )->justReturn(
			[
				$paragraph,
				[ 'blockName' => null, 'attrs' => [], 'innerHTML' => "\n\n" ],
				[ 'blockName' => null, 'attrs' => [], 'innerHTML' => '<p>Старий текст</p>' ],
			]
		);
		Functions\when( 'serialize_block' )->justReturn( "<!-- wp:paragraph -->\n<p>Так</p>\n<!-- /wp:paragraph -->" );

		$blocks = ( new PostReader( true, false ) )->read( self::post( 7, 'post', 'x' ) )['blocks'];

		$this->assertSame(
			[
				[ 'block' => 'core/paragraph', 'html' => "<!-- wp:paragraph -->\n<p>Так</p>\n<!-- /wp:paragraph -->" ],
				[ 'block' => 'core/freeform', 'html' => '<p>Старий текст</p>' ],
			],
			$blocks
		);
	}

	public function test_a_record_has_no_language_no_translations_and_no_link(): void {
		Functions\when( 'get_field_objects' )->justReturn( [ 'phone' => [ 'name' => 'phone', 'type' => 'text', 'value' => '(050) 000' ] ] );
		Functions\when( 'pll_is_translated_post_type' )->justReturn( false );

		$read = ( new PostReader( true, true ) )->read( self::post( 900, 'conversation' ) );

		$this->assertNull( $read['lang'] );
		$this->assertEquals( new \stdClass(), $read['translations'] );
		$this->assertNull( $read['link'] );
		$this->assertSame( '(050) 000', $read['acf']->phone );
	}

	public function test_terms_of_the_types_taxonomies_in_scope(): void {
		Functions\when( 'get_field_objects' )->justReturn( false );
		Functions\when( 'get_object_taxonomies' )->justReturn( [ 'category', 'post_tag', 'post_format' ] );
		Functions\when( 'wp_get_post_terms' )->alias(
			static fn( int $id, string $tax ) => 'category' === $tax ? [ (object) [ 'term_id' => 1, 'name' => 'Новини' ] ] : []
		);

		$terms = ( new PostReader( true, false ) )->read( self::post( 7, 'post' ) )['terms'];

		$this->assertEquals( (object) [ 'category' => [ [ 'id' => 1, 'name' => 'Новини' ] ], 'post_tag' => [] ], $terms );
	}

	public function test_without_acf_there_are_no_values_and_block_fields_are_empty(): void {
		Functions\when( 'parse_blocks' )->justReturn( [ [ 'blockName' => 'acf/hero', 'attrs' => [ 'data' => [ 'heading' => 'x' ] ] ] ] );

		$read = ( new PostReader( false, false ) )->read( self::post( 3, 'page', 'x' ) );

		$this->assertEquals( new \stdClass(), $read['acf'] );
		$this->assertEquals( new \stdClass(), $read['blocks'][0]['fields'] );
		$this->assertSame( [], $this->setups );
	}

	private static function post( int $id, string $type, string $content = '' ): object {
		return (object) [
			'ID'            => $id,
			'post_type'     => $type,
			'post_status'   => 'publish',
			'post_title'    => "Post {$id}",
			'post_name'     => "post-{$id}",
			'post_parent'   => 0,
			'menu_order'    => 0,
			'post_modified' => '2026-11-05 10:00:00',
			'post_content'  => $content,
		];
	}
}
