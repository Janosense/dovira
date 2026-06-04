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

		foreach ( self::COPY_META as $key ) {
			$value = get_post_meta( $source->ID, $key, true );

			if ( $value !== '' && $value !== false ) {
				update_post_meta( $post_id, $key, $value );
			}
		}

		foreach ( self::TRANSLATED_META as $key ) {
			if ( isset( $translations[ "meta:$key" ] ) ) {
				update_post_meta( $post_id, $key, wp_slash( $translations[ "meta:$key" ] ) );
			}
		}

		pll_set_post_language( $post_id, $to );
		pll_save_post_translations( [
			$from => $source->ID,
			$to   => $post_id,
		] );

		return $post_id;
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
