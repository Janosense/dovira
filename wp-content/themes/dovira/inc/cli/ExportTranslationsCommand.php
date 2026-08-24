<?php

namespace dovira\CLI;

use Throwable;
use WP_CLI;
use WP_Post;
use WP_Query;

/**
 * Exports existing translations to a bundle file, for import on another
 * environment without copying the database.
 */
class ExportTranslationsCommand {

	/**
	 * Writes the translated content of every linked post pair to a JSON bundle.
	 *
	 * Only translated text is exported. The featured image, taxonomy terms,
	 * publication date and status are re-derived from the source post at import
	 * time, so the bundle stays free of environment-specific IDs.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<post-type>]
	 * : Post type to export.
	 * ---
	 * default: post
	 * ---
	 *
	 * [--post=<id>]
	 * : Export a single source post by ID.
	 *
	 * [--post-status=<status>]
	 * : Source post status to include (comma-separated, or "any").
	 * ---
	 * default: any
	 * ---
	 *
	 * [--lang-from=<slug>]
	 * : Source language slug.
	 * ---
	 * default: uk
	 * ---
	 *
	 * [--lang-to=<slug>]
	 * : Translated language slug.
	 * ---
	 * default: ru
	 * ---
	 *
	 * [--file=<path>]
	 * : Bundle file to write. Defaults to the theme's temp-data directory.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dovira translations-export --post-type=post
	 *     wp dovira translations-export --post-type=page --file=/tmp/pages.json
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$post_type   = $assoc_args['post-type'] ?? 'post';
		$from        = $assoc_args['lang-from'] ?? 'uk';
		$to          = $assoc_args['lang-to'] ?? 'ru';
		$post_id     = (int) ( $assoc_args['post'] ?? 0 );
		$post_status = $assoc_args['post-status'] ?? 'any';
		$path        = $assoc_args['file'] ?? TranslationBundle::default_path( $post_type, $to );

		if ( ! function_exists( 'pll_get_post' ) ) {
			WP_CLI::error( 'Polylang is not active.' );
		}

		$sources = $this->get_sources( $post_type, $from, $post_id, $post_status );

		if ( empty( $sources ) ) {
			WP_CLI::error( "No \"$post_type\" posts found in language \"$from\"." );
		}

		$entries = [];
		$skipped = 0;

		foreach ( $sources as $source ) {
			$translation_id = (int) ( pll_get_post( $source->ID, $to ) ?: 0 );
			$translation    = $translation_id ? get_post( $translation_id ) : null;

			if ( ! $translation instanceof WP_Post ) {
				++$skipped;
				continue;
			}

			$entries[] = [
				'source_id'          => $source->ID,
				'source_fingerprint' => TranslationBundle::fingerprint( $source ),
				'source_title'       => $source->post_title,
				'slug'               => $translation->post_name,
				'content'            => $translation->post_content,
				'translations'       => $this->build_translation_map( $source, $translation ),
			];
		}

		if ( empty( $entries ) ) {
			WP_CLI::error( "No \"$to\" translations found for \"$post_type\"." );
		}

		$bundle = [
			'format_version' => TranslationBundle::FORMAT_VERSION,
			'post_type'      => $post_type,
			'lang_from'      => $from,
			'lang_to'        => $to,
			'exported_from'  => home_url(),
			'exported_at'    => gmdate( 'c' ),
			'entries'        => $entries,
		];

		try {
			TranslationBundle::write( $path, $bundle );
		} catch ( Throwable $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		if ( $skipped ) {
			WP_CLI::log( "Skipped $skipped source post(s) without a \"$to\" translation." );
		}

		WP_CLI::success( sprintf( 'Exported %d translation(s) to %s (%s).', count( $entries ), $path, size_format( (int) filesize( $path ) ) ) );
	}

	/**
	 * @param string $post_type
	 * @param string $from
	 * @param int    $post_id
	 * @param string $post_status
	 *
	 * @return WP_Post[]
	 */
	private function get_sources( string $post_type, string $from, int $post_id, string $post_status ): array {
		$query = new WP_Query( [
			'post_type'              => $post_type,
			'post_status'            => $post_status === 'any' ? 'any' : array_map( 'trim', explode( ',', $post_status ) ),
			'posts_per_page'         => -1,
			'lang'                   => $from,
			'p'                      => $post_id ?: 0,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		] );

		return $query->posts;
	}

	/**
	 * Rebuilds the "meta:"/"pm:" translation map that PostCopier consumes, by
	 * reading the values back off the existing translation.
	 *
	 * @param WP_Post $source
	 * @param WP_Post $translation
	 *
	 * @return array<string, string>
	 */
	private function build_translation_map( WP_Post $source, WP_Post $translation ): array {
		$map = [ 'meta:post_title' => $translation->post_title ];

		if ( $translation->post_excerpt !== '' ) {
			$map['meta:post_excerpt'] = $translation->post_excerpt;
		}

		foreach ( [ '_yoast_wpseo_title', '_yoast_wpseo_metadesc' ] as $key ) {
			$value = get_post_meta( $translation->ID, $key, true );

			if ( is_string( $value ) && $value !== '' ) {
				$map[ "meta:$key" ] = $value;
			}
		}

		// Post types whose ACF content lives in meta: export the rows the
		// translation actually changed, the rest is copied from the source.
		if ( in_array( $source->post_type, PostCopier::META_BASED_POST_TYPES, true ) ) {
			$source_meta = get_post_meta( $source->ID );

			foreach ( get_post_meta( $translation->ID ) as $key => $values ) {
				$value = $values[0] ?? null;

				if ( ! is_string( $value ) || $value === ( $source_meta[ $key ][0] ?? null ) ) {
					continue;
				}

				if ( Heuristics::is_translatable( $key, $value ) ) {
					$map[ MetaExtractor::PREFIX . $key ] = $value;
				}
			}
		}

		return $map;
	}
}
