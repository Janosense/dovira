<?php

namespace dovira\ContentMcp;

/**
 * Which post types and taxonomies the connector covers, and who may read them
 * (DECISIONS 2026-11-05 — The tool catalogue speaks ACF field names…).
 */
final class Scope {

	/** Content: read here, written by later steps. */
	public const CONTENT = [ 'service', 'employee', 'vacancy', 'page', 'post' ];

	/** Form records: read only, never written by the connector. */
	public const RECORDS = [ 'conversation', 'application', 'questionary' ];

	/** Taxonomies the connector reads (`service-city` is never written). */
	public const TAXONOMIES = [ 'category', 'post_tag', 'service-city' ];

	/**
	 * @return list<string>
	 */
	public static function types(): array {
		return array_merge( self::CONTENT, self::RECORDS );
	}

	public static function is_record( string $type ): bool {
		return in_array( $type, self::RECORDS, true );
	}

	/**
	 * Whether the current user may read posts of this type through the connector.
	 *
	 * Content types: the type's `edit_posts` capability (editors and
	 * administrators alike). Record types: the type's `edit_others_posts`
	 * (`edit_others_conversations`) — the gate of `dovira-bridge-conversations`.
	 * Not `read_post`: the records are `publish`, and core maps `read_post` of
	 * a public status to plain `read`, which an editor has.
	 */
	public static function can_read_type( string $type ): bool {
		if ( ! in_array( $type, self::types(), true ) ) {
			return false;
		}

		$object = get_post_type_object( $type );
		if ( ! $object ) {
			return false;
		}

		$cap = self::is_record( $type ) ? $object->cap->edit_others_posts : $object->cap->edit_posts;

		return current_user_can( $cap );
	}
}
