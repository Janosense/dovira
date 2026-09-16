<?php
/**
 * A series of numbers drawn as one line of block characters.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge;

/**
 * Draws a series as a sparkline: one character per value, tallest for the
 * largest.
 *
 * Pure: no WordPress, no settings, no escaping. The characters are the eight
 * Unicode block elements, which every Telegram client can draw and which line
 * up in a monospace run — the message wraps the result in <code> for exactly
 * that reason.
 *
 * The scale runs from the series' own minimum to its own maximum rather than
 * from zero (DECISIONS "A 28-day trend sparkline in the visitors block, drawn
 * with block characters"): a site whose traffic is steady would otherwise be a
 * flat row of full blocks, which says less than nothing. The price is that the
 * line shows shape and not size, which is why the two real numbers are printed
 * beside it.
 */
final class Sparkline {

	/**
	 * The eight heights, shortest first.
	 */
	private const LEVELS = array( '▁', '▂', '▃', '▄', '▅', '▆', '▇', '█' );

	/**
	 * The height a series with nothing to compare is drawn at.
	 *
	 * Mid height, not the floor: every day of a flat week was equal, and none
	 * of them was a low point.
	 */
	private const FLAT = '▄';

	/**
	 * Draws one character per value, scaled between the series' own ends.
	 *
	 * @param list<int>|null $series The values, oldest first, or null when the block is off.
	 */
	public static function render( ?array $series ): string {
		if ( null === $series || array() === $series ) {
			return '';
		}

		$lowest  = min( $series );
		$highest = max( $series );

		if ( $lowest === $highest ) {
			return str_repeat( self::FLAT, count( $series ) );
		}

		$steps = count( self::LEVELS ) - 1;
		$line  = '';

		foreach ( $series as $value ) {
			$level = (int) round( ( $value - $lowest ) / ( $highest - $lowest ) * $steps );
			$line .= self::LEVELS[ $level ];
		}

		return $line;
	}
}
