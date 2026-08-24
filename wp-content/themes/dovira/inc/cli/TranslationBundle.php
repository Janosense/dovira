<?php

namespace dovira\CLI;

use RuntimeException;
use WP_Post;

/**
 * Read/write format for translation bundles moved between environments.
 *
 * A bundle carries only translated *content*, keyed by the ID of the source
 * post it belongs to. Everything else the translation needs (featured image,
 * taxonomy terms, publication date, status) is re-derived from the source post
 * on the target site, so no ID in the bundle has to be remapped — the source
 * post ID is the single shared reference, and it is fingerprinted so a
 * mismatched database is caught instead of silently writing to the wrong post.
 */
class TranslationBundle {

	public const FORMAT_VERSION = 1;

	/**
	 * Identifies a source post by its stable, human-visible properties, so an
	 * import can prove it is looking at the same post the export came from.
	 *
	 * @param WP_Post $post
	 *
	 * @return string
	 */
	public static function fingerprint( WP_Post $post ): string {
		return md5( implode( '|', [ $post->post_type, $post->post_name, $post->post_date ] ) );
	}

	/**
	 * @param string $path
	 * @param array  $bundle
	 *
	 * @throws RuntimeException When the file cannot be written.
	 */
	public static function write( string $path, array $bundle ): void {
		$directory = dirname( $path );

		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			throw new RuntimeException( "Could not create directory \"$directory\"." );
		}

		$json = wp_json_encode( $bundle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

		if ( $json === false ) {
			throw new RuntimeException( 'Could not encode the bundle as JSON.' );
		}

		if ( file_put_contents( $path, $json ) === false ) {
			throw new RuntimeException( "Could not write \"$path\"." );
		}
	}

	/**
	 * @param string $path
	 *
	 * @return array
	 * @throws RuntimeException When the file is missing or malformed.
	 */
	public static function read( string $path ): array {
		if ( ! is_readable( $path ) ) {
			throw new RuntimeException( "Bundle file \"$path\" is missing or not readable." );
		}

		$bundle = json_decode( (string) file_get_contents( $path ), true );

		if ( ! is_array( $bundle ) || ! isset( $bundle['entries'] ) || ! is_array( $bundle['entries'] ) ) {
			throw new RuntimeException( "Bundle file \"$path\" is not a valid translation bundle." );
		}

		$version = (int) ( $bundle['format_version'] ?? 0 );

		if ( $version !== self::FORMAT_VERSION ) {
			throw new RuntimeException(
				sprintf( 'Bundle format version %d is not supported (expected %d).', $version, self::FORMAT_VERSION )
			);
		}

		return $bundle;
	}

	/**
	 * Default bundle location inside the theme.
	 *
	 * @param string $post_type
	 * @param string $to
	 *
	 * @return string
	 */
	public static function default_path( string $post_type, string $to ): string {
		return get_template_directory() . "/temp-data/translations-$post_type-$to.json";
	}
}
