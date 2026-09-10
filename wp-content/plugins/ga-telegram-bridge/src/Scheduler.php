<?php
/**
 * When the report goes out by itself.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The only class that schedules or clears the plugin's cron events.
 *
 * One recurring event carries the daily report; a settings save re-registers it
 * at the next occurrence of the configured time, deactivation clears it, and
 * everything it starts goes through Runner, so the date guard cannot be skipped
 * by a duplicate firing (DECISIONS "Scheduling, retries and idempotency on
 * WP-Cron").
 */
final class Scheduler {

	/**
	 * The recurring event that sends the daily report.
	 */
	public const DAILY_HOOK = 'gatb_daily_report';

	/**
	 * The single event a failed run schedules to try the same day again. It
	 * carries the day it is for as its one argument.
	 */
	public const RETRY_HOOK = 'gatb_retry_report';

	/**
	 * How long after a failure the day is tried again, in seconds.
	 *
	 * One hour is fixed by DECISIONS "Scheduling, retries and idempotency on
	 * WP-Cron"; how many times is what the site configures (`max_attempts`).
	 * Written out rather than taken from HOUR_IN_SECONDS: the unit tests load
	 * no WordPress.
	 */
	public const RETRY_DELAY = 3600;

	/**
	 * How long wp-cron.php may go unvisited before the screen says so, in
	 * seconds. Written out rather than taken from DAY_IN_SECONDS: the unit
	 * tests load no WordPress.
	 */
	public const CRON_HIT_MAX_AGE = 86400;

	/**
	 * Registers the daily event at the next occurrence of the configured time.
	 *
	 * Whatever was registered before is cleared first, so a changed send time
	 * replaces the event instead of adding a second one. Hooked on the settings
	 * save and called on activation.
	 */
	public static function reschedule(): void {
		wp_clear_scheduled_hook( self::DAILY_HOOK );

		wp_schedule_event(
			self::next_occurrence( wp_timezone(), Settings::send_time(), time() ),
			'daily',
			self::DAILY_HOOK
		);
	}

	/**
	 * Books one more attempt at a day, an hour from now.
	 *
	 * The day travels with the event: an hour later "yesterday" may be another
	 * day, and the attempt is about the day it was booked for.
	 *
	 * @param string   $date The day the report was about, Y-m-d.
	 * @param int|null $now  The current Unix time; injected by the tests.
	 */
	public static function schedule_retry( string $date, ?int $now = null ): void {
		wp_schedule_single_event( ( $now ?? time() ) + self::RETRY_DELAY, self::RETRY_HOOK, array( $date ) );
	}

	/**
	 * The callback of the retry event: one run for the day it was booked for.
	 *
	 * The default covers an event scheduled without its argument — by hand, or
	 * by a version of this plugin that did not pass one — which would otherwise
	 * be a fatal error inside WP-Cron rather than a run.
	 *
	 * @param string $date The day this attempt is for, Y-m-d.
	 */
	public static function run_retry( string $date = '' ): void {
		Runner::run( 'retry', false, null, '' !== $date ? $date : null );
	}

	/**
	 * Clears every event the plugin owns. Called on deactivation.
	 *
	 * Unscheduling is by hook, not by hook and arguments:
	 * wp_clear_scheduled_hook() takes the arguments an event was registered
	 * with and, called with none, unschedules only the events that have none —
	 * which the retry, carrying its day, never is (wp-includes/cron.php).
	 */
	public static function clear(): void {
		wp_unschedule_hook( self::DAILY_HOOK );
		wp_unschedule_hook( self::RETRY_HOOK );
	}

	/**
	 * Returns when the report is due next, or null when nothing is scheduled.
	 *
	 * WP-Cron's own answer is the only copy of this. A timestamp of our own
	 * would have to be invalidated on every reschedule and could then only
	 * disagree with the event that really fires.
	 */
	public static function next_run(): ?int {
		$timestamp = wp_next_scheduled( self::DAILY_HOOK );

		return false === $timestamp ? null : (int) $timestamp;
	}

	/**
	 * The callback of the daily event: one run, with the date guard on.
	 *
	 * The guard compares the day of the report that was built — the property's
	 * own day, not the server's (DECISIONS "The date guard runs after the report
	 * is built, not before") — so a second firing for a day already delivered
	 * sends nothing and logs nothing.
	 */
	public static function run_daily(): void {
		Runner::run( 'cron' );
	}

	/**
	 * Remembers a visit to wp-cron.php, on the requests that are one.
	 *
	 * Hooked on every request, cheap on all but the few that run as cron: an
	 * install with WP-Cron switched on spawns wp-cron.php only when an event is
	 * due, and one with it switched off is visited by its system cron.
	 */
	public static function note_cron_hit(): void {
		if ( ! wp_doing_cron() ) {
			return;
		}

		RunLog::mark_cron_hit();
	}

	/**
	 * Tells whether the site has switched WP-Cron off without putting anything
	 * in its place.
	 *
	 * Nothing else can be concluded from a missing hit: while WP-Cron is on,
	 * WordPress visits wp-cron.php by itself when something is due, so silence
	 * only means nothing was due.
	 *
	 * @param bool $wp_cron_disabled Whether DISABLE_WP_CRON is set in wp-config.php.
	 * @param int  $last_hit         When wp-cron.php last ran here; 0 for never.
	 * @param int  $now              The current Unix time.
	 */
	public static function external_cron_missing( bool $wp_cron_disabled, int $last_hit, int $now ): bool {
		if ( ! $wp_cron_disabled ) {
			return false;
		}

		return 0 === $last_hit || $now - $last_hit > self::CRON_HIT_MAX_AGE;
	}

	/**
	 * Returns the next moment the configured time of day comes round.
	 *
	 * The time is site-local and the event is registered in UTC, which is what
	 * WP-Cron stores, so the conversion happens here and only here. A time of
	 * day that has already passed today belongs to tomorrow — as does the
	 * moment that is exactly it, because an event registered for "now" would
	 * fire twice on a site that is being saved at its own send time. On the two
	 * days a year the clock jumps, "tomorrow at 09:00" is a local time, not
	 * 24 hours: the day the hour is lost is 23 hours long, and a local time
	 * that does not exist at all resolves forward, the way PHP resolves it.
	 *
	 * @param DateTimeZone $zone      The site's time zone (wp_timezone()).
	 * @param string       $send_time The configured time of day, HH:MM.
	 * @param int          $now       The current Unix time.
	 */
	public static function next_occurrence( DateTimeZone $zone, string $send_time, int $now ): int {
		// explode() always yields a first part; a value without a colon is read
		// as an hour, and Settings has already refused anything but HH:MM.
		$parts   = array_map( 'intval', explode( ':', $send_time ) );
		$hours   = $parts[0];
		$minutes = isset( $parts[1] ) ? $parts[1] : 0;

		$due = ( new DateTimeImmutable( '@' . $now ) )
			->setTimezone( $zone )
			->setTime( $hours, $minutes );

		if ( $due->getTimestamp() <= $now ) {
			$due = $due->modify( '+1 day' );
		}

		return $due->getTimestamp();
	}
}
