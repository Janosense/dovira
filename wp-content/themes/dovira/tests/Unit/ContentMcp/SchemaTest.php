<?php
/**
 * Tests for dovira\ContentMcp\Schema: the content model describe-site returns.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\ContentMcp;

use Brain\Monkey\Functions;
use dovira\ContentMcp\Schema;
use dovira\Tests\TestCase;

require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/Scope.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/Schema.php';

/**
 * Types and blocks are described from the ACF local field groups; the service
 * price columns per city are hidden; terms are capped.
 */
final class SchemaTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'get_post_type_object' )->alias(
			static fn( string $type ) => in_array( $type, [ 'service', 'page', 'conversation' ], true )
				? (object) [ 'label' => ucfirst( $type ), 'hierarchical' => 'page' === $type ]
				: null
		);
		Functions\when( 'get_object_taxonomies' )->alias(
			static fn( string $type ) => 'service' === $type ? [ 'service-city', 'some_other' ] : []
		);
		Functions\when( 'acf_get_local_field_groups' )->justReturn( self::field_groups() );
		Functions\when( 'acf_get_fields' )->alias( static fn( string $key ) => self::fields()[ $key ] ?? [] );
		Functions\when( 'acf_get_block_types' )->justReturn(
			[
				'acf/about' => [ 'name' => 'acf/about', 'title' => 'About' ],
				'core/x'    => [ 'name' => 'core/x', 'title' => 'Not ours' ],
			]
		);
		Functions\when( 'get_taxonomy' )->justReturn( false );
	}

	public function test_each_type_in_scope_is_described_with_the_fields_located_on_it(): void {
		$types = ( new Schema( true, false ) )->post_types();

		$this->assertSame( [ 'service', 'page', 'conversation' ], array_column( $types, 'name' ) );
		$page = $types[1];
		$this->assertTrue( $page['hierarchical'] );
		$this->assertSame( [ 'subtitle' ], array_column( $page['fields'], 'name' ) );
		$this->assertTrue( $types[2]['read_only'] );
		$this->assertFalse( $types[0]['read_only'] );
		$this->assertSame( [ 'service-city' ], $types[0]['taxonomies'] );
		$this->assertFalse( $types[0]['translated'] );
	}

	public function test_ui_only_fields_are_dropped_and_choices_are_kept(): void {
		$fields = ( new Schema( true, false ) )->post_types()[0]['fields'];

		$this->assertSame( [ 'image', 'prices' ], array_column( $fields, 'name' ) );
		$this->assertTrue( $fields[0]['required'] );
	}

	public function test_the_service_price_columns_per_city_are_hidden_and_price_means_this_city(): void {
		$prices = ( new Schema( true, false ) )->post_types()[0]['fields'][1];

		$this->assertSame( [ 'title', 'price', 'cities', 'is_price_general' ], array_column( $prices['sub_fields'], 'name' ) );
		$this->assertSame( Schema::CITY_PRICE_DESCRIPTION, $prices['sub_fields'][1]['description'] );
		$this->assertSame( [ 'city_4' => 'Харків' ], $prices['sub_fields'][2]['choices'] );
	}

	public function test_the_options_page_group_is_never_described(): void {
		$schema = new Schema( true, false );

		$names = [];
		foreach ( $schema->post_types() as $type ) {
			$names = array_merge( $names, array_column( $type['fields'], 'name' ) );
		}
		foreach ( $schema->blocks() as $block ) {
			$names = array_merge( $names, array_column( $block['fields'], 'name' ) );
		}

		$this->assertNotContains( 'contacts_cities', $names );
	}

	public function test_only_acf_blocks_are_described_with_their_fields(): void {
		$blocks = ( new Schema( true, false ) )->blocks();

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'acf/about', $blocks[0]['name'] );
		$this->assertSame( [ 'heading', 'years' ], array_column( $blocks[0]['fields'], 'name' ) );
		$this->assertSame( [ 'year', 'title' ], array_column( $blocks[0]['fields'][1]['sub_fields'], 'name' ) );
	}

	public function test_without_acf_there_are_no_fields_and_no_blocks(): void {
		$schema = new Schema( false, false );

		$this->assertSame( [], $schema->blocks() );
		foreach ( $schema->post_types() as $type ) {
			$this->assertSame( [], $type['fields'] );
		}
	}

	public function test_terms_are_capped_and_the_total_is_given(): void {
		Functions\when( 'get_taxonomy' )->alias(
			static fn( string $tax ) => 'category' === $tax ? (object) [ 'label' => 'Рубрики', 'object_type' => [ 'post' ] ] : false
		);
		$asked = [];
		Functions\when( 'get_terms' )->alias(
			static function ( array $args ) use ( &$asked ) {
				$asked = $args;
				return [ (object) [ 'term_id' => 1, 'name' => 'Новини', 'slug' => 'news', 'parent' => 0, 'count' => 3 ] ];
			}
		);
		Functions\when( 'wp_count_terms' )->justReturn( '120' );
		Functions\when( 'pll_is_translated_taxonomy' )->justReturn( true );
		Functions\when( 'pll_get_term_language' )->justReturn( 'uk' );

		$taxonomies = ( new Schema( false, true ) )->taxonomies();

		$this->assertCount( 1, $taxonomies );
		$this->assertSame( Schema::TERMS_CAP, $asked['number'] );
		$this->assertSame( '', $asked['lang'] );
		$this->assertSame( 120, $taxonomies[0]['terms_total'] );
		$this->assertTrue( $taxonomies[0]['translated'] );
		$this->assertFalse( $taxonomies[0]['read_only'] );
		$this->assertSame(
			[ 'id' => 1, 'name' => 'Новини', 'slug' => 'news', 'parent' => 0, 'count' => 3, 'lang' => 'uk' ],
			$taxonomies[0]['terms'][0]
		);
	}

	public function test_service_city_is_read_only(): void {
		Functions\when( 'get_taxonomy' )->alias(
			static fn( string $tax ) => 'service-city' === $tax ? (object) [ 'label' => 'Міста', 'object_type' => [ 'service' ] ] : false
		);
		Functions\when( 'get_terms' )->justReturn( [] );
		Functions\when( 'wp_count_terms' )->justReturn( 0 );

		$taxonomies = ( new Schema( false, false ) )->taxonomies();

		$this->assertTrue( $taxonomies[0]['read_only'] );
		$this->assertSame( [], $taxonomies[0]['terms'] );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function field_groups(): array {
		return [
			[
				'key'      => 'group_settings',
				'location' => [ [ [ 'param' => 'options_page', 'operator' => '==', 'value' => 'acf-options-settings' ] ] ],
			],
			[
				'key'      => 'group_service',
				'location' => [
					[
						[ 'param' => 'post_type', 'operator' => '==', 'value' => 'service' ],
						[ 'param' => 'page_template', 'operator' => '!=', 'value' => 'templates/sub-service.php' ],
					],
				],
			],
			[
				'key'      => 'group_page',
				'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ] ] ],
			],
			[
				'key'      => 'group_about',
				'location' => [ [ [ 'param' => 'block', 'operator' => '==', 'value' => 'acf/about' ] ] ],
			],
		];
	}

	/**
	 * @return array<string, list<array<string, mixed>>>
	 */
	private static function fields(): array {
		return [
			'group_settings' => [ [ 'name' => 'contacts_cities', 'label' => 'Contacts', 'type' => 'repeater' ] ],
			'group_service'  => [
				[ 'name' => 'image', 'label' => 'Image', 'type' => 'image', 'required' => 1 ],
				[ 'name' => 'general', 'label' => 'General', 'type' => 'tab' ],
				[
					'name'       => 'prices',
					'label'      => 'Prices',
					'type'       => 'repeater',
					'sub_fields' => [
						[ 'name' => 'title', 'label' => 'Title', 'type' => 'textarea' ],
						[
							'name'       => 'city_prices',
							'label'      => 'Prices by city:',
							'type'       => 'group',
							'sub_fields' => [ [ 'name' => 'price_city_4', 'label' => 'Price in Харків', 'type' => 'number' ] ],
						],
						[ 'name' => 'price', 'label' => 'Price', 'type' => 'number' ],
						[ 'name' => 'cities', 'label' => 'Cities', 'type' => 'checkbox', 'choices' => [ 'city_4' => 'Харків' ] ],
						[ 'name' => 'is_price_general', 'label' => 'Is price general?', 'type' => 'true_false' ],
					],
				],
			],
			'group_page'     => [ [ 'name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text' ] ],
			'group_about'    => [
				[ 'name' => 'block-name', 'label' => 'Block name', 'type' => 'message' ],
				[ 'name' => 'heading', 'label' => 'Heading', 'type' => 'text' ],
				[
					'name'       => 'years',
					'label'      => 'Years',
					'type'       => 'repeater',
					'sub_fields' => [
						[ 'name' => 'year', 'label' => 'Year', 'type' => 'text' ],
						[ 'name' => 'title', 'label' => 'Title', 'type' => 'text' ],
					],
				],
			],
		];
	}
}
