<?php
/**
 * Tests for the run log and the state the date guard reads.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge\Tests\Unit;

use Brain\Monkey\Functions;
use GaTelegramBridge\RunLog;
use GaTelegramBridge\Tests\TestCase;

/**
 * Both options are held in one place, so both are asserted in one place: what
 * an entry looks like, how many are kept, and what a run does to the state.
 */
final class RunLogTest extends TestCase {

	/**
	 * What the stubbed options hold during one test.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = array();

	/**
	 * Every write the test saw, as option name and autoload flag.
	 *
	 * @var list<array{string, mixed}>
	 */
	private array $writes = array();

	/**
	 * Stubs the option functions with a store that behaves like the database.
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();

		$this->options = array();
		$this->writes  = array();

		Functions\when( 'get_option' )->alias(
			fn( string $option, $default_value = false ) => $this->option( $option, $default_value )
		);
		Functions\when( 'update_option' )->alias(
			function ( string $option, $value, $autoload = null ): bool {
				$this->options[ $option ] = $value;
				$this->writes[]           = array( $option, $autoload );

				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( string $option ): bool {
				unset( $this->options[ $option ] );

				return true;
			}
		);
	}

	/**
	 * Returns what the stubbed database holds for an option.
	 *
	 * @param string $option        The option name.
	 * @param mixed  $default_value What get_option() was asked to fall back to.
	 * @return mixed
	 */
	private function option( string $option, $default_value ) {
		return array_key_exists( $option, $this->options ) ? $this->options[ $option ] : $default_value;
	}

	/**
	 * A run is stored with everything the table shows and nothing else.
	 */
	public function test_an_entry_holds_what_the_table_shows(): void {
		$entry = RunLog::add( 'manual', '2026-09-08', 'sent', 1, 'Sent to chat -1001234567890.' );

		$this->assertSame(
			array( 'time', 'trigger', 'date', 'status', 'attempt', 'message' ),
			array_keys( $entry )
		);
		$this->assertSame( 'manual', $entry['trigger'] );
		$this->assertSame( '2026-09-08', $entry['date'] );
		$this->assertSame( 'sent', $entry['status'] );
		$this->assertSame( 1, $entry['attempt'] );
		$this->assertGreaterThan( 0, $entry['time'] );
		$this->assertSame( array( $entry ), RunLog::entries() );
	}

	/**
	 * The newest run is the first one read back.
	 */
	public function test_the_newest_run_comes_first(): void {
		RunLog::add( 'manual', '2026-09-07', 'sent', 1, 'first' );
		RunLog::add( 'cron', '2026-09-08', 'failed', 2, 'second' );

		$this->assertSame( array( 'second', 'first' ), array_column( RunLog::entries(), 'message' ) );
	}

	/**
	 * The log stops at thirty runs, and it is the oldest that goes.
	 */
	public function test_the_log_keeps_thirty_runs_and_drops_the_oldest(): void {
		for ( $run = 1; $run <= 31; $run++ ) {
			RunLog::add( 'cron', '2026-09-08', 'sent', 1, 'run ' . $run );
		}

		$entries = RunLog::entries();

		$this->assertCount( 30, $entries );
		$this->assertSame( 'run 31', $entries[0]['message'] );
		$this->assertSame( 'run 2', $entries[29]['message'], 'the first run has fallen off the end' );
	}

	/**
	 * Neither option is autoloaded: they are read on demand, not on every page.
	 */
	public function test_neither_option_is_autoloaded(): void {
		RunLog::add( 'manual', '2026-09-08', 'sent', 1, 'a run' );
		RunLog::mark_sent( '2026-09-08' );

		$this->assertSame(
			array(
				array( 'gatb_log', false ),
				array( 'gatb_state', false ),
			),
			$this->writes
		);
	}

	/**
	 * A site that has never run has nothing to say, and says so without failing.
	 */
	public function test_an_empty_log_and_an_empty_state_are_not_a_failure(): void {
		$this->assertSame( array(), RunLog::entries() );
		$this->assertSame( '', RunLog::last_report_date() );
		$this->assertSame( 0, RunLog::attempt() );
	}

	/**
	 * A sent report is remembered by its day, and the counter starts again.
	 */
	public function test_a_sent_report_records_the_day_and_clears_the_counter(): void {
		RunLog::mark_failed();
		RunLog::mark_failed();
		$this->assertSame( 2, RunLog::attempt() );

		RunLog::mark_sent( '2026-09-08' );

		$this->assertSame( '2026-09-08', RunLog::last_report_date() );
		$this->assertSame( 0, RunLog::attempt() );
	}

	/**
	 * A failed attempt counts, and leaves the day unsent.
	 *
	 * The day has to stay unsent: it is what Sprint 2's retry looks for, and
	 * what stops a failure from being remembered as a delivery.
	 */
	public function test_a_failed_attempt_counts_and_leaves_the_day_unsent(): void {
		RunLog::mark_sent( '2026-09-07' );

		RunLog::mark_failed();

		$this->assertSame( '2026-09-07', RunLog::last_report_date() );
		$this->assertSame( 1, RunLog::attempt() );
	}

	/**
	 * The cron hit is remembered next to the day, and neither write erases the
	 * other: the two keys are written by different requests.
	 */
	public function test_the_cron_hit_survives_a_run_and_a_failure(): void {
		RunLog::mark_cron_hit( 1757505600 );

		RunLog::mark_sent( '2026-09-08' );
		$this->assertSame( 1757505600, RunLog::last_cron_hit(), 'a delivery did not erase it' );

		RunLog::mark_failed();
		$this->assertSame( 1757505600, RunLog::last_cron_hit(), 'a failure did not erase it' );
		$this->assertSame( '2026-09-08', RunLog::last_report_date(), 'and the day is still there' );

		RunLog::mark_cron_hit( 1757592000 );
		$this->assertSame( 1757592000, RunLog::last_cron_hit() );
		$this->assertSame( 1, RunLog::attempt(), 'a cron hit is not a run' );
	}

	/**
	 * A state row written before the key existed reads as "never", not as a
	 * broken row: there is no migration.
	 */
	public function test_a_state_from_before_the_cron_hit_reads_as_never(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'last_report_date' => '2026-09-08',
				'attempt'          => 2,
			)
		);

		$this->assertSame( 0, RunLog::last_cron_hit() );
		$this->assertSame( '2026-09-08', RunLog::last_report_date() );
		$this->assertSame( 2, RunLog::attempt() );
	}

	/**
	 * A row edited by hand is completed rather than trusted.
	 */
	public function test_a_damaged_row_is_completed_instead_of_breaking_the_table(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				array( 'message' => 'only a message' ),
				'not an entry at all',
			)
		);

		$entries = RunLog::entries();

		$this->assertCount( 1, $entries, 'what is not an entry is dropped' );
		$this->assertSame( 0, $entries[0]['time'] );
		$this->assertSame( '', $entries[0]['status'] );
		$this->assertSame( 'only a message', $entries[0]['message'] );
	}

	/**
	 * An unknown trigger or status is not written into the log.
	 */
	public function test_an_unknown_trigger_or_status_is_refused(): void {
		$entry = RunLog::add( 'from nowhere', '2026-09-08', 'exploded', 1, 'a run' );

		$this->assertSame( 'manual', $entry['trigger'] );
		$this->assertSame( 'failed', $entry['status'] );
	}

	/**
	 * Clearing the log leaves nothing behind.
	 */
	public function test_clearing_the_log_leaves_nothing(): void {
		RunLog::add( 'manual', '2026-09-08', 'sent', 1, 'a run' );

		RunLog::clear();

		$this->assertSame( array(), RunLog::entries() );
	}
}
