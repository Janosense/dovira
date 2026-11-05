<?php
/**
 * Tests for dovira\ContentMcp\PostWriter: a partial change of one post,
 * planned (the diff) and applied (exactly what the diff shows).
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\ContentMcp;

use Brain\Monkey\Functions;
use dovira\ContentMcp\Diff;
use dovira\ContentMcp\PostReader;
use dovira\ContentMcp\PostWriter;
use dovira\Tests\TestCase;

foreach ( [ 'Scope', 'DescribeSite', 'FieldValues', 'FieldInput', 'CityPrice', 'PostReader', 'Diff', 'PostWriter' ] as $dovira_class ) {
	require_once dirname( __DIR__, 3 ) . "/inc/features/content-mcp/{$dovira_class}.php";
}

/**
 * Only what is named changes; blocks round-trip with the reader by name;
 * nothing outside the editor's blocks, the post's fields or existing terms.
 */
final class PostWriterTest extends TestCase {

	/** @var array<string, array<string, mixed>> ACF's local block meta: block id → flattened data. */
	private array $meta = [];

	/** @var list<array{0: string, 1: mixed}> Every write, in order. */
	private array $writes = [];

	/** @var list<array<string, mixed>> The hero block's field definitions. */
	private const HERO = [
		[ 'key' => 'field_hero_visible', 'name' => 'is_visible', 'type' => 'true_false', 'default_value' => 1 ],
		[ 'key' => 'field_hero_heading', 'name' => 'heading', 'type' => 'text', 'default_value' => '' ],
		[ 'key' => 'field_hero_text', 'name' => 'text', 'type' => 'wysiwyg' ],
		[ 'key' => 'field_hero_image', 'name' => 'image', 'type' => 'image' ],
	];

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_slash' )->alias( static fn( $v ) => is_array( $v ) ? array_map( static fn( $x ) => is_string( $x ) ? addslashes( $x ) : $x, $v ) : $v );
		Functions\when( 'wp_json_encode' )->alias( static fn( $v ) => json_encode( $v ) );
		Functions\when( 'sanitize_title' )->alias( static fn( string $t ) => strtolower( (string) preg_replace( '/[^a-z0-9]+/i', '-', $t ) ) );
		Functions\when( 'wp_unique_post_slug' )->alias( static fn( string $slug ) => 'taken' === $slug ? 'taken-2' : $slug );
		Functions\when( 'is_post_type_hierarchical' )->alias( static fn( string $type ) => 'page' === $type );
		Functions\when( 'get_post_type_object' )->justReturn( (object) [ 'cap' => (object) [ 'publish_posts' => 'publish_pages' ] ] );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_object_taxonomies' )->alias( static fn( string $type ) => 'post' === $type ? [ 'category', 'post_tag', 'post_format' ] : ( 'vacancy' === $type ? [ 'service-city' ] : [] ) );
		Functions\when( 'wp_get_object_terms' )->justReturn( [ (object) [ 'term_id' => 1, 'name' => 'Новини' ] ] );
		Functions\when( 'get_term' )->alias(
			static fn( int $id, string $tax = '' ) => match ( $id ) {
				1       => (object) [ 'term_id' => 1, 'name' => 'Новини', 'taxonomy' => 'category' ],
				7       => (object) [ 'term_id' => 7, 'name' => 'Акції', 'taxonomy' => 'category' ],
				9       => (object) [ 'term_id' => 9, 'name' => 'Акции', 'taxonomy' => 'category' ],
				4       => (object) [ 'term_id' => 4, 'name' => 'Харків', 'taxonomy' => 'service-city' ],
				default => null,
			}
		);
		Functions\when( 'get_terms' )->alias( static fn( array $args ) => 'Акції' === $args['name'] ? [ (object) [ 'term_id' => 7, 'name' => 'Акції' ] ] : [] );
		Functions\when( 'pll_is_translated_post_type' )->justReturn( true );
		Functions\when( 'pll_get_post_language' )->justReturn( 'uk' );
		Functions\when( 'pll_get_post_translations' )->justReturn( [] );
		Functions\when( 'pll_is_translated_taxonomy' )->alias( static fn( string $tax ) => 'service-city' !== $tax );
		Functions\when( 'pll_get_term_language' )->alias( static fn( int $id ) => 9 === $id ? 'ru' : 'uk' );

		Functions\when( 'wp_update_post' )->alias(
			function ( array $postarr, bool $wp_error ) {
				$this->writes[] = [ 'post', $postarr ];
				return $postarr['ID'];
			}
		);
		Functions\when( 'wp_set_object_terms' )->alias(
			function ( int $id, array $ids, string $tax, bool $append ) {
				$this->writes[] = [ "terms:{$tax}", [ $ids, $append ] ];
				return $ids;
			}
		);
		Functions\when( 'update_field' )->alias(
			function ( string $key, $value, int $id ) {
				$this->writes[] = [ "acf:{$key}", $value ];
				return true;
			}
		);

		// ACF: field groups, values and its local block meta.
		Functions\when( 'acf_get_field_groups' )->alias( static fn( array $filter ) => [ [ 'key' => 'group_' . $filter['post_type'] ] ] );
		Functions\when( 'acf_get_fields' )->alias(
			static fn( array $group ) => match ( $group['key'] ) {
				'group_vacancy' => [ [ 'key' => 'field_vacancy_is_open', 'name' => 'is_open', 'type' => 'true_false' ] ],
				'group_service' => [
					[ 'key' => 'field_service_image', 'name' => 'image', 'type' => 'image', 'required' => 1 ],
					[ 'key' => 'field_service_prices', 'name' => 'prices', 'type' => 'repeater', 'sub_fields' => [] ],
				],
				'group_employee' => [ [ 'key' => 'field_employee_photo', 'name' => 'photo', 'type' => 'image', 'required' => true ] ],
				default         => [
					[ 'key' => 'field_page_image', 'name' => 'image', 'type' => 'image' ],
					[
						'key'        => 'field_page_rows',
						'name'       => 'rows',
						'type'       => 'repeater',
						'sub_fields' => [ [ 'key' => 'field_page_rows_photo', 'name' => 'photo', 'type' => 'image' ], [ 'key' => 'field_page_rows_title', 'name' => 'title', 'type' => 'text' ] ],
					],
				],
			}
		);
		Functions\when( 'get_field' )->alias( static fn( string $key ) => 'field_vacancy_is_open' === $key ? true : null );
		Functions\when( 'acf_get_block_fields' )->alias( static fn( array $block ) => 'acf/hero' === $block['name'] ? self::HERO : [] );
		Functions\when( 'acf_setup_meta' )->alias(
			function ( array $values, string $id ) {
				$data = $values;
				if ( str_starts_with( (string) array_key_first( $values ), 'field_' ) ) {
					$names = array_column( self::HERO, 'name', 'key' );
					$data  = [];
					foreach ( $values as $key => $value ) {
						$data[ $names[ $key ] ]       = $value;
						$data[ '_' . $names[ $key ] ] = $key;
					}
				}
				$this->meta[ $id ] = $data;
				return $data;
			}
		);
		Functions\when( 'acf_reset_meta' )->alias(
			function ( string $id ): void {
				unset( $this->meta[ $id ] );
			}
		);
		Functions\when( 'get_fields' )->alias( fn( string $id ) => array_filter( $this->meta[ $id ] ?? [], static fn( $k ) => ! str_starts_with( $k, '_' ), ARRAY_FILTER_USE_KEY ) );
		Functions\when( 'get_field_objects' )->alias(
			function ( $id ) {
				$types = array_column( self::HERO, 'type', 'name' );
				$objects = [];
				foreach ( $this->meta[ $id ] ?? [] as $name => $value ) {
					if ( ! str_starts_with( $name, '_' ) ) {
						$objects[ $name ] = [ 'name' => $name, 'type' => $types[ $name ] ?? 'text', 'value' => 'wysiwyg' === ( $types[ $name ] ?? '' ) ? "<p>{$value}</p>" : $value ];
					}
				}
				return $objects ?: false;
			}
		);
		Functions\when( 'wp_get_attachment_url' )->alias( static fn( int $id ) => "https://x/{$id}.jpg" );
		Functions\when( 'get_post_meta' )->justReturn( '' );

		// Blocks: content is one JSON block per paragraph.
		Functions\when( 'serialize_block' )->alias( static fn( array $block ) => json_encode( $block, JSON_UNESCAPED_UNICODE ) );
		Functions\when( 'parse_blocks' )->alias(
			static fn( string $content ) => array_map( static fn( $part ) => json_decode( $part, true ), array_filter( explode( "\n\n", $content ) ) )
		);
	}

	public function test_only_the_named_post_fields_are_written_and_the_diff_is_what_is_written(): void {
		$plan = $this->writer()->plan( self::post( 'page' ), [ 'title' => 'Про нас', 'menu_order' => 3 ] );

		$this->assertSame(
			[
				[ 'target' => [ 'id' => 176, 'lang' => 'uk' ], 'field' => 'title', 'from' => 'Сторінка', 'to' => 'Про нас' ],
				[ 'target' => [ 'id' => 176, 'lang' => 'uk' ], 'field' => 'menu_order', 'from' => 0, 'to' => 3 ],
			],
			$plan['diff']->lines()
		);
		$this->assertSame( [], $this->writes, 'planning writes nothing' );

		$this->assertTrue( $plan['apply']() );
		$this->assertSame( [ [ 'post', [ 'ID' => 176, 'post_title' => 'Про нас', 'menu_order' => 3 ] ] ], $this->writes );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function refused_statuses(): array {
		return [
			'trash'   => [ 'trash', 'trash-post' ],
			'future'  => [ 'future', 'publish, draft, pending, private' ],
			'unknown' => [ 'archived', 'publish, draft, pending, private' ],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'refused_statuses' )]
	public function test_a_status_outside_the_four_is_refused( string $status, string $hint ): void {
		$result = $this->writer()->plan( self::post( 'page' ), [ 'status' => $status ] );

		$this->assertSame( 'dovira_status_invalid', $result->get_error_code() );
		$this->assertStringContainsString( $hint, $result->get_error_message() );
	}

	public function test_publishing_needs_the_types_publish_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$this->assertSame( 'dovira_cannot_publish', $this->writer()->plan( self::post( 'page', 'draft' ), [ 'status' => 'publish' ] )->get_error_code() );
		$this->assertIsArray( $this->writer()->plan( self::post( 'page' ), [ 'status' => 'draft' ] ) );
	}

	public function test_a_trashed_post_and_an_empty_change_are_refused(): void {
		$this->assertSame( 'dovira_post_in_trash', $this->writer()->plan( self::post( 'page', 'trash' ), [ 'title' => 'x' ] )->get_error_code() );
		$this->assertSame( 'dovira_nothing_to_change', $this->writer()->plan( self::post( 'page' ), [ 'content_html' => '<p>x</p>' ] )->get_error_code() );
	}

	public function test_the_slug_is_shown_as_it_will_be_stored(): void {
		$plan = $this->writer()->plan( self::post( 'page' ), [ 'slug' => 'Taken' ] );

		$this->assertSame( 'taken-2', $plan['diff']->lines()[0]['to'] );
	}

	public function test_a_parent_must_be_a_page_that_is_not_the_post_or_its_descendant(): void {
		Functions\when( 'get_post' )->alias( static fn( int $id ) => in_array( $id, [ 5, 6 ], true ) ? self::post( 'page', 'publish', $id ) : ( 8 === $id ? self::post( 'post', 'publish', 8 ) : null ) );
		Functions\when( 'get_post_ancestors' )->alias( static fn( int $id ) => 6 === $id ? [ 176 ] : [] );

		$this->assertIsArray( $this->writer()->plan( self::post( 'page' ), [ 'parent' => 5 ] ) );
		$this->assertIsArray( $this->writer()->plan( self::post( 'page' ), [ 'parent' => 0 ] ) );
		foreach ( [ 6, 8, 99, 176 ] as $parent ) {
			$this->assertSame( 'dovira_parent_invalid', $this->writer()->plan( self::post( 'page' ), [ 'parent' => $parent ] )->get_error_code(), "parent {$parent}" );
		}
		$this->assertSame( 'dovira_parent_invalid', $this->writer()->plan( self::post( 'post' ), [ 'parent' => 5 ] )->get_error_code() );
	}

	public function test_terms_by_name_in_the_posts_language_and_by_id_replace_the_taxonomys_set(): void {
		$plan = $this->writer()->plan( self::post( 'post' ), [ 'terms' => [ 'category' => [ 1, 'Акції' ] ] ] );

		$this->assertSame(
			[ 'field' => 'terms.category', 'from' => [ [ 'id' => 1, 'name' => 'Новини' ] ], 'to' => [ [ 'id' => 1, 'name' => 'Новини' ], [ 'id' => 7, 'name' => 'Акції' ] ] ],
			array_diff_key( $plan['diff']->lines()[0], [ 'target' => 1 ] )
		);
		$plan['apply']();
		$this->assertSame( [ [ 'terms:category', [ [ 1, 7 ], false ] ] ], $this->writes );
	}

	/**
	 * @return array<string, array{string, array<string, mixed>, string}>
	 */
	public static function refused_terms(): array {
		return [
			'unknown name, never created' => [ 'post', [ 'category' => [ 'Нова рубрика' ] ], 'dovira_term_unknown' ],
			'unknown id'                  => [ 'post', [ 'category' => [ 404 ] ], 'dovira_term_unknown' ],
			'a term in Russian'           => [ 'post', [ 'category' => [ 9 ] ], 'dovira_term_language' ],
			'a city by name'              => [ 'vacancy', [ 'service-city' => [ 'Харків' ] ], 'dovira_term_unknown' ],
			'a taxonomy the type lacks'   => [ 'page', [ 'category' => [ 1 ] ], 'dovira_taxonomy_invalid' ],
			'out of scope'                => [ 'post', [ 'post_format' => [ 1 ] ], 'dovira_taxonomy_invalid' ],
		];
	}

	/**
	 * @param array<string, mixed> $terms
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'refused_terms' )]
	public function test_terms_are_existing_ones_only( string $type, array $terms, string $code ): void {
		Functions\expect( 'wp_insert_term' )->never();

		$this->assertSame( $code, $this->writer()->plan( self::post( $type ), [ 'terms' => $terms ] )->get_error_code() );
	}

	public function test_a_city_by_id_is_accepted(): void {
		$plan = $this->writer()->plan( self::post( 'vacancy' ), [ 'terms' => [ 'service-city' => [ 4 ] ] ] );

		$this->assertSame( [ [ 'id' => 4, 'name' => 'Харків' ] ], $plan['diff']->lines()[0]['to'] );
	}

	public function test_acf_fields_are_written_by_key_with_images_as_ids_and_a_repeater_whole(): void {
		$rows = [ [ 'photo' => [ 'id' => 768, 'url' => 'https://x/768.jpg', 'alt' => '' ], 'title' => 'Рядок' ] ];

		$plan = $this->writer()->plan( self::post( 'page' ), [ 'acf' => [ 'image' => [ 'id' => 5, 'url' => 'u', 'alt' => '' ], 'rows' => $rows ] ] );
		$plan['apply']();

		$this->assertSame(
			[
				[ 'acf:field_page_image', 5 ],
				[ 'acf:field_page_rows', [ [ 'photo' => 768, 'title' => 'Рядок' ] ] ],
			],
			$this->writes
		);
		$this->assertSame( [ 'acf.image', 'acf.rows' ], array_column( $plan['diff']->lines(), 'field' ) );
	}

	public function test_a_vacancy_closes_through_its_true_false(): void {
		$plan = $this->writer()->plan( self::post( 'vacancy' ), [ 'acf' => [ 'is_open' => false ] ] );

		$this->assertSame( [ true, false ], [ $plan['diff']->lines()[0]['from'], $plan['diff']->lines()[0]['to'] ] );
	}

	public function test_unknown_fields_and_service_prices_are_refused(): void {
		$this->assertSame( 'dovira_unknown_field', $this->writer()->plan( self::post( 'page' ), [ 'acf' => [ 'nope' => 1 ] ] )->get_error_code() );
		$this->assertSame( 'dovira_prices_through_price_tool', $this->writer()->plan( self::post( 'service' ), [ 'acf' => [ 'prices' => [] ] ] )->get_error_code() );
	}

	public function test_a_block_field_is_written_by_key_through_acf_and_read_back_by_name(): void {
		$current = self::hero( [ 'heading' => 'Привіт', '_heading' => 'field_hero_heading', 'text' => 'Старий текст', '_text' => 'field_hero_text' ], [ 'mode' => 'edit', 'align' => 'full' ] );
		$post    = self::post( 'page', 'publish', 176, $current );

		$plan = $this->writer()->plan( $post, [ 'blocks' => [ [ 'block' => 'acf/hero', 'fields' => [ 'heading' => 'Вітаємо' ] ] ] ] );

		$this->assertSame( [ [ 'target' => [ 'id' => 176, 'lang' => 'uk' ], 'field' => 'blocks[0].acf/hero.heading', 'from' => 'Привіт', 'to' => 'Вітаємо' ] ], $plan['diff']->lines() );

		$plan['apply']();
		$content = stripslashes( $this->writes[0][1]['post_content'] );
		$block   = json_decode( $content, true );

		$this->assertSame( 'Старий текст', $block['attrs']['data']['text'], 'an unnamed field keeps its stored value, unformatted' );
		$this->assertSame( 'Вітаємо', $block['attrs']['data']['heading'] );
		$this->assertSame( 'field_hero_heading', $block['attrs']['data']['_heading'] );
		$this->assertSame( [ 'heading', '_heading', 'text', '_text' ], array_keys( $block['attrs']['data'] ), 'in the order of the block\'s fields' );
		$this->assertSame( [ 'edit', 'full', 'acf/hero' ], [ $block['attrs']['mode'], $block['attrs']['align'], $block['attrs']['name'] ] );

		$read = ( new PostReader( true, false ) )->blocks( $content );
		$this->assertSame( 'Вітаємо', $read[0]['fields']->heading );
		$this->assertSame( '<p>Старий текст</p>', $read[0]['fields']->text );
	}

	public function test_a_new_block_gets_the_defaults_of_its_unnamed_fields_and_an_existing_one_does_not(): void {
		$plan = $this->writer()->plan( self::post( 'page' ), [ 'blocks' => [ [ 'block' => 'acf/hero', 'fields' => [ 'heading' => 'Новий' ] ] ] ] );
		$plan['apply']();
		$data = json_decode( stripslashes( $this->writes[0][1]['post_content'] ), true )['attrs']['data'];

		$this->assertSame( [ 'is_visible', '_is_visible', 'heading', '_heading' ], array_keys( $data ), 'an empty default adds nothing' );
		$this->assertSame( 1, $data['is_visible'] );

		$this->writes = [];
		$current      = self::hero( [ 'heading' => 'Привіт', '_heading' => 'field_hero_heading' ] );
		$plan         = $this->writer()->plan( self::post( 'page', 'publish', 176, $current ), [ 'blocks' => [ [ 'block' => 'acf/hero', 'fields' => [ 'heading' => 'Вітаємо' ] ] ] ] );
		$plan['apply']();
		$data = json_decode( stripslashes( $this->writes[0][1]['post_content'] ), true )['attrs']['data'];

		$this->assertArrayNotHasKey( 'is_visible', $data, 'an existing block keeps what it stores' );
	}

	public function test_the_content_is_slashed_for_wp_update_post(): void {
		$plan = $this->writer()->plan( self::post( 'post' ), [ 'blocks' => [ [ 'block' => 'core/freeform', 'html' => '<p>a "b"</p>' ] ] ] );
		$plan['apply']();

		$written   = $this->writes[0][1]['post_content'];
		$unslashed = stripslashes( $written );

		$this->assertSame( addslashes( $unslashed ), $written );
		$this->assertSame( '<p>a "b"</p>', json_decode( $unslashed, true )['innerHTML'] );
	}

	public function test_a_new_block_added_and_a_trailing_block_removed_show_in_the_diff(): void {
		$post = self::post( 'page', 'publish', 176, self::hero( [ 'heading' => 'A', '_heading' => 'field_hero_heading' ] ) . "\n\n" . self::hero( [ 'heading' => 'B', '_heading' => 'field_hero_heading' ] ) );

		$plan = $this->writer()->plan( $post, [ 'blocks' => [ [ 'block' => 'acf/hero', 'fields' => [] ] ] ] );

		$this->assertSame( [ 'blocks[1]' ], array_column( $plan['diff']->lines(), 'field' ) );
		$this->assertNull( $plan['diff']->lines()[0]['to'] );
	}

	/**
	 * @return array<string, array{string, array<string, mixed>, string}>
	 */
	public static function refused_blocks(): array {
		return [
			'an unknown acf block'       => [ 'page', [ 'block' => 'acf/foo', 'fields' => [] ], 'dovira_block_not_allowed' ],
			'a core block on a page'     => [ 'page', [ 'block' => 'core/paragraph', 'html' => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->' ], 'dovira_block_not_allowed' ],
			'markup of another block'    => [ 'post', [ 'block' => 'core/paragraph', 'html' => '{"blockName":"core/heading","attrs":[]}' ], 'dovira_block_markup_invalid' ],
			'markup of two blocks'       => [ 'post', [ 'block' => 'core/paragraph', 'html' => "{\"blockName\":\"core/paragraph\"}\n\n{\"blockName\":\"core/paragraph\"}" ], 'dovira_block_markup_invalid' ],
			'an unknown field of a block' => [ 'page', [ 'block' => 'acf/hero', 'fields' => [ 'subtitle' => 'x' ] ], 'dovira_unknown_field' ],
		];
	}

	/**
	 * @param array<string, mixed> $block
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'refused_blocks' )]
	public function test_blocks_outside_the_editors_set_or_malformed_are_refused( string $type, array $block, string $code ): void {
		$this->assertSame( $code, $this->writer()->plan( self::post( $type ), [ 'blocks' => [ $block ] ] )->get_error_code() );
	}

	public function test_a_core_block_is_written_back_as_parsed_on_a_blog_post(): void {
		$html = '{"blockName":"core/paragraph","attrs":[],"innerHTML":"<p>Так</p>"}';

		$plan = $this->writer()->plan( self::post( 'post' ), [ 'blocks' => [ [ 'block' => 'core/paragraph', 'html' => $html ] ] ] );
		$plan['apply']();

		$this->assertSame( 'core/paragraph', json_decode( stripslashes( $this->writes[0][1]['post_content'] ), true )['blockName'] );
	}

	public function test_a_new_post_is_previewed_value_by_value_and_inserted_in_ukrainian(): void {
		$this->creation_stubs();
		$plan = $this->writer()->create(
			[
				'post_type' => 'post',
				'title'     => 'Акція на вакцинацію',
				'status'    => 'draft',
				'terms'     => [ 'category' => [ 'Акції' ] ],
				'blocks'    => [ [ 'block' => 'acf/hero', 'fields' => [ 'heading' => 'Вакцинація' ] ] ],
			]
		);

		$this->assertSame(
			[ 'post_type', 'title', 'status', 'terms.category', 'blocks[0]' ],
			array_column( $plan['diff']->lines(), 'field' )
		);
		foreach ( $plan['diff']->lines() as $line ) {
			$this->assertSame( [ 'id' => null, 'lang' => 'uk' ], $line['target'] );
			$this->assertNull( $line['from'] );
		}
		$this->assertSame( [], $this->writes, 'planning inserts nothing' );

		$this->assertSame( 501, $plan['apply']() );
		$this->assertSame( 'post', $this->writes[0][1]['post_type'] );
		$this->assertSame( 'Акція на вакцинацію', $this->writes[0][1]['post_title'] );
		$this->assertSame( 'draft', $this->writes[0][1]['post_status'] );
		$this->assertSame( 'Вакцинація', json_decode( stripslashes( $this->writes[0][1]['post_content'] ), true )['attrs']['data']['heading'] );
		$this->assertSame( [ 'lang', [ 501, 'uk' ] ], $this->writes[1] );
		$this->assertSame( [ 'terms:category', [ [ 7 ], false ] ], $this->writes[2] );
	}

	/**
	 * @return array<string, array{array<string, mixed>, string}>
	 */
	public static function refused_creations(): array {
		$base = [ 'post_type' => 'page', 'title' => 'Нова', 'status' => 'draft' ];

		return [
			'no status, no default'  => [ array_diff_key( $base, [ 'status' => 1 ] ), 'dovira_status_required' ],
			'no title'               => [ array_diff_key( $base, [ 'title' => 1 ] ), 'dovira_title_required' ],
			'Russian'                => [ [ 'lang' => 'ru' ] + $base, 'dovira_lang_invalid' ],
			'a record'               => [ [ 'post_type' => 'conversation' ] + $base, 'dovira_read_only_type' ],
			'a type out of scope'    => [ [ 'post_type' => 'attachment' ] + $base, 'dovira_type_out_of_scope' ],
			'trash as status'        => [ [ 'status' => 'trash' ] + $base, 'dovira_status_invalid' ],
			'service without image'  => [ [ 'post_type' => 'service', 'acf' => [ 'image' => '' ] ] + $base, 'dovira_required_fields' ],
			'employee without photo' => [ [ 'post_type' => 'employee' ] + $base, 'dovira_required_fields' ],
			'a term that is not there' => [ [ 'post_type' => 'post', 'terms' => [ 'category' => [ 'Нова рубрика' ] ] ] + $base, 'dovira_term_unknown' ],
		];
	}

	/**
	 * @param array<string, mixed> $input
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'refused_creations' )]
	public function test_a_creation_is_refused_before_anything_is_inserted( array $input, string $code ): void {
		$this->creation_stubs();
		Functions\expect( 'wp_insert_post' )->never();

		$result = $this->writer()->create( $input );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( $code, $result->get_error_code() );
	}

	public function test_the_refusal_names_the_missing_required_fields(): void {
		$this->creation_stubs();

		$result = $this->writer()->create( [ 'post_type' => 'service', 'title' => 'Нова послуга', 'status' => 'draft' ] );

		$this->assertStringContainsString( 'image', $result->get_error_message() );
	}

	public function test_a_blog_post_is_refused_where_there_is_no_news_page(): void {
		$this->creation_stubs();
		Functions\when( 'get_page_by_path' )->justReturn( null );

		$this->assertSame( 'dovira_no_blog', $this->writer()->create( [ 'post_type' => 'post', 'title' => 'x', 'status' => 'draft' ] )->get_error_code() );
	}

	public function test_publishing_a_new_post_needs_the_publish_capability(): void {
		$this->creation_stubs();
		Functions\when( 'current_user_can' )->justReturn( false );

		$this->assertSame( 'dovira_cannot_publish', $this->writer()->create( [ 'post_type' => 'page', 'title' => 'x', 'status' => 'publish' ] )->get_error_code() );
	}

	public function test_a_new_services_price_rows_are_general_rows_of_this_city(): void {
		$this->creation_stubs();

		$plan = $this->writer()->create(
			[
				'post_type' => 'service',
				'title'     => 'Нова послуга',
				'status'    => 'draft',
				'acf'       => [
					'image'  => [ 'id' => 768, 'url' => 'u', 'alt' => '' ],
					'prices' => [ [ 'title' => 'Огляд', 'description' => '', 'price' => 450 ], [ 'title' => 'Аналіз', 'price' => null ] ],
				],
			]
		);
		$plan['apply']();

		$this->assertSame(
			[ [ 'title' => 'Огляд', 'description' => '', 'price' => 450 ], [ 'title' => 'Аналіз', 'description' => '', 'price' => null ] ],
			array_values( array_filter( $plan['diff']->lines(), static fn( $l ) => 'acf.prices' === $l['field'] ) )[0]['to']
		);
		$rows = array_values( array_filter( $this->writes, static fn( $w ) => 'acf:field_service_prices' === $w[0] ) )[0][1];
		$this->assertSame( [ 'title' => 'Огляд', 'description' => '', 'price' => '450', 'linked_page' => '', 'cities' => [ 'city_4' ], 'is_price_general' => 1 ], $rows[0] );
		$this->assertSame( '', $rows[1]['price'], 'no amount: the site shows the phone' );
		$this->assertArrayNotHasKey( 'city_prices', $rows[0] );
		$this->assertContains( [ 'acf:field_service_image', 768 ], $this->writes );
	}

	public function test_a_new_services_rows_are_refused_without_the_city_term_or_with_a_bad_price(): void {
		$this->creation_stubs();
		$input = [ 'post_type' => 'service', 'title' => 'x', 'status' => 'draft', 'acf' => [ 'image' => 5, 'prices' => [ [ 'title' => 'a', 'price' => 'договірна' ] ] ] ];

		$this->assertSame( 'dovira_price_invalid', $this->writer()->create( $input )->get_error_code() );

		Functions\when( 'get_term_by' )->justReturn( false );
		$input['acf']['prices'][0]['price'] = 100;
		$this->assertSame( 'dovira_city_term_missing', $this->writer()->create( $input )->get_error_code() );
	}

	public function test_an_existing_services_prices_are_still_refused(): void {
		$this->assertSame( 'dovira_prices_through_price_tool', $this->writer()->plan( self::post( 'service' ), [ 'acf' => [ 'prices' => [] ] ] )->get_error_code() );
	}

	public function test_several_targets_share_one_diff(): void {
		$diff = new Diff();
		$this->writer()->plan( self::post( 'page' ), [ 'title' => 'A' ], $diff );
		$this->writer()->plan( self::post( 'page', 'publish', 177 ), [ 'title' => 'B' ], $diff );

		$this->assertSame( [ 176, 177 ], array_column( array_column( $diff->lines(), 'target' ), 'id' ) );
	}

	private function creation_stubs(): void {
		Functions\when( 'get_page_by_path' )->justReturn( (object) [ 'ID' => 211 ] );
		Functions\when( 'get_site_url' )->justReturn( 'https://dev.dovira.vet' );
		Functions\when( 'get_term_by' )->justReturn( (object) [ 'term_id' => 4 ] );
		Functions\when( 'wp_insert_post' )->alias(
			function ( array $postarr, bool $wp_error ) {
				$this->writes[] = [ 'insert', $postarr ];
				return 501;
			}
		);
		Functions\when( 'pll_set_post_language' )->alias(
			function ( int $id, string $lang ): void {
				$this->writes[] = [ 'lang', [ $id, $lang ] ];
			}
		);
	}

	private function writer(): PostWriter {
		return new PostWriter(
			true,
			true,
			new PostReader( true, true ),
			static fn( object $post ) => 'post' === $post->post_type ? [ 'core/paragraph', 'core/heading', 'core/freeform', 'acf/hero' ] : [ 'acf/hero', 'acf/about' ]
		);
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $attrs
	 */
	private static function hero( array $data, array $attrs = [] ): string {
		return (string) json_encode(
			[
				'blockName' => 'acf/hero',
				'attrs'     => [ 'name' => 'acf/hero', 'data' => $data ] + $attrs,
				'innerHTML' => '',
			],
			JSON_UNESCAPED_UNICODE
		);
	}

	private static function post( string $type, string $status = 'publish', int $id = 176, string $content = '' ): object {
		return (object) [
			'ID'           => $id,
			'post_type'    => $type,
			'post_status'  => $status,
			'post_title'   => 'Сторінка',
			'post_name'    => 'storinka',
			'post_parent'  => 0,
			'menu_order'   => 0,
			'post_content' => $content,
		];
	}
}
