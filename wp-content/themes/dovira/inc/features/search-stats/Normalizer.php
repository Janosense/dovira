<?php

namespace dovira\SearchStats;

/**
 * The one place a search text is normalized; only its result is stored and
 * printed (docs/features/search-stats/FEATURE.md → Invariants).
 */
final class Normalizer {

	/**
	 * Collapses every run of whitespace to one space, trims the ends and
	 * lowercases. With /u, \s also matches Unicode whitespace such as the
	 * non-breaking space.
	 *
	 * @return string The normalized text; '' when the text is not valid UTF-8.
	 */
	public static function normalize( string $text ): string {
		$collapsed = preg_replace( '/\s+/u', ' ', $text );

		if ( null === $collapsed ) {
			return '';
		}

		return mb_strtolower( trim( $collapsed ) );
	}
}
