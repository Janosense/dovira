<?php

namespace dovira\CLI;

/**
 * Extracts translatable strings from Gutenberg block trees (including ACF block
 * data stored in block attributes) and injects translations back by path.
 */
class ContentExtractor {

	/**
	 * Attribute/data keys that never hold human-readable content.
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
	];

	/**
	 * Collect translatable strings from a parsed block tree.
	 *
	 * @param array $blocks Result of parse_blocks().
	 *
	 * @return array<string, string> Map of path => source string.
	 */
	public function extract( array $blocks ): array {
		$map = [];

		$this->walk_blocks( $blocks, '', function ( string $path, string $value ) use ( &$map ) {
			$map[ $path ] = $value;

			return null;
		} );

		return $map;
	}

	/**
	 * Write translated strings back into the block tree at their original paths.
	 *
	 * @param array                 $blocks       Result of parse_blocks().
	 * @param array<string, string> $translations Map of path => translated string.
	 *
	 * @return array Updated block tree, ready for serialize_blocks().
	 */
	public function inject( array $blocks, array $translations ): array {
		return $this->walk_blocks( $blocks, '', function ( string $path, string $value ) use ( $translations ) {
			return $translations[ $path ] ?? null;
		} );
	}

	/**
	 * Recursively walk blocks (including innerBlocks), visiting every
	 * translatable string. The visitor may return a replacement string or null.
	 *
	 * @param array    $blocks
	 * @param string   $prefix
	 * @param callable $visitor fn( string $path, string $value ): ?string
	 *
	 * @return array
	 */
	private function walk_blocks( array $blocks, string $prefix, callable $visitor ): array {
		foreach ( $blocks as $i => $block ) {
			$path = $prefix === '' ? "b$i" : "$prefix.b$i";

			// ACF blocks keep their field values in attrs.data.
			if ( ! empty( $block['attrs']['data'] ) && is_array( $block['attrs']['data'] ) ) {
				$blocks[ $i ]['attrs']['data'] = $this->walk_data( $block['attrs']['data'], "$path.data", $visitor );
			}

			// Core blocks keep HTML chunks in innerContent (null entries are innerBlocks placeholders).
			if ( ! empty( $block['innerContent'] ) ) {
				foreach ( $block['innerContent'] as $j => $chunk ) {
					if ( is_string( $chunk ) && trim( wp_strip_all_tags( $chunk ) ) !== '' ) {
						$new = $visitor( "$path.ic$j", $chunk );

						if ( $new !== null && $new !== $chunk ) {
							$blocks[ $i ]['innerContent'][ $j ] = $new;
						}
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$blocks[ $i ]['innerBlocks'] = $this->walk_blocks( $block['innerBlocks'], $path, $visitor );
			}
		}

		return $blocks;
	}

	/**
	 * Recursively walk an ACF data array (handles link/group/repeater sub-arrays).
	 *
	 * @param array    $data
	 * @param string   $prefix
	 * @param callable $visitor
	 *
	 * @return array
	 */
	private function walk_data( array $data, string $prefix, callable $visitor ): array {
		foreach ( $data as $key => $value ) {
			// Keys prefixed with "_" hold ACF field keys, never content.
			if ( is_string( $key ) && str_starts_with( $key, '_' ) ) {
				continue;
			}

			$path = "$prefix.$key";

			if ( is_array( $value ) ) {
				$data[ $key ] = $this->walk_data( $value, $path, $visitor );
			} elseif ( is_string( $value ) && $this->is_translatable( (string) $key, $value ) ) {
				$new = $visitor( $path, $value );

				if ( $new !== null ) {
					$data[ $key ] = $new;
				}
			}
		}

		return $data;
	}

	/**
	 * Heuristics deciding whether an ACF data value is human-readable content.
	 *
	 * @param string $key
	 * @param string $value
	 *
	 * @return bool
	 */
	private function is_translatable( string $key, string $value ): bool {
		if ( in_array( strtolower( $key ), self::SKIP_KEYS, true ) ) {
			return false;
		}

		$value = trim( $value );

		if ( $value === '' || is_numeric( $value ) ) {
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

		// URLs, emails, anchors, relative paths, protocol links.
		if (
			filter_var( $value, FILTER_VALIDATE_URL )
			|| filter_var( $value, FILTER_VALIDATE_EMAIL )
			|| preg_match( '/^(mailto:|tel:|#|\/)/', $value )
		) {
			return false;
		}

		// No letters at all (phone numbers, dates, dimensions).
		if ( ! preg_match( '/\p{L}/u', $value ) ) {
			return false;
		}

		// Single lowercase-latin tokens are setting values (e.g. "primary", "wide"),
		// not content — the site content is Cyrillic.
		if ( preg_match( '/^[a-z0-9_\-]+$/', $value ) ) {
			return false;
		}

		return true;
	}
}
