<?php

namespace dovira\CLI;

use Throwable;
use WP_CLI;
use WP_Post;

/**
 * Imports a translation bundle produced by "wp dovira translations-export",
 * recreating the translated posts and their Polylang links.
 */
class ImportTranslationsCommand {

	/**
	 * Creates translated posts from a bundle file.
	 *
	 * Each entry is matched to its source post by ID and verified against a
	 * fingerprint (post type, slug, publication date). The translation is then
	 * built exactly as the translate command builds it — same slug, dates,
	 * featured image, taxonomy mapping and Polylang linkage — but with the
	 * translated text taken from the bundle instead of the Anthropic API.
	 *
	 * Safe to re-run: entries that already have a translation are skipped
	 * unless --overwrite is passed.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the bundle file.
	 *
	 * [--dry-run]
	 * : Validate the bundle against this database without writing anything.
	 *
	 * [--overwrite]
	 * : Update translations that already exist.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dovira translations-import translations-post-ru.json --dry-run
	 *     wp dovira translations-import translations-post-ru.json
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$path      = $args[0] ?? '';
		$dry_run   = isset( $assoc_args['dry-run'] );
		$overwrite = isset( $assoc_args['overwrite'] );

		try {
			$bundle = TranslationBundle::read( $path );
		} catch ( Throwable $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		$from = (string) ( $bundle['lang_from'] ?? 'uk' );
		$to   = (string) ( $bundle['lang_to'] ?? 'ru' );

		$this->check_requirements( $from, $to );

		WP_CLI::log( sprintf(
			'Bundle: %d entr(ies), %s → %s, %s, exported %s from %s.',
			count( $bundle['entries'] ),
			$from,
			$to,
			$bundle['post_type'] ?? 'unknown type',
			$bundle['exported_at'] ?? 'unknown date',
			$bundle['exported_from'] ?? 'unknown site'
		) );

		// CLI runs without a user, so kses would strip block comments on
		// wp_insert_post(). Content originates from trusted bundle content.
		kses_remove_filters();

		$copier  = new PostCopier();
		$results = [];
		$total   = count( $bundle['entries'] );

		foreach ( array_values( $bundle['entries'] ) as $i => $entry ) {
			$number    = $i + 1;
			$source_id = (int) ( $entry['source_id'] ?? 0 );
			$label     = ( $entry['source_title'] ?? '(untitled)' ) . " (#$source_id)";
			$source    = $source_id ? get_post( $source_id ) : null;

			if ( ! $source instanceof WP_Post ) {
				WP_CLI::warning( "[$number/$total] Source post #$source_id does not exist here — skipped." );
				$results[] = $this->result( $source_id, $label, 'missing-source', 0, "No post #$source_id" );
				continue;
			}

			if ( TranslationBundle::fingerprint( $source ) !== ( $entry['source_fingerprint'] ?? '' ) ) {
				WP_CLI::warning( "[$number/$total] Post #$source_id does not match the exported source (type, slug or date differs) — skipped." );
				$results[] = $this->result( $source_id, $source->post_title, 'mismatch', 0, 'Fingerprint mismatch' );
				continue;
			}

			$existing_id = (int) ( pll_get_post( $source->ID, $to ) ?: 0 );

			if ( $existing_id && ! $overwrite ) {
				WP_CLI::log( "[$number/$total] Skipped (translation #$existing_id exists): $label" );
				$results[] = $this->result( $source_id, $source->post_title, 'skipped', $existing_id );
				continue;
			}

			if ( $dry_run ) {
				$action    = $existing_id ? 'would update' : 'would create';
				WP_CLI::log( "[$number/$total] OK — $action translation for: $label" );
				$results[] = $this->result( $source_id, $source->post_title, 'dry-run', $existing_id );
				continue;
			}

			try {
				$post_id = $copier->copy(
					$source,
					(string) ( $entry['content'] ?? '' ),
					(array) ( $entry['translations'] ?? [] ),
					$from,
					$to,
					$existing_id,
					$entry['slug'] ?? null
				);

				$status    = $existing_id ? 'updated' : 'created';
				$results[] = $this->result( $source_id, $source->post_title, $status, $post_id );
				WP_CLI::log( "[$number/$total] " . ucfirst( $status ) . " translation #$post_id" );
			} catch ( Throwable $e ) {
				WP_CLI::warning( "[$number/$total] Failed: {$e->getMessage()}" );
				$results[] = $this->result( $source_id, $source->post_title, 'error', 0, $e->getMessage() );
			}
		}

		WP_CLI\Utils\format_items( 'table', $results, [ 'source_id', 'title', 'status', 'translation_id', 'error' ] );

		$failed = count( array_filter(
			$results,
			fn( $row ) => in_array( $row['status'], [ 'error', 'missing-source', 'mismatch' ], true )
		) );

		if ( $failed ) {
			WP_CLI::warning( "Finished with $failed problem(s)." );
		} elseif ( $dry_run ) {
			WP_CLI::success( 'Bundle validated, nothing written.' );
		} else {
			WP_CLI::success( 'Finished.' );
		}
	}

	/**
	 * @param string $from
	 * @param string $to
	 */
	private function check_requirements( string $from, string $to ): void {
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
	 * @param int    $source_id
	 * @param string $title
	 * @param string $status
	 * @param int    $translation_id
	 * @param string $error
	 *
	 * @return array<string, string|int>
	 */
	private function result( int $source_id, string $title, string $status, int $translation_id, string $error = '' ): array {
		return [
			'source_id'      => $source_id,
			'title'          => $title,
			'status'         => $status,
			'translation_id' => $translation_id ?: '—',
			'error'          => $error,
		];
	}
}
