<?php

namespace dovira\CLI;

/**
 * Extracts translatable strings from post meta. ACF stores repeater/group
 * values flattened ("prices_0_title"), so the shared key/value heuristics
 * apply to meta keys directly.
 */
class MetaExtractor {

	/**
	 * Path prefix distinguishing post meta entries in the translation map.
	 */
	public const PREFIX = 'pm:';

	/**
	 * Collect translatable strings from a post's meta.
	 *
	 * @param int $post_id
	 *
	 * @return array<string, string> Map of "pm:{meta_key}" => source string.
	 */
	public function extract( int $post_id ): array {
		$map = [];

		foreach ( get_post_meta( $post_id ) as $key => $values ) {
			$value = $values[0] ?? null;

			if ( is_string( $value ) && Heuristics::is_translatable( $key, $value ) ) {
				$map[ self::PREFIX . $key ] = $value;
			}
		}

		return $map;
	}
}
