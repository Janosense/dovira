<?php
/**
 * One run of the daily report, from Google to Telegram to the log.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge;

/**
 * The one path a report ever takes: build, render, send, log, remember.
 *
 * Every caller goes through here — the *Send now* button today, the cron
 * callback from Sprint 2 — so that the date guard and the log cannot be
 * bypassed by accident, only on purpose.
 */
final class Runner {

	/**
	 * Runs the report once and returns the entry it wrote.
	 *
	 * Null means the run stopped at the date guard: the report for that day had
	 * already gone out, nothing was sent and nothing was logged, because
	 * nothing happened.
	 *
	 * @param string      $trigger           What started this run: cron, retry or manual.
	 * @param bool        $bypass_date_guard Whether to send even if the day was already sent.
	 * @param int|null    $now               The current Unix time; injected by the tests.
	 * @param string|null $for_date          The day a retry was booked for; null for a run about whatever yesterday is now.
	 * @return array{time: int, trigger: string, date: string, status: string, attempt: int, message: string}|null
	 */
	public static function run( string $trigger, bool $bypass_date_guard = false, ?int $now = null, ?string $for_date = null ): ?array {
		try {
			$report = ReportBuilder::build( $now );
		} catch ( GaClientException | GoogleAuthException $unreadable ) {
			// A day that could not be read must not look like a day with no
			// visitors, so it is logged as a failure and nothing is sent. A
			// retry knows which day it is about; a first attempt has to guess.
			return self::failed(
				$trigger,
				null !== $for_date ? $for_date : self::site_yesterday( $now ?? time() ),
				$unreadable->getMessage(),
				$now
			);
		}

		if ( null !== $for_date && $for_date !== $report->date ) {
			// Every range this plugin asks Google for is relative and resolved
			// in the property's zone (FEATURE.md → Invariants), so once the
			// property's day has turned, the day this attempt was booked for
			// cannot be read again — and no later attempt could do better. The
			// report in hand belongs to the run the daily event will make at
			// the configured time, not to this one.
			return self::failed(
				$trigger,
				$for_date,
				sprintf(
					/* translators: 1: the day the attempt was for, 2: the day the property now calls yesterday. */
					__( 'The report for %1$s can no longer be built: the property now calls %2$s yesterday.', 'ga-telegram-bridge' ),
					$for_date,
					$report->date
				),
				$now,
				false
			);
		}

		if ( ! $bypass_date_guard && RunLog::last_report_date() === $report->date ) {
			return null;
		}

		try {
			TelegramClient::send_message( Settings::telegram_chat_id(), MessageRenderer::render( $report ) );
		} catch ( TelegramException $refused ) {
			return self::failed( $trigger, $report->date, $refused->getMessage(), $now );
		}

		RunLog::mark_sent( $report->date );

		return RunLog::add(
			$trigger,
			$report->date,
			'sent',
			self::attempt(),
			sprintf(
				/* translators: 1: the day the report was about, 2: the Telegram chat it went to. */
				__( 'The report for %1$s was sent to chat %2$s.', 'ga-telegram-bridge' ),
				$report->date,
				Settings::telegram_chat_id()
			)
		);
	}

	/**
	 * Records a run that did not deliver, counts the attempt, and books the
	 * next one while the day has attempts left.
	 *
	 * The last sent day is deliberately left as it was: the day stays unsent.
	 *
	 * @param string   $trigger       The trigger of the run.
	 * @param string   $date          The day the report was about.
	 * @param string   $message       The mapped reason, already free of any secret.
	 * @param int|null $now           The current Unix time; injected by the tests.
	 * @param bool     $may_try_again Whether another attempt could still succeed.
	 * @return array{time: int, trigger: string, date: string, status: string, attempt: int, message: string}
	 */
	private static function failed( string $trigger, string $date, string $message, ?int $now = null, bool $may_try_again = true ): array {
		$attempt = self::attempt();

		RunLog::mark_failed();

		if ( self::is_automatic( $trigger ) ) {
			if ( $may_try_again && $attempt < Settings::max_attempts() ) {
				Scheduler::schedule_retry( $date, $now );
			} else {
				// The day is given up: the chat is told once, and the counter
				// starts again for the next day. The day itself stays unsent —
				// nothing here pretends it was delivered.
				$message .= ' ' . self::notify( $date );

				RunLog::reset_attempt();
			}
		}

		return RunLog::add( $trigger, $date, 'failed', $attempt, $message );
	}

	/**
	 * Tells the chat that a day could not be reported, and says how that went.
	 *
	 * Best effort by definition: the notice travels the way the report does, so
	 * whatever refused the report may refuse this too — and then the log is the
	 * only place left that can say so.
	 *
	 * @param string $date The day that could not be reported.
	 */
	private static function notify( string $date ): string {
		try {
			TelegramClient::send_message( Settings::telegram_chat_id(), MessageRenderer::render_failure( $date ) );
		} catch ( TelegramException $refused ) {
			return sprintf(
				/* translators: %s: the reason Telegram gave, already free of the bot token. */
				__( 'No attempts are left, and the chat could not be told either: %s', 'ga-telegram-bridge' ),
				$refused->getMessage()
			);
		}

		return __( 'No attempts are left, so the chat has been told that this day could not be reported.', 'ga-telegram-bridge' );
	}

	/**
	 * Whether this run belongs to the chain that looks after itself.
	 *
	 * Only the schedule retries. *Send now* is a person at the screen who is
	 * shown the reason and can press again: a retry booked behind their back
	 * would wake up an hour later for a day the manual run had bypassed the
	 * guard for, and a wrong property id tried three times by hand would tell
	 * the owner's chat that the day is missing.
	 *
	 * @param string $trigger The trigger of the run.
	 */
	private static function is_automatic( string $trigger ): bool {
		return 'cron' === $trigger || 'retry' === $trigger;
	}

	/**
	 * Returns the number this run is: the first after a success is 1.
	 */
	private static function attempt(): int {
		return RunLog::attempt() + 1;
	}

	/**
	 * Returns the day the site would call yesterday.
	 *
	 * Used only when the report could not be built at all, so no property time
	 * zone is known. The site's own is the closest thing to the truth, and an
	 * empty column in the log would be worse than a date that may be a few
	 * hours out on a property in another zone.
	 *
	 * @param int $now The current Unix time.
	 */
	private static function site_yesterday( int $now ): string {
		return ReportBuilder::report_date( (string) wp_timezone_string(), $now );
	}
}
