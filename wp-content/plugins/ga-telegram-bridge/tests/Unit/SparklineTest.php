<?php
/**
 * The sparkline helper.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge\Tests\Unit;

use GaTelegramBridge\Sparkline;
use GaTelegramBridge\Tests\TestCase;

/**
 * Nothing is stubbed here: the helper touches no WordPress at all.
 */
final class SparklineTest extends TestCase {

	/**
	 * A rise through eight values uses each height once, shortest first.
	 */
	public function test_a_rising_series_uses_every_height_in_order(): void {
		$this->assertSame( '▁▂▃▄▅▆▇█', Sparkline::render( array( 1, 2, 3, 4, 5, 6, 7, 8 ) ) );
	}

	/**
	 * A week that never moved is drawn at mid height, one character per day.
	 */
	public function test_a_flat_series_is_drawn_at_mid_height(): void {
		$this->assertSame( '▄▄▄▄▄', Sparkline::render( array( 61, 61, 61, 61, 61 ) ) );
	}

	/**
	 * A property nobody visited is flat too — and flat is not the floor.
	 *
	 * The scale runs between the series' own ends, so all-zero has no low point
	 * to mark. Drawing it at the floor would read as "the worst it has ever
	 * been" rather than "nothing happened, every day the same".
	 */
	public function test_a_series_of_zeros_is_flat_and_not_at_the_floor(): void {
		$line = Sparkline::render( array_fill( 0, 28, 0 ) );

		$this->assertSame( str_repeat( '▄', 28 ), $line );
		$this->assertStringNotContainsString( '▁', $line );
	}

	/**
	 * One busy day among quiet ones is the only full block.
	 */
	public function test_a_single_spike_is_the_only_full_block(): void {
		$this->assertSame( '▁▁█▁', Sparkline::render( array( 0, 0, 9, 0 ) ) );
	}

	/**
	 * Values close to each other share a height when the range dwarfs them.
	 *
	 * The decision asks for the nearest level, not for one band per distinct
	 * value: two days that differ by one visitor in a month that peaked at 80
	 * really are the same height.
	 */
	public function test_close_values_land_on_the_same_height(): void {
		$line = Sparkline::render( array( 10, 11, 80 ) );

		$this->assertSame( '▁▁█', $line );
	}

	/**
	 * Twenty-eight days in, twenty-eight characters out.
	 *
	 * Counted in characters and not in bytes: each block element is three of
	 * them, so strlen() would say 84 and be no use to anyone.
	 */
	public function test_twenty_eight_values_give_twenty_eight_characters(): void {
		$series = array();

		foreach ( range( 1, 28 ) as $day ) {
			$series[] = $day * 3;
		}

		$this->assertSame( 28, mb_strlen( Sparkline::render( $series ), 'UTF-8' ) );
	}

	/**
	 * Nothing to draw is an empty string, whichever way it is nothing.
	 *
	 * The renderer asks this helper once and decides from what comes back,
	 * rather than testing the block switch and the row count separately.
	 */
	public function test_an_empty_or_missing_series_draws_nothing(): void {
		$this->assertSame( '', Sparkline::render( null ) );
		$this->assertSame( '', Sparkline::render( array() ) );
	}
}
