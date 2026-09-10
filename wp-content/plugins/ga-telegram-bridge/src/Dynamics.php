<?php
/**
 * The arithmetic of the daily report.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge;

/**
 * The three sums the report is built on, kept apart from where the numbers
 * come from and from how they are printed.
 *
 * Nothing here touches WordPress, Google or a setting: it takes numbers and
 * returns numbers, which is why docs/TESTING.md forbids stubbing it.
 */
final class Dynamics {

	/**
	 * Returns the daily average of a total measured over a whole period.
	 *
	 * The divisor is the length of the period, not the number of days that
	 * happened to have traffic: a week with three silent days is a quiet week,
	 * and that is exactly what "yesterday against the usual day" has to show.
	 *
	 * @param int $total The metric for the whole period.
	 * @param int $days  How many days the period covers.
	 */
	public static function average( int $total, int $days ): float {
		if ( $days <= 0 ) {
			return 0.0;
		}

		return $total / $days;
	}

	/**
	 * Returns the change of a value against a baseline, in whole per cent.
	 *
	 * Null when the baseline is zero: a rise from nothing is not a percentage,
	 * and the report prints "—" instead of inventing one.
	 *
	 * @param int|float $current  What was measured.
	 * @param int|float $baseline What it is compared with.
	 */
	public static function change( $current, $baseline ): ?int {
		if ( 0.0 === (float) $baseline ) {
			return null;
		}

		return (int) round( ( ( (float) $current - (float) $baseline ) / (float) $baseline ) * 100 );
	}

	/**
	 * Returns a row's share of its block, in whole per cent.
	 *
	 * Each row is rounded on its own, so the shares of a block need not add up
	 * to exactly 100 — three equal rows are 33, 33 and 33. Forcing the last row
	 * to absorb the remainder would misreport it.
	 *
	 * @param int $value The row's metric.
	 * @param int $total The block's total.
	 */
	public static function share( int $value, int $total ): ?int {
		if ( 0 === $total ) {
			return null;
		}

		return (int) round( ( $value / $total ) * 100 );
	}
}
