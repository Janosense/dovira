<?php

namespace dovira\SearchStats;

/**
 * Writes the search lists as the blocks of the daily Telegram report, exactly
 * as docs/features/search-stats/FEATURE.md → UI shows them (DECISIONS "Three
 * blocks, each with a yesterday list and a 28-day list").
 *
 * The blocks are HTML in Telegram's parse mode, and Telegram knows only four
 * named entities — &lt;, &gt;, &amp;, &quot; — plus numeric ones. Every value
 * is therefore escaped with double encoding: WordPress's esc_html() keeps an
 * entity it recognises, so a visitor who searched for "&nbsp;" would put a
 * named entity into the message and Telegram would refuse all of it.
 */
final class Renderer {

	private const HEADING_SITE = '🔎 <b>Пошук по сайту</b>';
	private const HEADING_SERVICES = '🗂 <b>Пошук у переліку послуг</b>';
	private const HEADING_SERVICE = '💊 <b>Пошук у послугах</b>';
	private const LABEL_YESTERDAY = 'Вчора:';
	private const LABEL_28_DAYS = 'За 28 днів:';
	private const EMPTY_LIST = '—';
	private const NOTHING_FOUND = ' · нічого не знайдено';
	private const DELETED = '(видалено)';

	/** A longer query is cut to this many characters and followed by an ellipsis. */
	public const MAX_QUERY_LENGTH = 40;

	/**
	 * The blocks that have something in them, in the order site, services
	 * list, service; a level whose two lists are both empty contributes none.
	 *
	 * @return list<string>
	 */
	public static function blocks( SearchStats $stats ): array {
		$levels = [
			[ self::HEADING_SITE, $stats->site_yesterday, $stats->site_28_days, false ],
			[ self::HEADING_SERVICES, $stats->services_yesterday, $stats->services_28_days, true ],
			[ self::HEADING_SERVICE, $stats->service_yesterday, $stats->service_28_days, true ],
		];

		$blocks = [];

		foreach ( $levels as [ $heading, $yesterday, $last_28_days, $with_context ] ) {
			if ( [] === $yesterday && [] === $last_28_days ) {
				continue;
			}

			$blocks[] = implode(
				"\n",
				array_merge(
					[ $heading, self::LABEL_YESTERDAY ],
					self::rows( $yesterday, $with_context ),
					[ self::LABEL_28_DAYS ],
					self::rows( $last_28_days, $with_context )
				)
			);
		}

		return $blocks;
	}

	/**
	 * The lines of one list, numbered in the order given — the lists come
	 * sorted from the database and are never sorted here — or a dash.
	 *
	 * @param list<TopQuery> $list
	 * @return list<string>
	 */
	private static function rows( array $list, bool $with_context ): array {
		if ( [] === $list ) {
			return [ self::EMPTY_LIST ];
		}

		$rows  = [];
		$place = 0;

		foreach ( $list as $top ) {
			++$place;

			$row = $place . '. ' . self::escape( self::cut( $top->query ) );

			if ( $with_context ) {
				$row .= ' — ' . self::escape( self::title( $top->context_id ) );
			}

			// Plain digits: number_format_i18n() writes the Ukrainian thousands
			// separator as the named entity &nbsp;, which Telegram refuses.
			$row .= ' — ' . $top->count;

			if ( $top->nothing_found ) {
				$row .= self::NOTHING_FOUND;
			}

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * The first MAX_QUERY_LENGTH characters and an ellipsis, for a longer
	 * query. Cut before escaping, so no entity is ever cut in half, and without
	 * a space left in front of the ellipsis.
	 */
	private static function cut( string $query ): string {
		if ( mb_strlen( $query ) <= self::MAX_QUERY_LENGTH ) {
			return $query;
		}

		return rtrim( mb_substr( $query, 0, self::MAX_QUERY_LENGTH ) ) . '…';
	}

	/**
	 * The title of the Ukrainian source post of a context, as its editors
	 * typed it. A post with no Ukrainian translation is named by itself, and
	 * a post that no longer exists as deleted; a trashed one still has a title.
	 */
	private static function title( int $context_id ): string {
		$post_id = $context_id;

		if ( function_exists( 'pll_get_post' ) && function_exists( 'pll_default_language' ) ) {
			$source = pll_get_post( $context_id, pll_default_language() );

			if ( $source ) {
				$post_id = (int) $source;
			}
		}

		if ( null === get_post( $post_id ) ) {
			return self::DELETED;
		}

		// WordPress may store "&" as "&amp;" in a title; decoded here, escaped once on output.
		return html_entity_decode( (string) get_post_field( 'post_title', $post_id, 'raw' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Escapes one value into what Telegram's HTML accepts: &lt; &gt; &amp;
	 * &quot; and &#039; for the apostrophe (ENT_HTML5's &apos; it would
	 * refuse). Double encoding, so an entity a visitor typed is shown, never
	 * interpreted.
	 */
	private static function escape( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8', true );
	}
}
