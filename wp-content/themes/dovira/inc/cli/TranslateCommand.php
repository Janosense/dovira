<?php

namespace dovira\CLI;

use Throwable;
use WP_CLI;
use WP_Post;
use WP_Query;

/**
 * Creates language copies of posts translated via the Anthropic API
 * and links them with Polylang.
 */
class TranslateCommand {

	private const LANGUAGE_NAMES = [
		'uk' => 'Ukrainian',
		'ru' => 'Russian',
		'en' => 'English',
	];

	/**
	 * Creates translated copies of posts in the target language.
	 *
	 * Extracts human-readable strings from the post (title, excerpt, ACF block
	 * data inside post_content, core block HTML, Yoast SEO meta), translates
	 * them via the Anthropic API, creates a copy of the post and links it to
	 * the source via Polylang.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<post-type>]
	 * : Post type to translate.
	 * ---
	 * default: page
	 * ---
	 *
	 * [--post=<id>]
	 * : Translate a single post by ID.
	 *
	 * [--lang-from=<slug>]
	 * : Source language slug.
	 * ---
	 * default: uk
	 * ---
	 *
	 * [--lang-to=<slug>]
	 * : Target language slug.
	 * ---
	 * default: ru
	 * ---
	 *
	 * [--dry-run]
	 * : Extract and translate, print the result, but do not create posts.
	 *
	 * [--overwrite]
	 * : Re-translate and update posts that already have a translation.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dovira translate --post-type=page --dry-run --post=42
	 *     wp dovira translate --post-type=page
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$post_type = $assoc_args['post-type'] ?? 'page';
		$from      = $assoc_args['lang-from'] ?? 'uk';
		$to        = $assoc_args['lang-to'] ?? 'ru';
		$post_id   = (int) ( $assoc_args['post'] ?? 0 );
		$dry_run   = isset( $assoc_args['dry-run'] );
		$overwrite = isset( $assoc_args['overwrite'] );

		$this->check_requirements( $from, $to );

		// CLI runs without a user, so kses would strip block comments on
		// wp_insert_post(). Content originates from trusted DB content.
		kses_remove_filters();

		$extractor  = new ContentExtractor();
		$translator = new AnthropicTranslator(
			ANTHROPIC_API_KEY,
			self::LANGUAGE_NAMES[ $from ] ?? $from,
			self::LANGUAGE_NAMES[ $to ] ?? $to
		);
		$copier     = new PostCopier();

		$posts = $this->get_posts( $post_type, $from, $post_id );

		if ( empty( $posts ) ) {
			WP_CLI::error( "No \"$post_type\" posts found in language \"$from\"." );
		}

		WP_CLI::log( sprintf( 'Found %d post(s) to process.', count( $posts ) ) );

		$results = [];
		$total   = count( $posts );

		foreach ( $posts as $i => $post ) {
			$number = $i + 1;
			$label  = "{$post->post_title} (#{$post->ID})";

			$existing_id = (int) ( pll_get_post( $post->ID, $to ) ?: 0 );

			if ( $existing_id && ! $overwrite ) {
				WP_CLI::log( "[$number/$total] Skipped (translation #$existing_id exists): $label" );
				$results[] = $this->result( $post, 'skipped', $existing_id );
				continue;
			}

			WP_CLI::log( "[$number/$total] Translating: $label" );

			try {
				$blocks = parse_blocks( $post->post_content );
				$map    = $extractor->extract( $blocks ) + $this->get_meta_map( $post );

				if ( empty( $map ) ) {
					WP_CLI::warning( "No translatable strings found in: $label" );
				}

				$translations = $translator->translate_map( $map );
				$content      = serialize_blocks( $extractor->inject( $blocks, $translations ) );

				if ( $dry_run ) {
					$this->print_dry_run( $map, $translations );
					$results[] = $this->result( $post, 'dry-run', 0 );
					continue;
				}

				$new_id = $copier->copy( $post, $content, $translations, $from, $to, $existing_id );

				$status    = $existing_id ? 'updated' : 'created';
				$results[] = $this->result( $post, $status, $new_id );
				WP_CLI::log( "[$number/$total] " . ucfirst( $status ) . " translation #$new_id" );
			} catch ( Throwable $e ) {
				WP_CLI::warning( "[$number/$total] Failed: {$e->getMessage()}" );
				$results[] = $this->result( $post, 'error', 0, $e->getMessage() );
			}
		}

		WP_CLI\Utils\format_items( 'table', $results, [ 'id', 'title', 'status', 'translation_id', 'error' ] );

		$errors = count( array_filter( $results, fn( $row ) => $row['status'] === 'error' ) );

		if ( $errors ) {
			WP_CLI::warning( "Finished with $errors error(s)." );
		} else {
			WP_CLI::success( 'Finished.' );
		}
	}

	/**
	 * Fatal-checks the environment before doing any work.
	 *
	 * @param string $from
	 * @param string $to
	 */
	private function check_requirements( string $from, string $to ): void {
		if ( ! defined( 'ANTHROPIC_API_KEY' ) || ANTHROPIC_API_KEY === '' ) {
			WP_CLI::error( 'ANTHROPIC_API_KEY constant is not defined in wp-config.php.' );
		}

		if ( ! function_exists( 'pll_set_post_language' ) ) {
			WP_CLI::error( 'Polylang is not active.' );
		}

		$languages = pll_languages_list();

		foreach ( [ $from, $to ] as $slug ) {
			if ( ! in_array( $slug, $languages, true ) ) {
				WP_CLI::error( "Language \"$slug\" is not registered in Polylang. Available: " . implode( ', ', $languages ) );
			}
		}
	}

	/**
	 * Queries source-language posts, parents first, so that parent
	 * translations exist before their children are processed.
	 *
	 * @param string $post_type
	 * @param string $from
	 * @param int    $post_id
	 *
	 * @return WP_Post[]
	 */
	private function get_posts( string $post_type, string $from, int $post_id ): array {
		$query = new WP_Query( [
			'post_type'              => $post_type,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'lang'                   => $from,
			'p'                      => $post_id ?: 0,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		] );

		$posts = $query->posts;

		usort( $posts, function ( WP_Post $a, WP_Post $b ) {
			return count( get_post_ancestors( $a ) ) <=> count( get_post_ancestors( $b ) );
		} );

		return $posts;
	}

	/**
	 * Builds the synthetic "meta:" entries of the translation map.
	 *
	 * @param WP_Post $post
	 *
	 * @return array<string, string>
	 */
	private function get_meta_map( WP_Post $post ): array {
		$map = [ 'meta:post_title' => $post->post_title ];

		if ( $post->post_excerpt !== '' ) {
			$map['meta:post_excerpt'] = $post->post_excerpt;
		}

		foreach ( [ '_yoast_wpseo_title', '_yoast_wpseo_metadesc' ] as $key ) {
			$value = get_post_meta( $post->ID, $key, true );

			if ( is_string( $value ) && $value !== '' ) {
				$map[ "meta:$key" ] = $value;
			}
		}

		return $map;
	}

	/**
	 * Prints the source => translated pairs of a dry run.
	 *
	 * @param array<string, string> $map
	 * @param array<string, string> $translations
	 */
	private function print_dry_run( array $map, array $translations ): void {
		foreach ( $map as $path => $source ) {
			WP_CLI::log( "  $path" );
			WP_CLI::log( "    UK: $source" );
			WP_CLI::log( '    →  ' . ( $translations[ $path ] ?? '(missing)' ) );
		}
	}

	/**
	 * @param WP_Post $post
	 * @param string  $status
	 * @param int     $translation_id
	 * @param string  $error
	 *
	 * @return array<string, string|int>
	 */
	private function result( WP_Post $post, string $status, int $translation_id, string $error = '' ): array {
		return [
			'id'             => $post->ID,
			'title'          => $post->post_title,
			'status'         => $status,
			'translation_id' => $translation_id ?: '—',
			'error'          => $error,
		];
	}
}
