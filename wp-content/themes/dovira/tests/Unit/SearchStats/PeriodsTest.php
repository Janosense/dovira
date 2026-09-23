<?php
/**
 * Tests for dovira\SearchStats\Periods.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\SearchStats;

use Brain\Monkey\Functions;
use DateTimeZone;
use dovira\SearchStats\Periods;
use dovira\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/Periods.php';

/**
 * Yesterday and the last 28 days as UTC bounds of calendar days in the site's zone.
 *
 * The expected bounds were computed with PHP's own DateTimeImmutable over
 * Europe/Kyiv, whose 2026 clock changes fall on 2026-03-29 01:00 UTC (to +3)
 * and 2026-10-25 01:00 UTC (back to +2).
 */
final class PeriodsTest extends TestCase {

	/**
	 * @return array<string, array{int, array{from: string, to: string}, array{from: string, to: string}}>
	 */
	public static function clocks(): array {
		return [
			'an ordinary afternoon, 2026-09-23 12:00 UTC'               => [
				1790164800,
				[ 'from' => '2026-09-21 21:00:00', 'to' => '2026-09-22 21:00:00' ],
				[ 'from' => '2026-08-25 21:00:00', 'to' => '2026-09-22 21:00:00' ],
			],
			'the last second before local midnight, 20:59:59 UTC'        => [
				1790110799,
				[ 'from' => '2026-09-20 21:00:00', 'to' => '2026-09-21 21:00:00' ],
				[ 'from' => '2026-08-24 21:00:00', 'to' => '2026-09-21 21:00:00' ],
			],
			'local midnight while UTC is still on the 22nd, 21:00 UTC'   => [
				1790110800,
				[ 'from' => '2026-09-21 21:00:00', 'to' => '2026-09-22 21:00:00' ],
				[ 'from' => '2026-08-25 21:00:00', 'to' => '2026-09-22 21:00:00' ],
			],
			'the morning after the spring night: yesterday is 23 hours' => [
				1774854000,
				[ 'from' => '2026-03-28 22:00:00', 'to' => '2026-03-29 21:00:00' ],
				[ 'from' => '2026-03-01 22:00:00', 'to' => '2026-03-29 21:00:00' ],
			],
			'the morning after the autumn night: yesterday is 25 hours' => [
				1792998000,
				[ 'from' => '2026-10-24 21:00:00', 'to' => '2026-10-25 22:00:00' ],
				[ 'from' => '2026-09-27 21:00:00', 'to' => '2026-10-25 22:00:00' ],
			],
			'28 days across the spring change'                          => [
				1775804400,
				[ 'from' => '2026-04-08 21:00:00', 'to' => '2026-04-09 21:00:00' ],
				[ 'from' => '2026-03-12 22:00:00', 'to' => '2026-04-09 21:00:00' ],
			],
			'28 days across the autumn change'                          => [
				1793862000,
				[ 'from' => '2026-11-03 22:00:00', 'to' => '2026-11-04 22:00:00' ],
				[ 'from' => '2026-10-07 21:00:00', 'to' => '2026-11-04 22:00:00' ],
			],
		];
	}

	/**
	 * @param array{from: string, to: string} $yesterday
	 * @param array{from: string, to: string} $last_28_days
	 */
	#[DataProvider( 'clocks' )]
	public function test_the_two_periods_for_a_clock( int $now, array $yesterday, array $last_28_days ): void {
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'Europe/Kyiv' ) );

		$this->assertSame( $yesterday, Periods::yesterday( $now ) );
		$this->assertSame( $last_28_days, Periods::last_28_days( $now ) );
	}

	/**
	 * The zone the local install and the committed database snapshot carry: a
	 * bare offset with no name, so no clock change — the autumn night is 24 hours.
	 */
	public function test_a_fixed_offset_has_no_clock_change(): void {
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( '+03:00' ) );

		$this->assertSame(
			[ 'from' => '2026-10-24 21:00:00', 'to' => '2026-10-25 21:00:00' ],
			Periods::yesterday( 1792998000 )
		);
		$this->assertSame(
			[ 'from' => '2026-09-27 21:00:00', 'to' => '2026-10-25 21:00:00' ],
			Periods::last_28_days( 1792998000 )
		);
	}
}
