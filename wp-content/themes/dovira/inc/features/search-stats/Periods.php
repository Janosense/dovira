<?php

namespace dovira\SearchStats;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The two periods the daily report reads, as calendar days in the site's zone
 * (wp_timezone()) turned into UTC bounds, because created_at is stored in UTC
 * (DECISIONS "Three blocks, each with a yesterday list and a 28-day list").
 *
 * Both periods end at the local midnight that starts today, and each bound is
 * found by stepping back whole calendar days from it — never by subtracting a
 * number of seconds — so the night the clocks change gives a yesterday of 23
 * or 25 hours by itself. Bounds are half-open: from <= created_at < to.
 */
final class Periods {

	/** The long period: the 28 calendar days that end with yesterday, as the plugin's 28daysAgo…yesterday. */
	public const DAYS = 28;

	/**
	 * @param int $now Unix time the periods are measured from.
	 * @return array{from: string, to: string} UTC bounds as Y-m-d H:i:s.
	 */
	public static function yesterday( int $now ): array {
		return self::ending_today( $now, 1 );
	}

	/**
	 * @param int $now Unix time the periods are measured from.
	 * @return array{from: string, to: string} UTC bounds as Y-m-d H:i:s.
	 */
	public static function last_28_days( int $now ): array {
		return self::ending_today( $now, self::DAYS );
	}

	/**
	 * The given number of whole local days that end at today's local midnight.
	 *
	 * @return array{from: string, to: string}
	 */
	private static function ending_today( int $now, int $days ): array {
		$today = ( new DateTimeImmutable( '@' . $now ) )->setTimezone( wp_timezone() )->setTime( 0, 0 );

		return [
			'from' => self::utc( $today->modify( '-' . $days . ' days' ) ),
			'to'   => self::utc( $today ),
		];
	}

	private static function utc( DateTimeImmutable $moment ): string {
		return $moment->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}
}
