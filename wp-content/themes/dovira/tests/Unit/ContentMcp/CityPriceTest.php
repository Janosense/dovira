<?php
/**
 * Tests for dovira\ContentMcp\CityPrice — the per-city price of a service row
 * (test-critical zone «per-city price grouping of a Service», root CLAUDE.md).
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\ContentMcp;

use Brain\Monkey\Functions;
use dovira\ContentMcp\CityPrice;
use dovira\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/CityPrice.php';

/**
 * A row's price in this install's city is the site's own rule: the general
 * price, or the city's column; no amount is null, never a made-up number.
 */
final class CityPriceTest extends TestCase {

	public function test_a_general_row_gives_its_price(): void {
		$row = [ 'is_price_general' => true, 'price' => '450', 'city_prices' => [ 'price_city_4' => '999' ] ];

		$this->assertSame( 450, CityPrice::resolve( $row, 4 ) );
	}

	public function test_a_per_city_row_gives_the_own_city_column(): void {
		$row = [ 'is_price_general' => false, 'price' => '', 'city_prices' => [ 'price_city_4' => '550', 'price_city_7' => '600' ] ];

		$this->assertSame( 550, CityPrice::resolve( $row, 4 ) );
		$this->assertSame( 600, CityPrice::resolve( $row, 7 ) );
	}

	/**
	 * @return array<string, array{array<string, mixed>}>
	 */
	public static function no_amount(): array {
		return [
			'empty column'          => [ [ 'is_price_general' => false, 'city_prices' => [ 'price_city_4' => '' ] ] ],
			'missing column'        => [ [ 'is_price_general' => false, 'city_prices' => [ 'price_city_9' => '300' ] ] ],
			'no city_prices at all' => [ [ 'is_price_general' => false ] ],
			'general, empty price'  => [ [ 'is_price_general' => true, 'price' => '' ] ],
			'zero, as the site'     => [ [ 'is_price_general' => true, 'price' => '0' ] ],
			'not a number'          => [ [ 'is_price_general' => true, 'price' => 'договірна' ] ],
		];
	}

	/**
	 * @param array<string, mixed> $row
	 */
	#[DataProvider( 'no_amount' )]
	public function test_no_amount_is_null_never_a_number( array $row ): void {
		$this->assertNull( CityPrice::resolve( $row, 4 ) );
	}

	public function test_an_install_without_its_city_term_has_no_per_city_prices_but_keeps_general_ones(): void {
		$rows = CityPrice::rows(
			[
				[ 'title' => 'a', 'is_price_general' => true, 'price' => '100' ],
				[ 'title' => 'b', 'is_price_general' => false, 'price' => '', 'city_prices' => [ 'price_city_4' => '200' ] ],
			],
			null
		);

		$this->assertSame( 100, $rows[0]['price'] );
		$this->assertNull( $rows[1]['price'] );
	}

	public function test_rows_resolve_price_and_drop_the_per_city_columns_keeping_the_rest(): void {
		$rows = CityPrice::rows(
			[
				[
					'title'            => 'Екстрений прийом',
					'price'            => '',
					'city_prices'      => [ 'price_city_4' => '500' ],
					'cities'           => [ 'city_4' ],
					'is_price_general' => false,
				],
			],
			4
		);

		$this->assertSame(
			[
				'title'            => 'Екстрений прийом',
				'price'            => 500,
				'cities'           => [ 'city_4' ],
				'is_price_general' => false,
			],
			$rows[0]
		);
	}

	public function test_a_fractional_price_stays_a_number(): void {
		$this->assertSame( 12.5, CityPrice::resolve( [ 'is_price_general' => true, 'price' => '12.5' ], 4 ) );
	}

	/**
	 * @return array<string, array{string, int|null}>
	 */
	public static function cities(): array {
		return [
			'Kharkiv term' => [ 'kharkiv', 4 ],
			'Kyiv term'    => [ 'kyiv', 7 ],
			'no such term' => [ 'odesa', null ],
		];
	}

	#[DataProvider( 'cities' )]
	public function test_the_own_term_is_found_by_the_city_slug( string $city, ?int $expected ): void {
		Functions\when( 'get_term_by' )->alias(
			static function ( string $field, string $value, string $taxonomy ) {
				$terms = [ 'kharkiv' => 4, 'kyiv' => 7 ];
				return 'slug' === $field && 'service-city' === $taxonomy && isset( $terms[ $value ] )
					? (object) [ 'term_id' => $terms[ $value ] ]
					: false;
			}
		);

		$this->assertSame( $expected, CityPrice::term_id( $city ) );
	}
}
