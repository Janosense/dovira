<?php
/**
 * The day's report, written out as the message that goes to Telegram.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Turns a Report into the message fixed by FEATURE.md → UI, and nothing else:
 * no network, no settings, no state.
 *
 * Two constraints shape what comes out. Telegram's HTML parse mode understands
 * only four named entities — &lt;, &gt;, &amp; and &quot; — plus the numeric
 * ones, so everything Google supplies is escaped into exactly those, while the
 * separators WordPress puts into localized numbers (the Ukrainian thousands
 * separator really is the string "&nbsp;") are decoded back into the character
 * they stand for. And a block is printed only when it has rows: null means the
 * administrator switched it off, an empty array means Google had nothing to
 * report, and neither is worth a heading with nothing underneath it. The blocks
 * that are printed are set apart by one blank line each.
 */
final class MessageRenderer {

	/**
	 * What is printed where a comparison has no baseline to work from.
	 */
	private const NO_CHANGE = '—';

	/**
	 * The mark of a rise.
	 */
	private const UP = '▲';

	/**
	 * The mark of a fall.
	 */
	private const DOWN = '▼';

	/**
	 * What separates the rows of a share block.
	 */
	private const SHARE_SEPARATOR = ' · ';

	/**
	 * Writes the whole message for one report.
	 *
	 * @param Report $report The day's numbers, shares and changes included.
	 */
	public static function render( Report $report ): string {
		$report = self::filtered_report( $report );

		$blocks = array(
			array( self::header( $report ) ),
			self::visitors( $report ),
			self::pages(
				'📄',
				sprintf(
					/* translators: %d: how many pages the block lists. */
					__( 'Top %d pages yesterday', 'ga-telegram-bridge' ),
					ReportBuilder::TOP_ROWS
				),
				$report->pages_yesterday
			),
			self::pages(
				'📅',
				sprintf(
					/* translators: %d: how many pages the block lists. */
					__( 'Top %d pages over 28 days', 'ga-telegram-bridge' ),
					ReportBuilder::TOP_ROWS
				),
				$report->pages_28_days
			),
			self::shares( '🧭', __( 'Sources over 28 days', 'ga-telegram-bridge' ), $report->channels, false ),
			self::shares( '📍', __( 'Cities over 28 days', 'ga-telegram-bridge' ), $report->cities, true ),
			self::shares( '📱', __( 'Devices over 28 days', 'ga-telegram-bridge' ), $report->devices, true ),
		);

		$printed = array();

		foreach ( $blocks as $lines ) {
			if ( array() !== $lines ) {
				$printed[] = implode( "\n", $lines );
			}
		}

		return self::filtered_html( implode( "\n\n", $printed ), $report );
	}

	/**
	 * Writes the one-line notice that stands in for a report that failed.
	 *
	 * Sending it is Sprint 2's retry logic; it lives here because it is the
	 * second template of the same message, in the same voice.
	 *
	 * @param string $date The day the missing report was about, Y-m-d.
	 */
	public static function render_failure( string $date ): string {
		$html = sprintf(
			/* translators: 1: the site's host name, 2: the day the report was about. */
			__( '⚠️ <b>%1$s</b> — the report for %2$s was not built. The details are in the plugin\'s run log.', 'ga-telegram-bridge' ),
			esc_html( self::host() ),
			esc_html( self::day( $date ) )
		);

		return self::filtered_html( $html, null );
	}

	/**
	 * Writes the header: which site, and which day.
	 *
	 * @param Report $report The report.
	 */
	private static function header( Report $report ): string {
		return sprintf(
			'📊 <b>%1$s — %2$s</b>',
			esc_html( self::host() ),
			esc_html( self::day( $report->date ) )
		);
	}

	/**
	 * Writes the visitors block, which is never left out.
	 *
	 * @param Report $report The report.
	 * @return list<string>
	 */
	private static function visitors( Report $report ): array {
		return array(
			self::heading( '👥', __( 'Visitors', 'ga-telegram-bridge' ) ),
			sprintf(
				/* translators: 1: how many people visited yesterday, 2: the change against the seven-day average, for example "▲ 23%". */
				__( 'Yesterday: %1$s (%2$s to the 7-day average)', 'ga-telegram-bridge' ),
				self::number( $report->visitors_yesterday ),
				self::change( $report->visitors_change_vs_average )
			),
			sprintf(
				/* translators: 1: how many people visited in the last 28 days, 2: the change against the 28 days before those. */
				__( 'Last 28 days: %1$s (%2$s to the previous 28)', 'ga-telegram-bridge' ),
				self::number( $report->visitors_28_days ),
				self::change( $report->visitors_change_28_days )
			),
		);
	}

	/**
	 * Writes one of the two page blocks, or nothing at all.
	 *
	 * @param string                                                    $icon    The emoji in front of the block's title.
	 * @param string                                                    $heading The block's title.
	 * @param list<array{title: string, path: string, views: int}>|null $pages   The pages, or null when the block is off.
	 * @return list<string>
	 */
	private static function pages( string $icon, string $heading, ?array $pages ): array {
		if ( null === $pages || array() === $pages ) {
			return array();
		}

		$lines = array( self::heading( $icon, $heading ) );
		$place = 0;

		foreach ( $pages as $page ) {
			++$place;

			$lines[] = sprintf(
				/* translators: 1: the page's place in the list, 2: the page title, 3: how many times it was viewed. */
				__( '%1$d. %2$s — %3$s', 'ga-telegram-bridge' ),
				$place,
				self::page_link( $page ),
				self::number( $page['views'] )
			);
		}

		return $lines;
	}

	/**
	 * Writes a page's title as a link to the page.
	 *
	 * A path that does not start with a slash is not an address on the site —
	 * GA reports "(not set)" for a hit that carried no page — so it is printed
	 * as it is, rather than linked to somewhere that does not exist.
	 *
	 * @param array{title: string, path: string, views: int} $page The page.
	 */
	private static function page_link( array $page ): string {
		if ( ! str_starts_with( $page['path'], '/' ) ) {
			return esc_html( $page['title'] );
		}

		return sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( ReportBuilder::page_url( $page['path'] ) ),
			esc_html( $page['title'] )
		);
	}

	/**
	 * Writes one share block, or nothing at all.
	 *
	 * @param string                                                       $icon         The emoji in front of the block's title.
	 * @param string                                                       $heading      The block's title.
	 * @param list<array{label: string, value: int, share: int|null}>|null $rows         The rows, or null when the block is off.
	 * @param bool                                                         $one_per_line Whether each row gets its own line, rather than all of them one line together.
	 * @return list<string>
	 */
	private static function shares( string $icon, string $heading, ?array $rows, bool $one_per_line ): array {
		if ( null === $rows || array() === $rows ) {
			return array();
		}

		$printed = array();

		foreach ( $rows as $row ) {
			$printed[] = sprintf(
				'%1$s %2$s',
				esc_html( $row['label'] ),
				null === $row['share'] ? self::NO_CHANGE : self::number( $row['share'] ) . '%'
			);
		}

		return array_merge(
			array( self::heading( $icon, $heading ) ),
			$one_per_line ? $printed : array( implode( self::SHARE_SEPARATOR, $printed ) )
		);
	}

	/**
	 * Writes a comparison: an arrow and a percentage, or a dash.
	 *
	 * A change of null means the baseline was zero — a site's first days — and
	 * "up from nothing" is not a percentage anybody can read.
	 *
	 * @param int|null $change The change in whole per cent.
	 */
	private static function change( ?int $change ): string {
		if ( null === $change ) {
			return self::NO_CHANGE;
		}

		return sprintf(
			'%1$s %2$s%%',
			$change < 0 ? self::DOWN : self::UP,
			self::number( abs( $change ) )
		);
	}

	/**
	 * Writes one number the way the site's language writes numbers.
	 *
	 * The entity decoding is not decoration: several locales, Ukrainian among
	 * them, use the string "&nbsp;" as their thousands separator, and Telegram
	 * would either print that literally or refuse the whole message, since the
	 * only named entities it knows are &lt;, &gt;, &amp; and &quot;.
	 *
	 * @param int|float $value The number.
	 */
	private static function number( $value ): string {
		return html_entity_decode(
			number_format_i18n( $value ),
			ENT_QUOTES | ENT_HTML5,
			'UTF-8'
		);
	}

	/**
	 * Writes a day the way the site's language writes days.
	 *
	 * The date is already the property's own calendar day, so it is held still
	 * at noon UTC: no time zone can then move the printed day, and the format
	 * stays translatable for languages that order dates differently. WordPress
	 * declines the month by itself where the language needs it.
	 *
	 * @param string $date The day, Y-m-d.
	 */
	private static function day( string $date ): string {
		$utc = new DateTimeZone( 'UTC' );

		try {
			$noon = new DateTimeImmutable( $date . ' 12:00:00', $utc );
		} catch ( Exception $unreadable_day ) {
			return $date;
		}

		return (string) wp_date(
			/* translators: The report's date, in the letters of the PHP date() function — https://www.php.net/date. */
			__( 'j F (l)', 'ga-telegram-bridge' ),
			$noon->getTimestamp(),
			$utc
		);
	}

	/**
	 * Returns the host name the report is about.
	 */
	private static function host(): string {
		return (string) wp_parse_url( home_url(), PHP_URL_HOST );
	}

	/**
	 * Wraps a block title in the only markup the message uses for it.
	 *
	 * The emoji stays out of the translated title, the way the header's does:
	 * which picture marks a block is not a question of language.
	 *
	 * @param string $icon The emoji in front of the title.
	 * @param string $text The title.
	 */
	private static function heading( string $icon, string $text ): string {
		return sprintf( '%1$s <b>%2$s</b>', $icon, esc_html( $text ) );
	}

	/**
	 * Hands the finished numbers to anyone who wants to change them.
	 *
	 * @param Report $report The report as it was built.
	 */
	private static function filtered_report( Report $report ): Report {
		/**
		 * Filters the report before it is written out.
		 *
		 * @param Report $report The day's numbers, with shares and changes already computed.
		 */
		return self::as_report( apply_filters( 'gatb_report_data', $report ), $report );
	}

	/**
	 * Returns what a filter handed back, as long as it is a report.
	 *
	 * A filter is other people's code and may return anything at all; anything
	 * that is not a report is ignored rather than obeyed, because the message
	 * still has to go out.
	 *
	 * @param mixed  $filtered What the filter returned.
	 * @param Report $report   The report as it was built.
	 */
	private static function as_report( $filtered, Report $report ): Report {
		return $filtered instanceof Report ? $filtered : $report;
	}

	/**
	 * Hands the finished message to anyone who wants to change it.
	 *
	 * @param string      $html   The message, in Telegram's HTML parse mode.
	 * @param Report|null $report The report it was made from, or null for the failure notice.
	 */
	private static function filtered_html( string $html, ?Report $report ): string {
		/**
		 * Filters the message after it is written and before it is sent.
		 *
		 * @param string      $html   The message, in Telegram's HTML parse mode.
		 * @param Report|null $report The report it was made from; null for the failure notice.
		 */
		return (string) apply_filters( 'gatb_message_html', $html, $report );
	}
}
