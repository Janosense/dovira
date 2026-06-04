<?php

namespace dovira\CLI;

use RuntimeException;
use WP_Post;

/**
 * Creates (or updates) the translated copy of a post and links it
 * to the source via Polylang.
 */
class PostCopier {

	/**
	 * Meta keys copied verbatim from the source post.
	 */
	private const COPY_META = [ '_wp_page_template', '_thumbnail_id' ];

	/**
	 * Meta keys whose translated values come from the translation map
	 * (under the "meta:" path prefix).
	 */
	private const TRANSLATED_META = [ '_yoast_wpseo_title', '_yoast_wpseo_metadesc' ];

	/**
	 * Post types whose ACF content lives in post meta: all meta is copied
	 * over and translated "pm:*" values overwrite the copies.
	 */
	public const META_BASED_POST_TYPES = [ 'service', 'vacancy' ];

	/**
	 * Meta keys never copied to the translation.
	 */
	private const META_BLACKLIST = [ '_edit_lock', '_edit_last', '_wp_old_slug' ];

	/**
	 * @param WP_Post               $source       Source post (in the source language).
	 * @param string                $content      Rebuilt, translated post_content.
	 * @param array<string, string> $translations Translation map (includes "meta:*" paths).
	 * @param string                $from         Source language slug.
	 * @param string                $to           Target language slug.
	 * @param int                   $existing_id  Existing translation ID to update (0 to create).
	 *
	 * @return int New (or updated) post ID.
	 * @throws RuntimeException When the insert/update fails.
	 */
	public function copy( WP_Post $source, string $content, array $translations, string $from, string $to, int $existing_id = 0 ): int {
		$title = $translations['meta:post_title'] ?? $source->post_title;

		$postarr = [
			'post_type'    => $source->post_type,
			'post_status'  => $source->post_status,
			'post_author'  => $source->post_author,
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $translations['meta:post_excerpt'] ?? $source->post_excerpt,
			'post_name'    => $this->get_slug( $title, $to ),
			'post_parent'  => $source->post_parent ? (int) ( pll_get_post( $source->post_parent, $to ) ?: 0 ) : 0,
			'menu_order'   => $source->menu_order,
		];

		if ( $existing_id ) {
			$postarr['ID'] = $existing_id;
			$post_id       = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$post_id = wp_insert_post( wp_slash( $postarr ), true );
		}

		if ( is_wp_error( $post_id ) ) {
			throw new RuntimeException( 'Failed to save translated post: ' . $post_id->get_error_message() );
		}

		if ( in_array( $source->post_type, self::META_BASED_POST_TYPES, true ) ) {
			$this->copy_all_meta( $source->ID, $post_id, $translations );
		} else {
			foreach ( self::COPY_META as $key ) {
				$value = get_post_meta( $source->ID, $key, true );

				if ( $value !== '' && $value !== false ) {
					update_post_meta( $post_id, $key, $value );
				}
			}
		}

		foreach ( self::TRANSLATED_META as $key ) {
			if ( isset( $translations[ "meta:$key" ] ) ) {
				update_post_meta( $post_id, $key, wp_slash( $translations[ "meta:$key" ] ) );
			}
		}

		$this->copy_taxonomy_terms( $source, $post_id );

		pll_set_post_language( $post_id, $to );
		pll_save_post_translations( [
			$from => $source->ID,
			$to   => $post_id,
		] );

		return $post_id;
	}

	/**
	 * Copies all post meta from the source, then overwrites the keys that
	 * were translated (the "pm:" entries of the translation map).
	 *
	 * @param int                   $source_id
	 * @param int                   $post_id
	 * @param array<string, string> $translations
	 */
	private function copy_all_meta( int $source_id, int $post_id, array $translations ): void {
		foreach ( get_post_meta( $source_id ) as $key => $values ) {
			if ( in_array( $key, self::META_BLACKLIST, true ) || str_starts_with( $key, '_pll_' ) ) {
				continue;
			}

			// The all-meta form returns raw (still serialized) values;
			// unserialize so update_post_meta() re-serializes exactly once.
			update_post_meta( $post_id, $key, wp_slash( maybe_unserialize( $values[0] ?? '' ) ) );
		}

		foreach ( $translations as $path => $value ) {
			if ( str_starts_with( $path, MetaExtractor::PREFIX ) ) {
				update_post_meta( $post_id, substr( $path, strlen( MetaExtractor::PREFIX ) ), wp_slash( $value ) );
			}
		}
	}

	/**
	 * Copies taxonomy term assignments verbatim. Terms are shared across
	 * languages here (service-city is not a translated taxonomy), so the
	 * same term IDs are assigned. Polylang's internal taxonomies are managed
	 * by pll_set_post_language()/pll_save_post_translations() and skipped.
	 *
	 * @param WP_Post $source
	 * @param int     $post_id
	 */
	private function copy_taxonomy_terms( WP_Post $source, int $post_id ): void {
		foreach ( get_object_taxonomies( $source->post_type ) as $taxonomy ) {
			if ( in_array( $taxonomy, [ 'language', 'post_translations' ], true ) ) {
				continue;
			}

			$term_ids = wp_get_object_terms( $source->ID, $taxonomy, [ 'fields' => 'ids' ] );

			if ( ! is_wp_error( $term_ids ) && ! empty( $term_ids ) ) {
				wp_set_object_terms( $post_id, $term_ids, $taxonomy );
			}
		}
	}

	/**
	 * Generates a transliterated slug for the target language.
	 *
	 * Cyr2lat picks its transliteration table from the site locale, which may
	 * lack letters specific to the target language (e.g. "ы" is missing from
	 * the Ukrainian table). Point it at the target language locale instead.
	 *
	 * @param string $title Translated post title.
	 * @param string $to    Target language slug.
	 *
	 * @return string
	 */
	private function get_slug( string $title, string $to ): string {
		$language = function_exists( 'PLL' ) ? PLL()->model->get_language( $to ) : false;
		$filter   = $language ? fn() => $language->locale : null;

		if ( $filter ) {
			add_filter( 'ctl_locale', $filter );
		}

		$slug = sanitize_title( $title );

		if ( $filter ) {
			remove_filter( 'ctl_locale', $filter );
		}

		return $slug;
	}
}
