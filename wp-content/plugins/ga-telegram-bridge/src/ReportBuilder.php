<?php
/**
 * Composing the day's GA4 requests and reading the answers back.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Turns the enabled report blocks into GA4 requests, and the responses into a
 * Report.
 *
 * Two things here are not free choices. The Data API accepts at most five
 * requests per batchRunReports call, so the six reports of a full run cannot be
 * one call — hence FEATURE.md's "never more than 2". And the rows of the
 * visitors report come back ordered by metric value rather than by the order
 * the date ranges were asked for, so they are keyed by the dateRange dimension
 * GA adds; reading them by row index would silently swap yesterday with the
 * previous 28 days (docs/LEARNINGS.md, "Sprint 1 spike findings").
 */
final class ReportBuilder {

	/**
	 * How many report requests fit into one batchRunReports call.
	 */
	private const MAX_REQUESTS_PER_CALL = 5;

	/**
	 * How many rows the ranked blocks keep.
	 *
	 * Public because the message says "top five" with the same number that
	 * asks Google for five rows, rather than a second 5 in another file.
	 */
	public const TOP_ROWS = 5;

	/**
	 * What GA calls a dimension it has no value for.
	 */
	private const NOT_SET = '(not set)';

	/**
	 * The window the monthly figures cover, and the baseline before it.
	 */
	private const PERIOD_DAYS = 28;

	/**
	 * How many days the "usual day" is averaged over.
	 */
	private const BASELINE_DAYS = 7;

	/**
	 * Reads the configured property and returns the day's report.
	 *
	 * Failures are not caught: GaClientException and GoogleAuthException travel
	 * up to the caller, which from Step 8 is the runner that logs them.
	 *
	 * @param int|null $now The current Unix time; injected by the tests.
	 * @throws GaClientException When a report cannot be read.
	 */
	public static function build( ?int $now = null ): Report {
		$requests  = self::requests( Settings::blocks() );
		$responses = array();

		foreach ( array_chunk( $requests, self::MAX_REQUESTS_PER_CALL, true ) as $chunk ) {
			$reports = GaClient::batch_run_reports( array_values( $chunk ) );

			if ( count( $reports ) !== count( $chunk ) ) {
				throw new GaClientException(
					esc_html__( 'Google Analytics answered with fewer reports than were asked for.', 'ga-telegram-bridge' )
				);
			}

			$responses += array_combine( array_keys( $chunk ), $reports );
		}

		return self::to_report( $responses, $now ?? time() );
	}

	/**
	 * Builds one request per enabled block, in a fixed order.
	 *
	 * A disabled block contributes nothing at all — not an empty request, not a
	 * placeholder — which is what "a disabled block issues no request" means.
	 *
	 * @param array<string, bool> $blocks The enabled report blocks.
	 * @return array<string, array<string, mixed>>
	 */
	public static function requests( array $blocks ): array {
		$month                = self::range( self::PERIOD_DAYS . 'daysAgo', 'yesterday' );
		$requests             = array();
		$requests['visitors'] = array(
			'metrics'    => array( array( 'name' => 'activeUsers' ) ),
			'dateRanges' => array(
				self::range( 'yesterday', 'yesterday' ),
				self::range( ( self::BASELINE_DAYS + 1 ) . 'daysAgo', '2daysAgo' ),
				$month,
				self::range( ( self::PERIOD_DAYS * 2 ) . 'daysAgo', ( self::PERIOD_DAYS + 1 ) . 'daysAgo' ),
			),
		);

		if ( ! empty( $blocks['pages'] ) ) {
			$requests['pages_yesterday'] = self::ranked(
				array( 'pagePath', 'pageTitle' ),
				'screenPageViews',
				self::range( 'yesterday', 'yesterday' ),
				self::TOP_ROWS
			);

			$requests['pages_28_days'] = self::ranked(
				array( 'pagePath', 'pageTitle' ),
				'screenPageViews',
				$month,
				self::TOP_ROWS
			);
		}

		if ( ! empty( $blocks['channels'] ) ) {
			$requests['channels'] = self::ranked( array( 'sessionDefaultChannelGroup' ), 'sessions', $month, null );
		}

		if ( ! empty( $blocks['cities'] ) ) {
			$requests['cities'] = self::ranked( array( 'city' ), 'activeUsers', $month, self::TOP_ROWS );
		}

		if ( ! empty( $blocks['devices'] ) ) {
			$requests['devices'] = self::ranked( array( 'deviceCategory' ), 'activeUsers', $month, null );
		}

		return $requests;
	}

	/**
	 * Returns the day a report covers: yesterday in the property's own zone.
	 *
	 * Which calendar day "yesterday" means is decided by the property, not by
	 * this server — that is the invariant in FEATURE.md, and the reason the
	 * reporting time zone is read from the response.
	 *
	 * @param string $time_zone The property's reporting time zone.
	 * @param int    $now       The current Unix time.
	 */
	public static function report_date( string $time_zone, int $now ): string {
		return ( new DateTimeImmutable( '@' . $now ) )
			->setTimezone( self::zone( $time_zone ) )
			->modify( '-1 day' )
			->format( 'Y-m-d' );
	}

	/**
	 * Turns the keyed responses into the finished report.
	 *
	 * @param array<string, array<string, mixed>> $responses The reports, keyed as requests() keyed them.
	 * @param int                                 $now       The current Unix time.
	 */
	private static function to_report( array $responses, int $now ): Report {
		$visitors = isset( $responses['visitors'] ) ? $responses['visitors'] : array();
		$ranges   = self::by_date_range( $visitors );

		$yesterday     = isset( $ranges[0] ) ? $ranges[0] : 0;
		$previous_week = isset( $ranges[1] ) ? $ranges[1] : 0;
		$month         = isset( $ranges[2] ) ? $ranges[2] : 0;
		$previous      = isset( $ranges[3] ) ? $ranges[3] : 0;
		$average       = Dynamics::average( $previous_week, self::BASELINE_DAYS );
		$time_zone     = self::time_zone( $visitors );

		return new Report(
			self::report_date( $time_zone, $now ),
			$time_zone,
			$yesterday,
			$average,
			$month,
			$previous,
			Dynamics::change( $yesterday, $average ),
			Dynamics::change( $month, $previous ),
			isset( $responses['pages_yesterday'] ) ? self::pages( $responses['pages_yesterday'] ) : null,
			isset( $responses['pages_28_days'] ) ? self::pages( $responses['pages_28_days'] ) : null,
			isset( $responses['channels'] ) ? self::shares( $responses['channels'], false ) : null,
			isset( $responses['cities'] ) ? self::shares( $responses['cities'], true ) : null,
			isset( $responses['devices'] ) ? self::shares( $responses['devices'], false ) : null
		);
	}

	/**
	 * Reads the visitors report into the four date ranges it was asked for.
	 *
	 * Keyed by the dateRange dimension value (`date_range_0` … `date_range_3`),
	 * which is numbered in request order — never by the row's position, which
	 * follows the metric.
	 *
	 * @param array<string, mixed> $report The visitors report.
	 * @return array<int, int>
	 */
	private static function by_date_range( array $report ): array {
		$totals = array();

		foreach ( self::rows( $report ) as $row ) {
			$range = self::dimension( $row, 0 );

			if ( 1 !== preg_match( '/^date_range_(\d+)$/', $range, $matches ) ) {
				continue;
			}

			$totals[ (int) $matches[1] ] = self::metric( $row, 0 );
		}

		return $totals;
	}

	/**
	 * Reads a pages report, labelling each row the way the message shows it.
	 *
	 * @param array<string, mixed> $report The pages report.
	 * @return list<array{title: string, path: string, views: int}>
	 */
	private static function pages( array $report ): array {
		$pages = array();

		foreach ( self::rows( $report ) as $row ) {
			$path  = self::dimension( $row, 0 );
			$title = self::dimension( $row, 1 );

			$pages[] = array(
				'title' => ( '' === $title || self::NOT_SET === $title ) ? $path : $title,
				'path'  => $path,
				'views' => self::metric( $row, 0 ),
			);
		}

		return $pages;
	}

	/**
	 * Reads a ranked report and works out each row's share of the block.
	 *
	 * @param array<string, mixed> $report      The report.
	 * @param bool                 $drop_not_set Whether a "(not set)" row is dropped first.
	 * @return list<array{label: string, value: int, share: int|null}>
	 */
	private static function shares( array $report, bool $drop_not_set ): array {
		$rows  = array();
		$total = 0;

		foreach ( self::rows( $report ) as $row ) {
			$label = self::dimension( $row, 0 );

			if ( $drop_not_set && ( '' === $label || self::NOT_SET === $label ) ) {
				continue;
			}

			$value  = self::metric( $row, 0 );
			$total += $value;
			$rows[] = array(
				'label' => '' === $label ? self::NOT_SET : $label,
				'value' => $value,
			);
		}

		$shares = array();

		foreach ( $rows as $row ) {
			$shares[] = array(
				'label' => $row['label'],
				'value' => $row['value'],
				'share' => Dynamics::share( $row['value'], $total ),
			);
		}

		return $shares;
	}

	/**
	 * Returns the rows of a report, or none when GA sent none.
	 *
	 * A property with no traffic answers without a `rows` key at all, which is
	 * an empty block and not a failure.
	 *
	 * @param array<string, mixed> $report The report.
	 * @return list<array<string, mixed>>
	 */
	private static function rows( array $report ): array {
		if ( ! isset( $report['rows'] ) || ! is_array( $report['rows'] ) ) {
			return array();
		}

		$rows = array();

		foreach ( $report['rows'] as $row ) {
			if ( is_array( $row ) ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * Returns one dimension value of a row.
	 *
	 * @param array<string, mixed> $row      The row.
	 * @param int                  $position Which dimension, in request order.
	 */
	private static function dimension( array $row, int $position ): string {
		$values = isset( $row['dimensionValues'] ) && is_array( $row['dimensionValues'] ) ? $row['dimensionValues'] : array();
		$value  = isset( $values[ $position ]['value'] ) ? $values[ $position ]['value'] : '';

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Returns one metric value of a row.
	 *
	 * @param array<string, mixed> $row      The row.
	 * @param int                  $position Which metric, in request order.
	 */
	private static function metric( array $row, int $position ): int {
		$values = isset( $row['metricValues'] ) && is_array( $row['metricValues'] ) ? $row['metricValues'] : array();
		$value  = isset( $values[ $position ]['value'] ) ? $values[ $position ]['value'] : 0;

		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * Returns the reporting time zone GA sent with a report.
	 *
	 * @param array<string, mixed> $report The report.
	 */
	private static function time_zone( array $report ): string {
		$metadata = isset( $report['metadata'] ) && is_array( $report['metadata'] ) ? $report['metadata'] : array();
		$zone     = isset( $metadata['timeZone'] ) && is_string( $metadata['timeZone'] ) ? $metadata['timeZone'] : '';

		return '' !== $zone ? $zone : (string) wp_timezone_string();
	}

	/**
	 * Turns a time-zone name into a zone, falling back to UTC.
	 *
	 * @param string $time_zone The zone name.
	 */
	private static function zone( string $time_zone ): DateTimeZone {
		try {
			return new DateTimeZone( '' !== $time_zone ? $time_zone : 'UTC' );
		} catch ( Exception $unknown_zone ) {
			return new DateTimeZone( 'UTC' );
		}
	}

	/**
	 * Builds one date range.
	 *
	 * @param string $start The first day, relative.
	 * @param string $end   The last day, relative.
	 * @return array{startDate: string, endDate: string}
	 */
	private static function range( string $start, string $end ): array {
		return array(
			'startDate' => $start,
			'endDate'   => $end,
		);
	}

	/**
	 * Builds one request ordered by its metric, largest first.
	 *
	 * Ordering is what makes a limit mean "the top five" rather than "any
	 * five"; the blocks without a limit are ranked lists in the message, so
	 * they are ordered the same way.
	 *
	 * @param array<string>                             $dimensions The dimensions to group by.
	 * @param string                                    $metric     The metric to measure and order by.
	 * @param array{startDate: string, endDate: string} $range The period.
	 * @param int|null                                  $limit      How many rows to keep, or null for all.
	 * @return array<string, mixed>
	 */
	private static function ranked( array $dimensions, string $metric, array $range, ?int $limit ): array {
		$request = array(
			'dimensions' => array_map(
				static fn( string $dimension ): array => array( 'name' => $dimension ),
				$dimensions
			),
			'metrics'    => array( array( 'name' => $metric ) ),
			'dateRanges' => array( $range ),
			'orderBys'   => array(
				array(
					'metric' => array( 'metricName' => $metric ),
					'desc'   => true,
				),
			),
		);

		if ( null !== $limit ) {
			$request['limit'] = $limit;
		}

		return $request;
	}
}
