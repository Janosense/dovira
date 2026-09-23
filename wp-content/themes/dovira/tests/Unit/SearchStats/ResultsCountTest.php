<?php
/**
 * Tests for dovira\SearchStats\ResultsCount.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\SearchStats;

use dovira\SearchStats\ResultsCount;
use dovira\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/ResultsCount.php';

/**
 * The count follows what search.php lists, not what it collects.
 */
final class ResultsCountTest extends TestCase {

	private static function post( int $id, int $parent = 0 ): object {
		return (object) [ 'ID' => $id, 'post_parent' => $parent ];
	}

	/**
	 * @return array<string, array{array<int, array<string, mixed>>, array<string, array<int, object>>, int}>
	 */
	public static function results(): array {
		$row = [ 'title' => 'Вакцинація', 'price' => '500' ];

		return [
			'nothing found'                  => [ [], [], 0 ],
			'price rows only'                => [ [ $row, $row, $row ], [], 3 ],
			'every listed kind'              => [
				[ $row, $row ],
				[
					'service'  => [ 10 => self::post( 10 ) ],
					'post'     => [ 20 => self::post( 20 ), 21 => self::post( 21 ) ],
					'page'     => [ 30 => self::post( 30 ) ],
					'employee' => [ 40 => self::post( 40 ) ],
				],
				7,
			],
			'a child service is not listed'  => [
				[],
				[ 'service' => [ 10 => self::post( 10 ), 11 => self::post( 11, 5 ) ] ],
				1,
			],
			'types the page does not list'   => [
				[],
				[
					'vacancy'    => [ 50 => self::post( 50 ) ],
					'attachment' => [ 60 => self::post( 60 ) ],
					'page'       => [ 30 => self::post( 30 ) ],
				],
				1,
			],
		];
	}

	/**
	 * @param array<int, array<string, mixed>>  $sub_services
	 * @param array<string, array<int, object>> $search_posts
	 */
	#[DataProvider( 'results' )]
	public function test_shown( array $sub_services, array $search_posts, int $expected ): void {
		$this->assertSame( $expected, ResultsCount::shown( $sub_services, $search_posts ) );
	}
}
