<?php
/**
 * Tests for dovira\ContentMcp\PriceRows — finding and changing a service's
 * price rows (test-critical zone «per-city price grouping of a Service»,
 * root CLAUDE.md).
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\ContentMcp;

use Brain\Monkey\Functions;
use dovira\ContentMcp\FindPriceRows;
use dovira\ContentMcp\PriceRows;
use dovira\Tests\TestCase;

foreach ( [ 'Catalogue', 'Scope', 'CityPrice', 'DescribeSite', 'Diff', 'ReadTools', 'PriceRows', 'FindPriceRows' ] as $dovira_class ) {
	require_once dirname( __DIR__, 3 ) . "/inc/features/content-mcp/{$dovira_class}.php";
}

/**
 * The price in this city is read and written in the row's own column; the
 * price follows into the Russian copy by index; nothing else of the row moves.
 */
final class PriceRowsTest extends TestCase {

	private const UK = 18;
	private const RU = 1641;

	/** Kharkiv's `service-city` term on this install. */
	private const KHARKIV = 4;

	/** @var array<int, list<array<string, mixed>>> Raw rows (keyed by field key) per post. */
	private array $raw = [];

	/** @var array<int, string> */
	private array $langs = [ self::UK => 'uk', self::RU => 'ru' ];

	/** @var list<int> */
	private array $editable = [ self::UK, self::RU ];

	/** @var list<array{0: string, 1: mixed, 2: int}> */
	private array $updated = [];

	protected function setUp(): void {
		parent::setUp();

		$this->raw = [
			self::UK => [
				self::row( 'Екстрений прийом', '1', '450', [] ),
				self::row( 'Аналіз крові', '0', '', [ 'field_c4' => '300', 'field_c7' => '320' ] ),
				self::row( 'УЗД', '0', '', [ 'field_c4' => '', 'field_c7' => '700' ] ),
			],
			self::RU => [
				self::row( 'Экстренный приём', '1', '450', [] ),
				self::row( 'Анализ крови', '0', '', [ 'field_c4' => '300', 'field_c7' => '320' ] ),
				self::row( 'УЗИ', '0', '', [ 'field_c4' => '', 'field_c7' => '700' ] ),
			],
		];

		Functions\when( 'get_site_url' )->justReturn( 'https://dev.dovira.vet' );
		Functions\when( 'get_term_by' )->alias(
			static fn( string $by, string $slug ) => 'kharkiv' === $slug ? (object) [ 'term_id' => self::KHARKIV ] : false
		);
		Functions\when( 'get_post' )->alias(
			static fn( int $id ) => match ( $id ) {
				self::UK, self::RU => (object) [ 'ID' => $id, 'post_type' => 'service', 'post_title' => 'Терапія' ],
				3                  => (object) [ 'ID' => 3, 'post_type' => 'page', 'post_title' => 'Про нас' ],
				default            => null,
			}
		);
		Functions\when( 'get_field_object' )->justReturn( self::field() );
		Functions\when( 'get_field' )->alias(
			function ( string $selector, int $id, bool $format = true ) {
				if ( $format ) {
					return array_map( [ self::class, 'formatted' ], $this->raw[ $id ] ?? [] );
				}
				return 'field_prices' === $selector ? ( $this->raw[ $id ] ?? false ) : null;
			}
		);
		Functions\when( 'pll_get_post_language' )->alias( fn( int $id ) => $this->langs[ $id ] ?? false );
		Functions\when( 'pll_get_post' )->alias( static fn( int $id, string $lang ) => self::UK === $id && 'ru' === $lang ? self::RU : 0 );
		Functions\when( 'current_user_can' )->alias( fn( string $cap, int $id ) => in_array( $id, $this->editable, true ) );
		Functions\when( 'update_field' )->alias(
			function ( string $key, $value, int $id ): bool {
				$this->updated[] = [ $key, $value, $id ];
				return true;
			}
		);
	}

	public function test_rows_are_found_by_title_or_description_in_any_case_with_the_price_in_this_city(): void {
		$rows = $this->rows()->find( 'АНАЛІЗ' );

		$this->assertSame(
			[
				[
					'service'     => [ 'id' => self::UK, 'title' => 'Терапія' ],
					'row_index'   => 1,
					'title'       => 'Аналіз крові',
					'description' => 'Опис: Аналіз крові',
					'price'       => 300,
					'per_city'    => true,
				],
			],
			$rows
		);
		$this->assertCount( 3, $this->rows()->find( 'опис' ), 'the description matches too' );
		$this->assertSame( [], $this->rows()->find( '   ' ) );
	}

	public function test_a_general_row_gives_its_price_and_an_empty_city_column_gives_null_never_a_number(): void {
		$rows = $this->rows()->find( 'опис' );

		$by_title = array_column( $rows, 'price', 'title' );
		$this->assertSame( 450, $by_title['Екстрений прийом'] );
		$this->assertNull( $by_title['УЗД'], 'Kyiv has 700, Kharkiv has none' );
	}

	public function test_only_ukrainian_services_are_searched(): void {
		$asked = [];
		Functions\when( 'get_posts' )->alias(
			static function ( array $args ) use ( &$asked ): array {
				$asked = $args;
				return [];
			}
		);

		( new PriceRows( true ) )->find( 'x' );

		$this->assertSame( 'service', $asked['post_type'] );
		$this->assertSame( 'uk', $asked['lang'] );
		$this->assertSame( -1, $asked['numberposts'] );
		$this->assertNotContains( 'trash', $asked['post_status'] );
	}

	public function test_a_general_price_is_written_to_price_and_follows_into_the_russian_row_of_the_same_index(): void {
		$plan = $this->rows()->update( self::UK, 0, [ 'price' => 550 ] );

		$this->assertSame(
			[
				[ 'target' => [ 'id' => self::UK, 'lang' => 'uk' ], 'field' => 'prices[0].price', 'from' => 450, 'to' => 550 ],
				[ 'target' => [ 'id' => self::RU, 'lang' => 'ru' ], 'field' => 'prices[0].price', 'from' => 450, 'to' => 550 ],
			],
			$plan['diff']->lines()
		);
		$this->assertSame( [], $plan['diff']->warnings() );
		$this->assertSame( [], $this->updated, 'planning writes nothing' );

		$this->assertSame( [ self::UK, self::RU ], $this->rows()->apply( $plan['writes'] ) );

		$this->assertSame( [ 'field_prices', self::UK ], [ $this->updated[0][0], $this->updated[0][2] ] );
		$this->assertSame( '550', $this->updated[0][1][0]['field_p'] );
		$this->assertSame( '550', $this->updated[1][1][0]['field_p'] );
		$this->assertSame( self::RU, $this->updated[1][2] );
	}

	public function test_a_per_city_price_is_written_to_this_citys_column_only_and_nothing_else_of_the_repeater_moves(): void {
		$plan = $this->rows()->update( self::UK, 1, [ 'price' => 350 ] );
		$this->rows()->apply( $plan['writes'] );

		$expected                            = $this->raw[ self::UK ];
		$expected[1]['field_cp']['field_c4'] = '350';
		$this->assertSame( $expected, $this->updated[0][1], 'is_price_general, cities, the Kyiv column, the other rows: as read' );
		$this->assertSame( [ 'city_4', 'city_7' ], $this->updated[0][1][1]['field_cities'] );
		$this->assertSame( '0', $this->updated[0][1][1]['field_g'] );
		$this->assertSame( 300, $plan['diff']->lines()[0]['from'] );
	}

	public function test_an_empty_city_column_is_shown_as_null_and_gets_the_price(): void {
		$plan = $this->rows()->update( self::UK, 2, [ 'price' => 650 ] );

		$this->assertNull( $plan['diff']->lines()[0]['from'] );
		$this->assertSame( '650', $plan['writes'][ self::UK ]['rows'][2]['field_cp']['field_c4'] );
		$this->assertSame( '700', $plan['writes'][ self::UK ]['rows'][2]['field_cp']['field_c7'] );
	}

	public function test_a_russian_copy_with_another_row_count_gets_a_warning_and_only_ukrainian_is_written(): void {
		array_pop( $this->raw[ self::RU ] );

		$plan = $this->rows()->update( self::UK, 0, [ 'price' => 550 ] );

		$this->assertSame( [ self::UK ], array_keys( $plan['writes'] ) );
		$this->assertCount( 1, $plan['diff']->lines() );
		$this->assertStringContainsString( 'має 2 рядків цін, українська — 3', $plan['diff']->warnings()[0] );
	}

	public function test_a_russian_copy_the_user_cannot_edit_is_not_written(): void {
		$this->editable = [ self::UK ];

		$plan = $this->rows()->update( self::UK, 0, [ 'price' => 550 ] );

		$this->assertSame( [ self::UK ], array_keys( $plan['writes'] ) );
		$this->assertNotEmpty( $plan['diff']->warnings() );
	}

	public function test_title_and_description_change_the_targeted_copy_only(): void {
		$plan = $this->rows()->update( self::UK, 0, [ 'title' => 'Екстрений прийом (нічний)', 'description' => 'Цілодобово' ] );

		$this->assertSame( [ self::UK ], array_keys( $plan['writes'] ) );
		$this->assertSame( [ 'prices[0].title', 'prices[0].description' ], array_column( $plan['diff']->lines(), 'field' ) );
		$this->assertSame( 'Екстрений прийом (нічний)', $plan['writes'][ self::UK ]['rows'][0]['field_t'] );
	}

	public function test_the_russian_copys_text_is_changed_on_the_russian_copy_itself(): void {
		$plan = $this->rows()->update( self::RU, 0, [ 'title' => 'Экстренный приём (ночной)' ] );

		$this->assertSame( [ self::RU ], array_keys( $plan['writes'] ) );
		$this->assertSame( 'ru', $plan['diff']->lines()[0]['target']['lang'] );
	}

	public function test_a_price_on_a_russian_copy_is_refused(): void {
		$result = $this->rows()->update( self::RU, 0, [ 'price' => 550 ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'dovira_price_in_source', $result->get_error_code() );
	}

	public function test_a_per_city_row_on_an_install_without_its_city_term_is_refused_but_a_general_row_is_not(): void {
		Functions\when( 'get_term_by' )->justReturn( false );

		$per_city = $this->rows()->update( self::UK, 1, [ 'price' => 350 ] );
		$general  = $this->rows()->update( self::UK, 0, [ 'price' => 550 ] );

		$this->assertSame( 'dovira_city_term_missing', $per_city->get_error_code() );
		$this->assertIsArray( $general );
	}

	/**
	 * @return array<string, array{int, int, array<string, mixed>, string}>
	 */
	public static function refusals(): array {
		return [
			'row out of range' => [ self::UK, 9, [ 'price' => 1 ], 'dovira_price_row_missing' ],
			'not a service'    => [ 3, 0, [ 'price' => 1 ], 'dovira_not_a_service' ],
			'missing post'     => [ 99, 0, [ 'price' => 1 ], 'dovira_post_not_found' ],
			'nothing to change' => [ self::UK, 0, [ 'cities' => [] ], 'dovira_nothing_to_change' ],
			'zero price'       => [ self::UK, 0, [ 'price' => 0 ], 'dovira_price_invalid' ],
			'text price'       => [ self::UK, 0, [ 'price' => 'договірна' ], 'dovira_price_invalid' ],
		];
	}

	/**
	 * @param array<string, mixed> $changes
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'refusals' )]
	public function test_an_invalid_change_is_refused_before_anything_is_planned( int $id, int $index, array $changes, string $code ): void {
		$result = $this->rows()->update( $id, $index, $changes );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( $code, $result->get_error_code() );
	}

	public function test_without_polylang_a_price_is_written_to_the_one_copy(): void {
		$plan = ( new PriceRows( false, fn() => [] ) )->update( self::UK, 0, [ 'price' => 550 ] );

		$this->assertSame( [ self::UK ], array_keys( $plan['writes'] ) );
		$this->assertNull( $plan['diff']->lines()[0]['target']['lang'] );
	}

	public function test_find_price_rows_pages_the_rows(): void {
		$result = ( new FindPriceRows() )->execute( [ 'text' => 'опис', 'page' => 2, 'per_page' => 1 ], $this->rows() );

		$this->assertSame( 3, $result['total'] );
		$this->assertSame( 'Аналіз крові', $result['items'][0]['title'] );
		$this->assertSame( [ 2, 1 ], [ $result['page'], $result['per_page'] ] );
	}

	private function rows(): PriceRows {
		return new PriceRows(
			true,
			static fn() => [ (object) [ 'ID' => self::UK, 'post_title' => 'Терапія' ] ]
		);
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, mixed>
	 */
	private static function row( string $title, string $general, string $price, array $columns ): array {
		return [
			'field_t'      => $title,
			'field_d'      => "Опис: {$title}",
			'field_cp'     => $columns,
			'field_p'      => $price,
			'field_l'      => '',
			'field_cities' => [ 'city_4', 'city_7' ],
			'field_g'      => $general,
		];
	}

	/**
	 * A raw row as ACF formats it: by name.
	 *
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	public static function formatted( array $raw ): array {
		return [
			'title'            => $raw['field_t'],
			'description'      => $raw['field_d'],
			'city_prices'      => [
				'price_city_4' => $raw['field_cp']['field_c4'] ?? '',
				'price_city_7' => $raw['field_cp']['field_c7'] ?? '',
			],
			'price'            => $raw['field_p'],
			'linked_page'      => '',
			'cities'           => $raw['field_cities'],
			'is_price_general' => '1' === $raw['field_g'],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function field(): array {
		return [
			'key'        => 'field_prices',
			'name'       => 'prices',
			'type'       => 'repeater',
			'sub_fields' => [
				[ 'key' => 'field_t', 'name' => 'title' ],
				[ 'key' => 'field_d', 'name' => 'description' ],
				[
					'key'        => 'field_cp',
					'name'       => 'city_prices',
					'sub_fields' => [
						[ 'key' => 'field_c4', 'name' => 'price_city_4' ],
						[ 'key' => 'field_c7', 'name' => 'price_city_7' ],
					],
				],
				[ 'key' => 'field_p', 'name' => 'price' ],
				[ 'key' => 'field_l', 'name' => 'linked_page' ],
				[ 'key' => 'field_cities', 'name' => 'cities' ],
				[ 'key' => 'field_g', 'name' => 'is_price_general' ],
			],
		];
	}
}
