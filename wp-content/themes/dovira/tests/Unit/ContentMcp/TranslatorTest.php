<?php
/**
 * Tests for dovira\ContentMcp\Translator and dovira/create-translation: the
 * Russian copy of a Ukrainian post, carried over where not translated.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\ContentMcp;

use Brain\Monkey\Functions;
use dovira\ContentMcp\CreateTranslation;
use dovira\ContentMcp\PostReader;
use dovira\ContentMcp\PostWriter;
use dovira\ContentMcp\Session;
use dovira\ContentMcp\Translator;
use dovira\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

foreach ( [ 'Catalogue', 'Scope', 'Session', 'Confirmation', 'Diff', 'Dump', 'DescribeSite', 'FieldValues', 'FieldInput', 'CityPrice', 'PostReader', 'PostWriter', 'WriteAbility', 'UpdatePost', 'Translator', 'CreateTranslation' ] as $dovira_class ) {
	require_once dirname( __DIR__, 3 ) . "/inc/features/content-mcp/{$dovira_class}.php";
}

/**
 * The copy carries what is not translated (ACF raw, content, prices, terms in
 * Russian, the parent's translation), takes what is, and is linked to its
 * source; an existing copy or a Russian source is refused.
 */
final class TranslatorTest extends TestCase {

	/** @var array<string, array<string, mixed>> ACF's local block meta: block id → flattened data. */
	private array $meta = [];

	/** @var list<array{0: string, 1: mixed}> Every write, in order. */
	private array $writes = [];

	/** @var array<string, mixed> */
	private array $transients = [];

	private bool $polylang = true;

	/** @var list<array<string, mixed>> */
	private const HERO = [
		[ 'key' => 'field_hero_visible', 'name' => 'is_visible', 'type' => 'true_false', 'default_value' => 1 ],
		[ 'key' => 'field_hero_heading', 'name' => 'heading', 'type' => 'text' ],
		[ 'key' => 'field_hero_text', 'name' => 'text', 'type' => 'wysiwyg' ],
	];

	/** @var list<array<string, mixed>> */
	private const PRICE_SUBS = [
		[ 'key' => 'field_p_title', 'name' => 'title', 'type' => 'text' ],
		[ 'key' => 'field_p_desc', 'name' => 'description', 'type' => 'text' ],
		[ 'key' => 'field_p_price', 'name' => 'price', 'type' => 'text' ],
		[ 'key' => 'field_p_general', 'name' => 'is_price_general', 'type' => 'true_false' ],
		[ 'key' => 'field_p_cities', 'name' => 'cities', 'type' => 'checkbox' ],
		[ 'key' => 'field_p_city', 'name' => 'city_prices', 'type' => 'group' ],
	];

	/** The source service's stored price rows: keyed by sub-field key, as ACF loads them raw. */
	private const RAW_ROWS = [
		[ 'field_p_title' => 'Огляд', 'field_p_desc' => 'первинний', 'field_p_price' => '450', 'field_p_general' => '1', 'field_p_cities' => [ 'city_4' ], 'field_p_city' => [] ],
		[ 'field_p_title' => 'Аналіз', 'field_p_desc' => '', 'field_p_price' => '', 'field_p_general' => '0', 'field_p_cities' => [ 'city_4', 'city_5' ], 'field_p_city' => [ 'price_city_4' => '320', 'price_city_5' => '300' ] ],
	];

	protected function setUp(): void {
		parent::setUp();
		Session::reset();

		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'set_transient' )->alias(
			function ( string $key, $value ): bool {
				$this->transients[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'get_transient' )->alias( fn( string $key ) => $this->transients[ $key ] ?? false );
		Functions\when( 'delete_transient' )->alias(
			function ( string $key ): bool {
				unset( $this->transients[ $key ] );
				return true;
			}
		);

		Functions\when( 'get_post' )->alias( static fn( int $id ) => self::posts()[ $id ] ?? null );
		Functions\when( 'wp_slash' )->returnArg();
		Functions\when( 'wp_json_encode' )->alias( static fn( $v ) => json_encode( $v ) );
		Functions\when( 'sanitize_title' )->alias( static fn( string $t ) => [ 'Осмотр' => 'osmotr', 'О клинике' => 'o-klinike' ][ $t ] ?? strtolower( (string) preg_replace( '/[^a-z0-9]+/i', '-', $t ) ) );
		Functions\when( 'wp_unique_post_slug' )->returnArg();
		Functions\when( 'is_post_type_hierarchical' )->alias( static fn( string $type ) => 'page' === $type );
		Functions\when( 'is_post_type_viewable' )->justReturn( true );
		Functions\when( 'get_permalink' )->justReturn( 'https://x/' );
		Functions\when( 'get_post_ancestors' )->justReturn( [] );
		Functions\when( 'get_post_type_object' )->justReturn( (object) [ 'cap' => (object) [ 'create_posts' => 'edit_posts', 'publish_posts' => 'publish_posts' ] ] );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_site_url' )->justReturn( 'https://dev.dovira.vet' );
		Functions\when( 'get_term_by' )->justReturn( (object) [ 'term_id' => 4 ] );
		Functions\when( 'wp_get_attachment_url' )->alias( static fn( int $id ) => "https://x/{$id}.jpg" );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'get_the_title' )->justReturn( 'x' );
		Functions\when( 'get_page_template_slug' )->alias( static fn( object $post ) => 300 === $post->ID ? 'templates/sub-service.php' : '' );
		Functions\when( 'get_post_thumbnail_id' )->alias( static fn( object $post ) => in_array( $post->ID, [ 300, 400 ], true ) ? 55 : 0 );
		Functions\when( 'post_type_supports' )->alias( static fn( string $type ) => in_array( $type, [ 'page', 'post' ], true ) );

		// Polylang.
		Functions\when( 'pll_is_translated_post_type' )->justReturn( true );
		Functions\when( 'pll_get_post_language' )->alias( static fn( int $id ) => in_array( $id, [ 350, 411, 412 ], true ) ? 'ru' : 'uk' );
		Functions\when( 'pll_get_post' )->alias(
			static fn( int $id, string $lang ) => [
				'310:ru' => 311,
				'350:uk' => 360,
				'410:ru' => 411,
			][ "{$id}:{$lang}" ] ?? 0
		);
		Functions\when( 'pll_get_post_translations' )->alias( static fn( int $id ) => [ 'uk' => $id ] );
		Functions\when( 'pll_is_translated_taxonomy' )->alias( static fn( string $tax ) => 'service-city' !== $tax );
		Functions\when( 'pll_get_term' )->alias( static fn( int $id ) => 7 === $id ? 9 : 0 );
		Functions\when( 'pll_get_term_language' )->alias( static fn( int $id ) => in_array( $id, [ 9, 10 ], true ) ? 'ru' : 'uk' );

		// Terms.
		Functions\when( 'get_object_taxonomies' )->alias( static fn( string $type ) => [ 'post' => [ 'category', 'post_tag' ], 'service' => [ 'service-city' ] ][ $type ] ?? [] );
		Functions\when( 'wp_get_post_terms' )->alias(
			static fn( int $id, string $tax ) => [
				'500:category'     => [ (object) [ 'term_id' => 7, 'name' => 'Акції' ], (object) [ 'term_id' => 8, 'name' => 'Новини' ] ],
				'300:service-city' => [ (object) [ 'term_id' => 4, 'name' => 'Харків' ] ],
			][ "{$id}:{$tax}" ] ?? []
		);
		Functions\when( 'get_term' )->alias(
			static fn( int $id ) => [
				4  => (object) [ 'term_id' => 4, 'name' => 'Харків' ],
				9  => (object) [ 'term_id' => 9, 'name' => 'Акции' ],
				10 => (object) [ 'term_id' => 10, 'name' => 'Новости' ],
			][ $id ] ?? null
		);
		Functions\when( 'get_terms' )->alias( static fn( array $args ) => 'Новости' === $args['name'] && 'ru' === ( $args['lang'] ?? '' ) ? [ (object) [ 'term_id' => 10, 'name' => 'Новости' ] ] : [] );

		// ACF: the service's field group, its values (formatted and raw) and the block meta.
		Functions\when( 'acf_get_field_groups' )->alias( static fn( array $filter ) => [ [ 'key' => 'group_' . $filter['post_type'] ] ] );
		Functions\when( 'acf_get_fields' )->alias( static fn( array $group ) => 'group_service' === $group['key'] ? self::service_fields() : [] );
		Functions\when( 'get_field_objects' )->alias(
			function ( $id, $format = true ) {
				if ( is_string( $id ) ) {
					return $this->block_objects( $id );
				}
				if ( 300 !== $id ) {
					return false;
				}
				$fields = array_column( self::service_fields(), null, 'name' );
				$rows   = [
					[ 'title' => 'Огляд', 'description' => 'первинний', 'price' => '450', 'is_price_general' => true, 'cities' => [ 'city_4' ], 'city_prices' => [] ],
					[ 'title' => 'Аналіз', 'description' => '', 'price' => '', 'is_price_general' => false, 'cities' => [ 'city_4', 'city_5' ], 'city_prices' => [ 'price_city_4' => '320', 'price_city_5' => '300' ] ],
				];
				return [
					'image'       => $fields['image'] + [ 'value' => 768 ],
					'description' => $fields['description'] + [ 'value' => $format ? '<p>Опис</p>' : 'Опис' ],
					'note'        => $fields['note'] + [ 'value' => '' ],
					'prices'      => $fields['prices'] + [ 'value' => $format ? $rows : self::RAW_ROWS ],
				];
			}
		);
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

		// Blocks: content is one JSON block per paragraph.
		Functions\when( 'serialize_block' )->alias( static fn( array $block ) => json_encode( $block, JSON_UNESCAPED_UNICODE ) );
		Functions\when( 'parse_blocks' )->alias(
			static fn( string $content ) => array_map( static fn( $part ) => json_decode( $part, true ), array_filter( explode( "\n\n", $content ) ) )
		);

		// Writes.
		Functions\when( 'wp_insert_post' )->alias(
			function ( array $postarr ) {
				$this->writes[] = [ 'insert', $postarr ];
				return 777;
			}
		);
		Functions\when( 'pll_set_post_language' )->alias(
			function ( int $id, string $lang ): void {
				$this->writes[] = [ 'lang', [ $id, $lang ] ];
			}
		);
		Functions\when( 'pll_save_post_translations' )->alias(
			function ( array $translations ): array {
				$this->writes[] = [ 'link', $translations ];
				return $translations;
			}
		);
		Functions\when( 'update_field' )->alias(
			function ( string $key, $value, int $id ): bool {
				$this->writes[] = [ "acf:{$key}", $value ];
				return true;
			}
		);
		Functions\when( 'wp_set_object_terms' )->alias(
			function ( int $id, array $ids, string $tax ) {
				$this->writes[] = [ "terms:{$tax}", $ids ];
				return $ids;
			}
		);
	}

	public function test_the_copy_carries_what_is_not_translated_and_takes_what_is(): void {
		$plan = $this->translator()->plan( [ 'id' => 300, 'title' => 'Осмотр', 'status' => 'draft', 'acf' => [ 'description' => 'Описание' ] ] );

		$this->assertSame( [], $this->writes, 'planning writes nothing' );
		foreach ( $plan['diff']->lines() as $line ) {
			$this->assertSame( [ 'id' => null, 'lang' => 'ru' ], $line['target'] );
			$this->assertNull( $line['from'] );
		}
		$this->assertSame(
			[ 'post_type', 'title', 'status', 'slug', 'menu_order', 'terms.service-city', 'acf.description', 'blocks[0]', 'template', 'date', 'acf.image', 'acf.prices' ],
			array_column( $plan['diff']->lines(), 'field' )
		);

		$this->assertSame( 777, $plan['apply']() );

		$insert = $this->writes[0][1];
		$this->assertSame( 'service', $insert['post_type'] );
		$this->assertSame( 'Осмотр', $insert['post_title'] );
		$this->assertSame( 'draft', $insert['post_status'] );
		$this->assertSame( 'osmotr', $insert['post_name'] );
		$this->assertSame( 2, $insert['menu_order'] );
		$this->assertSame( self::hero_content(), $insert['post_content'], 'the content goes over verbatim' );
		$this->assertSame( 'templates/sub-service.php', $insert['page_template'] );
		$this->assertArrayNotHasKey( '_thumbnail_id', $insert, 'a service has no featured image' );
		$this->assertSame( '2026-10-01 10:00:00', $insert['post_date'] );
		$this->assertSame( '2026-10-01 07:00:00', $insert['post_date_gmt'] );

		$this->assertSame( [ 'lang', [ 777, 'ru' ] ], $this->writes[1] );
		$this->assertSame( [ 'link', [ 'uk' => 300, 'ru' => 777 ] ], $this->writes[2] );
		$this->assertContains( [ 'acf:field_service_description', 'Описание' ], $this->writes );
		$this->assertContains( [ 'acf:field_service_image', 768 ], $this->writes, 'raw, never re-converted' );
		$this->assertContains( [ 'acf:field_service_prices', self::RAW_ROWS ], $this->writes, 'prices, per-city columns and cities as stored' );
		$this->assertNotContains( 'acf:field_service_note', array_column( $this->writes, 0 ), 'an empty field is not written' );
		$this->assertSame( [ 'terms:service-city', [ 4 ] ], $this->writes[ count( $this->writes ) - 1 ] );
	}

	public function test_the_preview_shows_prices_in_this_city_and_names_what_stays_ukrainian(): void {
		$plan   = $this->translator()->plan( [ 'id' => 300, 'title' => 'Осмотр', 'status' => 'draft', 'acf' => [ 'description' => 'Описание' ] ] );
		$prices = array_values( array_filter( $plan['diff']->lines(), static fn( $l ) => 'acf.prices' === $l['field'] ) )[0]['to'];

		$this->assertSame( [ 450, 320 ], array_column( $prices, 'price' ) );
		$this->assertCount( 1, $plan['diff']->warnings() );
		$this->assertStringContainsString( 'acf.prices', $plan['diff']->warnings()[0] );
		$this->assertStringContainsString( 'blocks', $plan['diff']->warnings()[0] );
		$this->assertStringNotContainsString( 'acf.description', $plan['diff']->warnings()[0] );
		$this->assertStringNotContainsString( 'acf.image', $plan['diff']->warnings()[0] );
	}

	public function test_price_rows_take_translated_texts_and_keep_their_prices(): void {
		$plan = $this->translator()->plan(
			[
				'id'     => 300,
				'title'  => 'Осмотр',
				'status' => 'draft',
				'acf'    => [ 'prices' => [ [ 'title' => 'Осмотр', 'description' => 'первичный', 'price' => 450 ], [ 'title' => 'Анализ' ] ] ],
			]
		);
		$plan['apply']();

		$rows = array_values( array_filter( $this->writes, static fn( $w ) => 'acf:field_service_prices' === $w[0] ) )[0][1];
		$this->assertSame( 'Осмотр', $rows[0]['field_p_title'] );
		$this->assertSame( 'первичный', $rows[0]['field_p_desc'] );
		$this->assertSame( 'Анализ', $rows[1]['field_p_title'] );
		$this->assertSame( '', $rows[1]['field_p_desc'], 'a text not named stays' );
		foreach ( [ 'field_p_price', 'field_p_general', 'field_p_cities', 'field_p_city' ] as $key ) {
			$this->assertSame( array_column( self::RAW_ROWS, $key ), array_column( $rows, $key ) );
		}
		$this->assertStringNotContainsString( 'acf.prices', $plan['diff']->warnings()[0] );
	}

	/**
	 * @return array<string, array{array<int, mixed>, string}>
	 */
	public static function refused_rows(): array {
		return [
			'fewer rows'      => [ [ [ 'title' => 'Осмотр' ] ], 'dovira_prices_invalid' ],
			'another price'   => [ [ [ 'title' => 'Осмотр', 'price' => 500 ], [ 'title' => 'Анализ' ] ], 'dovira_price_in_source' ],
			'a price of none' => [ [ [ 'title' => 'Осмотр' ], [ 'title' => 'Анализ', 'price' => 1 ] ], 'dovira_price_in_source' ],
		];
	}

	/**
	 * @param array<int, mixed> $rows
	 */
	#[DataProvider( 'refused_rows' )]
	public function test_price_rows_of_another_count_or_another_price_are_refused( array $rows, string $code ): void {
		$result = $this->translator()->plan( [ 'id' => 300, 'title' => 'Осмотр', 'status' => 'draft', 'acf' => [ 'prices' => $rows ] ] );

		$this->assertSame( $code, $result->get_error_code() );
	}

	public function test_price_rows_for_a_service_without_rows_are_refused_not_ignored(): void {
		$result = $this->translator()->plan( [ 'id' => 320, 'title' => 'Осмотр', 'status' => 'draft', 'acf' => [ 'prices' => [ [ 'title' => 'Осмотр' ] ] ] ] );

		$this->assertSame( 'dovira_prices_invalid', $result->get_error_code() );
	}

	public function test_a_translated_block_field_replaces_the_sources_and_the_others_stay_raw(): void {
		$plan = $this->translator()->plan(
			[
				'id'     => 300,
				'title'  => 'Осмотр',
				'status' => 'draft',
				'blocks' => [ [ 'block' => 'acf/hero', 'fields' => [ 'heading' => 'Привет' ] ] ],
			]
		);
		$plan['apply']();

		$data = json_decode( $this->writes[0][1]['post_content'], true )['attrs']['data'];
		$this->assertSame( 'Привет', $data['heading'] );
		$this->assertSame( '1', $data['is_visible'], 'kept as stored' );
		$this->assertSame( 'Текст', $data['text'], 'kept unformatted' );

		$block = array_values( array_filter( $plan['diff']->lines(), static fn( $l ) => 'blocks[0]' === $l['field'] ) )[0]['to'];
		$this->assertSame( 'acf/hero', $block['block'] );
		$this->assertStringNotContainsString( 'blocks', $plan['diff']->warnings()[0] );
	}

	public function test_the_parent_is_its_translation_or_none_with_a_warning(): void {
		$plan = $this->translator()->plan( [ 'id' => 400, 'title' => 'О клинике', 'status' => 'publish' ] );
		$plan['apply']();
		$this->assertSame( 411, $this->writes[0][1]['post_parent'] );
		$this->assertSame( 55, $this->writes[0][1]['_thumbnail_id'], 'a page keeps its featured image' );

		$this->writes = [];
		$plan         = $this->translator()->plan( [ 'id' => 420, 'title' => 'О клинике', 'status' => 'publish' ] );
		$plan['apply']();
		$this->assertArrayNotHasKey( 'post_parent', $this->writes[0][1] );
		$this->assertStringContainsString( '430', implode( ' ', $plan['diff']->warnings() ) );

		$this->assertSame( 'dovira_parent_invalid', $this->translator()->plan( [ 'id' => 420, 'title' => 'x', 'status' => 'draft', 'parent' => 410 ] )->get_error_code(), 'a Ukrainian parent' );
		$this->writes = [];
		$plan         = $this->translator()->plan( [ 'id' => 420, 'title' => 'x', 'status' => 'draft', 'parent' => 411 ] );
		$plan['apply']();
		$this->assertSame( 411, $this->writes[0][1]['post_parent'] );
	}

	public function test_terms_map_to_their_russian_terms_and_one_without_is_left_out(): void {
		$plan = $this->translator()->plan( [ 'id' => 500, 'title' => 'Акция', 'status' => 'draft' ] );
		$plan['apply']();

		$this->assertContains( [ 'terms:category', [ 9 ] ], $this->writes );
		$this->assertStringContainsString( 'Новини', implode( ' ', $plan['diff']->warnings() ) );
		$this->assertArrayNotHasKey( 'post_date', $this->writes[0][1], 'a draft source has no date to keep' );

		$this->writes = [];
		$plan         = $this->translator()->plan( [ 'id' => 500, 'title' => 'Акция', 'status' => 'draft', 'terms' => [ 'category' => [ 'Новости' ] ] ] );
		$plan['apply']();
		$this->assertContains( [ 'terms:category', [ 10 ] ], $this->writes, 'the payload names the set, in Russian' );
	}

	/**
	 * @return array<string, array{array<string, mixed>, string, string}>
	 */
	public static function refused_sources(): array {
		$base = [ 'title' => 'Осмотр', 'status' => 'draft' ];

		return [
			'a copy exists'   => [ [ 'id' => 310 ] + $base, 'dovira_translation_exists', '311' ],
			'a Russian post'  => [ [ 'id' => 350 ] + $base, 'dovira_not_source', '360' ],
			'no title'        => [ [ 'id' => 300, 'status' => 'draft' ], 'dovira_title_required', '' ],
			'no status'       => [ [ 'id' => 300, 'title' => 'x' ], 'dovira_status_required', '' ],
			'in the trash'    => [ [ 'id' => 600 ] + $base, 'dovira_post_in_trash', '' ],
			'a record'        => [ [ 'id' => 700 ] + $base, 'dovira_read_only_type', '' ],
			'no such post'    => [ [ 'id' => 999 ] + $base, 'dovira_post_not_found', '' ],
		];
	}

	/**
	 * @param array<string, mixed> $input
	 */
	#[DataProvider( 'refused_sources' )]
	public function test_a_copy_is_refused_before_anything_is_inserted( array $input, string $code, string $named ): void {
		Functions\expect( 'wp_insert_post' )->never();

		$result = $this->translator()->plan( $input );

		$this->assertSame( $code, $result->get_error_code() );
		$this->assertStringContainsString( $named, $result->get_error_message() );
	}

	public function test_without_polylang_there_is_no_copy(): void {
		$this->polylang = false;

		$this->assertSame( 'dovira_polylang_missing', $this->translator()->plan( [ 'id' => 300, 'title' => 'x', 'status' => 'draft' ] )->get_error_code() );
	}

	public function test_the_ability_previews_then_dumps_and_inserts_with_the_token(): void {
		$input = [ 'id' => 300, 'title' => 'Осмотр', 'status' => 'draft' ];

		$preview = $this->ability()->run( $input );
		$this->assertIsString( $preview['confirmation'] );
		$this->assertSame( [], $this->writes );

		$refused = $this->ability()->run( [ 'status' => 'publish' ] + $input + [ 'confirmation' => $preview['confirmation'] ] );
		$this->assertSame( 'dovira_confirmation_invalid', $refused->get_error_code() );
		$this->assertSame( [], $this->writes );

		$result = $this->ability()->run( $input + [ 'confirmation' => $preview['confirmation'] ] );
		$this->assertSame( [ 777 ], $result['written'] );
		$this->assertSame( [ 'dump', 'insert' ], array_slice( array_column( $this->writes, 0 ), 0, 2 ) );
	}

	public function test_permission_needs_edit_post_of_the_source_create_posts_and_publish_posts_to_publish(): void {
		$caps = [ 'edit_post', 'edit_posts', 'publish_posts' ];
		Functions\when( 'current_user_can' )->alias( static function ( string $cap ) use ( &$caps ) {
			return in_array( $cap, $caps, true );
		} );

		$this->assertTrue( CreateTranslation::can( [ 'id' => 300, 'status' => 'publish' ] ) );
		$this->assertTrue( CreateTranslation::can( [ 'id' => 999, 'status' => 'publish' ] ), 'a missing source hears "not found"' );

		$caps = [ 'edit_post', 'edit_posts' ];
		$this->assertFalse( CreateTranslation::can( [ 'id' => 300, 'status' => 'publish' ] ) );
		$this->assertTrue( CreateTranslation::can( [ 'id' => 300, 'status' => 'draft' ] ) );

		$caps = [ 'edit_posts' ];
		$this->assertFalse( CreateTranslation::can( [ 'id' => 300, 'status' => 'draft' ] ), 'no edit_post of the source' );

		$caps = [ 'edit_post' ];
		$this->assertFalse( CreateTranslation::can( [ 'id' => 300, 'status' => 'draft' ] ), 'no create_posts' );
	}

	public function test_create_translation_is_a_write_with_title_and_status_required(): void {
		$args = CreateTranslation::args();

		$this->assertFalse( $args['meta']['annotations']['readonly'] );
		$this->assertFalse( $args['meta']['mcp']['public'] );
		$this->assertSame( [ 'id', 'title', 'status' ], $args['input_schema']['required'] );
		$this->assertArrayNotHasKey( 'default', $args['input_schema']['properties']['status'] );
		$this->assertArrayHasKey( 'confirmation', $args['input_schema']['properties'] );
	}

	private function translator(): Translator {
		$reader = new PostReader( true, $this->polylang );

		return new Translator( $this->polylang, new PostWriter( true, $this->polylang, $reader, static fn() => [ 'acf/hero' ] ), $reader );
	}

	private function ability(): CreateTranslation {
		return new CreateTranslation(
			$this->translator(),
			function ( string $session ): string {
				$this->writes[] = [ 'dump', $session ];
				return 'dovira-x-2026-11-05-101500.sql.gz';
			}
		);
	}

	/**
	 * @return array<int, object>
	 */
	private static function posts(): array {
		$post = static fn( int $id, string $type, array $extra = [] ) => (object) ( $extra + [
			'ID'            => $id,
			'post_type'     => $type,
			'post_status'   => 'publish',
			'post_title'    => 'Запис',
			'post_name'     => 'zapys',
			'post_parent'   => 0,
			'menu_order'    => 0,
			'post_content'  => '',
			'post_modified' => '2026-11-01 10:00:00',
			'post_date'     => '2026-10-01 10:00:00',
			'post_date_gmt' => '2026-10-01 07:00:00',
		] );

		return [
			300 => $post( 300, 'service', [ 'menu_order' => 2, 'post_content' => self::hero_content() ] ),
			310 => $post( 310, 'service' ),
			320 => $post( 320, 'service' ),
			350 => $post( 350, 'service' ),
			400 => $post( 400, 'page', [ 'post_parent' => 410 ] ),
			410 => $post( 410, 'page' ),
			411 => $post( 411, 'page' ),
			420 => $post( 420, 'page', [ 'post_parent' => 430 ] ),
			500 => $post( 500, 'post', [ 'post_status' => 'draft', 'post_date_gmt' => '0000-00-00 00:00:00' ] ),
			600 => $post( 600, 'service', [ 'post_status' => 'trash' ] ),
			700 => $post( 700, 'conversation' ),
		];
	}

	private static function hero_content(): string {
		return (string) json_encode(
			[
				'blockName'    => 'acf/hero',
				'attrs'        => [
					'name' => 'acf/hero',
					'data' => [
						'is_visible'  => '1',
						'_is_visible' => 'field_hero_visible',
						'heading'     => 'Привіт',
						'_heading'    => 'field_hero_heading',
						'text'        => 'Текст',
						'_text'       => 'field_hero_text',
					],
				],
				'innerBlocks'  => [],
				'innerHTML'    => '',
				'innerContent' => [],
			],
			JSON_UNESCAPED_UNICODE
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function service_fields(): array {
		return [
			[ 'key' => 'field_service_image', 'name' => 'image', 'type' => 'image', 'required' => 1 ],
			[ 'key' => 'field_service_description', 'name' => 'description', 'type' => 'wysiwyg' ],
			[ 'key' => 'field_service_note', 'name' => 'note', 'type' => 'text' ],
			[ 'key' => 'field_service_prices', 'name' => 'prices', 'type' => 'repeater', 'sub_fields' => self::PRICE_SUBS ],
		];
	}

	/**
	 * A block's field objects from ACF's local meta, by name.
	 *
	 * @return array<string, array<string, mixed>>|false
	 */
	private function block_objects( string $id ) {
		$types   = array_column( self::HERO, 'type', 'name' );
		$objects = [];
		foreach ( $this->meta[ $id ] ?? [] as $name => $value ) {
			if ( ! str_starts_with( $name, '_' ) ) {
				$objects[ $name ] = [ 'name' => $name, 'type' => $types[ $name ] ?? 'text', 'value' => $value ];
			}
		}

		return $objects ?: false;
	}
}
