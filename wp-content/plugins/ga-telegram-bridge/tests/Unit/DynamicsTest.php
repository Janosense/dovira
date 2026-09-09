<?php
/**
 * Tests for the report's arithmetic — the rules it encodes, not just its output.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge\Tests\Unit;

use GaTelegramBridge\Dynamics;
use GaTelegramBridge\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Pure arithmetic: nothing is stubbed here because nothing is called.
 */
final class DynamicsTest extends TestCase {

	/**
	 * The baseline divides by the length of the period, not by the busy days.
	 */
	public function test_the_average_divides_by_the_whole_period(): void {
		$this->assertSame( 100.0, Dynamics::average( 700, 7 ) );
		$this->assertSame(
			100.0,
			Dynamics::average( 700, 7 ),
			'700 visitors in a week is 100 a day even if four days had none'
		);
		$this->assertSame( 0.0, Dynamics::average( 0, 7 ) );
	}

	/**
	 * A period of no length is not divided by.
	 */
	public function test_an_empty_period_averages_to_zero(): void {
		$this->assertSame( 0.0, Dynamics::average( 700, 0 ) );
	}

	/**
	 * The change is a whole per cent, in both directions.
	 *
	 * @param int|float $current  What was measured.
	 * @param int|float $baseline What it is compared with.
	 * @param int|null  $expected The change the report shows.
	 */
	#[DataProvider( 'provide_changes' )]
	public function test_the_change_is_rounded_to_whole_per_cent( $current, $baseline, ?int $expected ): void {
		$this->assertSame( $expected, Dynamics::change( $current, $baseline ) );
	}

	/**
	 * The comparisons the report actually makes.
	 *
	 * @return array<string, array{int|float, int|float, int|null}>
	 */
	public static function provide_changes(): array {
		return array(
			'a third more than usual' => array( 133, 100.0, 33 ),
			'a fifth fewer'           => array( 80, 100.0, -20 ),
			'exactly the same'        => array( 100, 100.0, 0 ),
			'rounded up'              => array( 1559, 1577, -1 ),
			'rounded from a half'     => array( 105, 66.6, 58 ),
			'down to nothing'         => array( 0, 42.0, -100 ),
			'more than doubled'       => array( 250, 100.0, 150 ),
		);
	}

	/**
	 * A rise from nothing is not a percentage, and is not reported as one.
	 */
	public function test_a_zero_baseline_has_no_change(): void {
		$this->assertNull( Dynamics::change( 42, 0 ), 'a new site every morning' );
		$this->assertNull( Dynamics::change( 0, 0 ), 'and one that had nobody twice' );
		$this->assertNull( Dynamics::change( 42, 0.0 ) );
	}

	/**
	 * A share is a whole per cent of its block.
	 */
	public function test_a_share_is_a_whole_per_cent_of_the_block(): void {
		$this->assertSame( 50, Dynamics::share( 50, 100 ) );
		$this->assertSame( 1, Dynamics::share( 1, 100 ) );
		$this->assertSame( 100, Dynamics::share( 7, 7 ) );
	}

	/**
	 * An empty block has no shares to give.
	 */
	public function test_an_empty_block_has_no_shares(): void {
		$this->assertNull( Dynamics::share( 0, 0 ) );
	}

	/**
	 * Rows are rounded on their own, so a block need not add up to 100.
	 *
	 * Stated as a test rather than left to be discovered: the renderer prints
	 * what it is given, and 33 + 33 + 33 is the honest reading of three equal
	 * thirds. Forcing the last row to 34 would misreport that row.
	 */
	public function test_shares_are_rounded_independently_and_need_not_total_100(): void {
		$shares = array(
			Dynamics::share( 1, 3 ),
			Dynamics::share( 1, 3 ),
			Dynamics::share( 1, 3 ),
		);

		$this->assertSame( array( 33, 33, 33 ), $shares );
		$this->assertSame( 99, array_sum( $shares ) );
	}

	/**
	 * The rounding is half-up, which is what a reader expects of a percentage.
	 */
	public function test_a_share_of_a_half_rounds_up(): void {
		$this->assertSame( 3, Dynamics::share( 25, 1000 ) );
		$this->assertSame( 13, Dynamics::share( 125, 1000 ) );
	}
}
