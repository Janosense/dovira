<?php
/**
 * What the plugin has sent, and what happened when it tried.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge;

/**
 * The two rows that remember runs: the log the administrator reads and the
 * state the date guard compares against.
 *
 * They are kept behind one class so that nothing else in the plugin knows their
 * option names, and neither is autoloaded: a site loads them when the settings
 * screen or a run asks for them, not on every request.
 *
 * Nothing secret is ever written here. The only text stored is the message of a
 * mapped exception, and those are scrubbed of the bot token before they are
 * thrown (`TelegramClient`) and never carry the service-account key
 * (`GoogleAuth`).
 */
final class RunLog {

	/**
	 * The option holding the last runs.
	 */
	public const LOG_OPTION = 'gatb_log';

	/**
	 * The option holding what has been sent and how often it has been tried.
	 */
	public const STATE_OPTION = 'gatb_state';

	/**
	 * How many runs are kept. The oldest falls off the end.
	 */
	public const KEEP = 30;

	/**
	 * What a run can have been started by.
	 */
	public const TRIGGERS = array( 'cron', 'retry', 'manual' );

	/**
	 * How a run can have ended.
	 */
	public const STATUSES = array( 'sent', 'failed' );

	/**
	 * Records one finished run and returns the entry it stored.
	 *
	 * @param string $trigger What started the run: cron, retry or manual.
	 * @param string $date    The day the report was about, Y-m-d.
	 * @param string $status  How it ended: sent or failed.
	 * @param int    $attempt Which attempt this was for that day.
	 * @param string $message What to tell the administrator; never a secret.
	 * @return array{time: int, trigger: string, date: string, status: string, attempt: int, message: string}
	 */
	public static function add( string $trigger, string $date, string $status, int $attempt, string $message ): array {
		$entry = array(
			'time'    => time(),
			'trigger' => in_array( $trigger, self::TRIGGERS, true ) ? $trigger : 'manual',
			'date'    => $date,
			'status'  => in_array( $status, self::STATUSES, true ) ? $status : 'failed',
			'attempt' => $attempt,
			'message' => $message,
		);

		$entries = self::entries();
		array_unshift( $entries, $entry );

		update_option( self::LOG_OPTION, array_slice( $entries, 0, self::KEEP ), false );

		return $entry;
	}

	/**
	 * Returns the runs, newest first.
	 *
	 * Every entry is completed and typed on the way out, so a row edited by
	 * hand cannot break the table that prints it.
	 *
	 * @return list<array{time: int, trigger: string, date: string, status: string, attempt: int, message: string}>
	 */
	public static function entries(): array {
		$stored  = get_option( self::LOG_OPTION, array() );
		$entries = array();

		foreach ( is_array( $stored ) ? $stored : array() as $entry ) {
			if ( is_array( $entry ) ) {
				$entries[] = self::complete( $entry );
			}
		}

		return $entries;
	}

	/**
	 * Forgets every run. Used by the tests and by Sprint 2's uninstall.
	 */
	public static function clear(): void {
		delete_option( self::LOG_OPTION );
	}

	/**
	 * Returns the day of the last report that really went out.
	 *
	 * This is what the date guard compares the day in hand with: a report for a
	 * date is sent at most once, whatever WP-Cron does (FEATURE.md invariants).
	 */
	public static function last_report_date(): string {
		$state = self::state();

		return (string) $state['last_report_date'];
	}

	/**
	 * Returns how many attempts the pending day has already cost.
	 */
	public static function attempt(): int {
		$state = self::state();

		return (int) $state['attempt'];
	}

	/**
	 * Records that the report for a day went out, and clears the counter.
	 *
	 * @param string $date The day that was sent, Y-m-d.
	 */
	public static function mark_sent( string $date ): void {
		self::save_state(
			array(
				'last_report_date' => $date,
				'attempt'          => 0,
			)
		);
	}

	/**
	 * Records one more failed attempt, leaving the last sent day untouched.
	 *
	 * The day stays unsent on purpose: Sprint 2's retry has to find it.
	 */
	public static function mark_failed(): void {
		self::save_state(
			array(
				'last_report_date' => self::last_report_date(),
				'attempt'          => self::attempt() + 1,
			)
		);
	}

	/**
	 * Returns the state, completed with its defaults.
	 *
	 * @return array{last_report_date: string, attempt: int}
	 */
	public static function state(): array {
		$stored = get_option( self::STATE_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'last_report_date' => isset( $stored['last_report_date'] ) && is_string( $stored['last_report_date'] ) ? $stored['last_report_date'] : '',
			'attempt'          => isset( $stored['attempt'] ) && is_numeric( $stored['attempt'] ) ? (int) $stored['attempt'] : 0,
		);
	}

	/**
	 * Writes the state, never autoloaded.
	 *
	 * @param array{last_report_date: string, attempt: int} $state What to remember.
	 */
	private static function save_state( array $state ): void {
		update_option( self::STATE_OPTION, $state, false );
	}

	/**
	 * Completes and types one stored entry.
	 *
	 * @param array<string, mixed> $entry The stored entry.
	 * @return array{time: int, trigger: string, date: string, status: string, attempt: int, message: string}
	 */
	private static function complete( array $entry ): array {
		return array(
			'time'    => isset( $entry['time'] ) && is_numeric( $entry['time'] ) ? (int) $entry['time'] : 0,
			'trigger' => isset( $entry['trigger'] ) && is_string( $entry['trigger'] ) ? $entry['trigger'] : '',
			'date'    => isset( $entry['date'] ) && is_string( $entry['date'] ) ? $entry['date'] : '',
			'status'  => isset( $entry['status'] ) && is_string( $entry['status'] ) ? $entry['status'] : '',
			'attempt' => isset( $entry['attempt'] ) && is_numeric( $entry['attempt'] ) ? (int) $entry['attempt'] : 0,
			'message' => isset( $entry['message'] ) && is_string( $entry['message'] ) ? $entry['message'] : '',
		);
	}
}
