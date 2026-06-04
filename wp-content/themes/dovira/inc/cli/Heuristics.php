<?php

namespace dovira\CLI;

/**
 * Shared heuristics deciding whether a key/value pair holds human-readable
 * content. Used for ACF block data, flattened post meta, and flattened
 * option rows (which all share the same key/value shape).
 */
class Heuristics {

	/**
	 * Keys (exact, or as a "_{key}" suffix of a flattened key) that never
	 * hold human-readable content.
	 */
	private const SKIP_KEYS = [
		'url',
		'link',
		'target',
		'rel',
		'class',
		'classname',
		'id',
		'anchor',
		'mode',
		'align',
		'align_text',
		'align_content',
		'phone',
		'email',
		'map_iframe',
	];

	/**
	 * @param string $key   Field name, flattened meta key, or option key suffix.
	 * @param string $value
	 *
	 * @return bool
	 */
	public static function is_translatable( string $key, string $value ): bool {
		$key = strtolower( $key );

		// Keys prefixed with "_" hold ACF field keys, never content.
		if ( str_starts_with( $key, '_' ) ) {
			return false;
		}

		foreach ( self::SKIP_KEYS as $skip ) {
			if ( $key === $skip || str_ends_with( $key, "_$skip" ) ) {
				return false;
			}
		}

		$value = trim( $value );

		if ( $value === '' || is_numeric( $value ) ) {
			return false;
		}

		// Serialized arrays (repeater counts, checkbox values).
		if ( is_serialized( $value ) ) {
			return false;
		}

		// ACF field key references.
		if ( preg_match( '/^field_[a-zA-Z0-9]+$/', $value ) ) {
			return false;
		}

		// Boolean-ish select/radio values.
		if ( in_array( strtolower( $value ), [ 'true', 'false', 'yes', 'no', 'on', 'off' ], true ) ) {
			return false;
		}

		// Hex colors.
		if ( preg_match( '/^#[0-9a-f]{3,8}$/i', $value ) ) {
			return false;
		}

		// URLs, emails, anchors, relative paths, protocol links, @handles.
		if (
			filter_var( $value, FILTER_VALIDATE_URL )
			|| filter_var( $value, FILTER_VALIDATE_EMAIL )
			|| preg_match( '/^(mailto:|tel:|#|\/|@[\w.]+$)/', $value )
		) {
			return false;
		}

		// Embeds and inline graphics carry no translatable prose.
		if ( preg_match( '/^<(iframe|svg|script)\b/i', $value ) ) {
			return false;
		}

		// No letters at all (phone numbers, dates, dimensions).
		if ( ! preg_match( '/\p{L}/u', $value ) ) {
			return false;
		}

		// Single lowercase-latin tokens are setting values (e.g. "primary",
		// "wide"), not content — the site content is Cyrillic.
		if ( preg_match( '/^[a-z0-9_\-]+$/', $value ) ) {
			return false;
		}

		return true;
	}
}
