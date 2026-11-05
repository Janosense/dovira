<?php

namespace dovira\ContentMcp;

/**
 * An image uploaded to the media library (`upload-media`), from a URL or
 * from base64, planned and then uploaded. The preview downloads nothing: a
 * URL is checked by its scheme and host and a HEAD request (type, size), base64
 * by its decoded bytes. Only the confirmed call downloads — capped at the
 * size limit — and hands the file to `media_handle_sideload()` as the
 * connected user, the call `media_sideload_image()` makes.
 */
final class MediaWriter {

	/** The filter of the size cap in bytes. */
	public const MAX_BYTES_FILTER = 'dovira_mcp_upload_max_bytes';

	/** The default size cap: 10 MB. */
	private const MAX_BYTES = 10 * 1024 * 1024;

	/**
	 * @param array<string, mixed> $input `url` | `base64`, `filename`, `alt`, `title`, `attach_to`.
	 * @return array{diff: Diff, apply: callable(): (array<string, mixed>|\WP_Error)}|\WP_Error
	 */
	public function plan( array $input ) {
		$url    = trim( (string) ( $input['url'] ?? '' ) );
		$base64 = (string) ( $input['base64'] ?? '' );
		if ( ( '' === $url ) === ( '' === $base64 ) ) {
			return new \WP_Error( 'dovira_media_source_invalid', 'Передайте рівно одне: url (http/https) або base64 зображення.' );
		}

		$cap   = self::max_bytes();
		$types = self::image_types();
		$diff  = new Diff();

		$attach_to = (int) ( $input['attach_to'] ?? 0 );
		$attached  = null;
		if ( $attach_to > 0 ) {
			$attached = UpdatePost::content_post( $attach_to );
			if ( is_wp_error( $attached ) ) {
				return $attached;
			}
		}

		if ( '' !== $url ) {
			$checked = self::check_url( $url, $cap, $types );
			if ( is_wp_error( $checked ) ) {
				return $checked;
			}
			$mime     = $checked;
			$bytes    = null;
			$filename = (string) ( $input['filename'] ?? '' );
			if ( '' === $filename ) {
				$filename = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
			}
			$diff->add( null, null, 'source', null, $url );
		} else {
			$decoded = self::decode( $base64, $cap, $types );
			if ( is_wp_error( $decoded ) ) {
				return $decoded;
			}
			[ $bytes, $mime ] = $decoded;
			$filename         = (string) ( $input['filename'] ?? '' );
			$diff->add( null, null, 'source', null, sprintf( 'base64, %d KB', (int) ceil( strlen( $bytes ) / 1024 ) ) );
		}

		$filename = self::filename( '' !== $filename ? $filename : 'image', $mime );
		$title    = isset( $input['title'] ) ? (string) $input['title'] : null;
		$alt      = isset( $input['alt'] ) ? (string) $input['alt'] : null;

		$diff->add( null, null, 'filename', null, $filename );
		if ( null !== $title ) {
			$diff->add( null, null, 'title', null, $title );
		}
		if ( null !== $alt ) {
			$diff->add( null, null, 'alt', null, $alt );
		}
		if ( null !== $mime ) {
			$diff->add( null, null, 'mime', null, $mime );
		}
		if ( is_object( $attached ) ) {
			$diff->add(
				null,
				null,
				'attach_to',
				null,
				[
					'id'    => $attach_to,
					'title' => (string) $attached->post_title,
				]
			);
		}

		return [
			'diff'  => $diff,
			'apply' => static fn() => self::upload( $url, $bytes, $filename, $title, $alt, $attach_to, $cap, $types ),
		];
	}

	/**
	 * The size cap in bytes (filter `dovira_mcp_upload_max_bytes`, at least 1).
	 */
	public static function max_bytes(): int {
		return max( 1, (int) apply_filters( self::MAX_BYTES_FILTER, self::MAX_BYTES ) );
	}

	/**
	 * The raster image types the user may upload: the `image/*` types of
	 * `get_allowed_mime_types()`, never SVG (its bytes are markup, and
	 * `wp_get_image_mime()` cannot read it).
	 *
	 * @return array<string, string> Extension pattern → mime type.
	 */
	private static function image_types(): array {
		return array_filter(
			(array) get_allowed_mime_types(),
			static fn( $mime ) => str_starts_with( (string) $mime, 'image/' ) && 'image/svg+xml' !== $mime
		);
	}

	/**
	 * A URL's scheme, host, type and declared size; nothing is downloaded.
	 *
	 * @param array<string, string> $types
	 * @return string|null|\WP_Error The image type, when the server or the extension names one.
	 */
	private static function check_url( string $url, int $cap, array $types ) {
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) || ! wp_http_validate_url( $url ) ) {
			return new \WP_Error( 'dovira_media_url_invalid', 'Адреса зображення — лише публічна http(s)-адреса.' );
		}

		$type   = '';
		$length = '';
		$head   = wp_safe_remote_head(
			$url,
			[
				'timeout'     => 10,
				'redirection' => 3,
			]
		);
		if ( ! is_wp_error( $head ) ) {
			$code = (int) wp_remote_retrieve_response_code( $head );
			if ( $code >= 200 && $code < 300 ) {
				$type   = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $head, 'content-type' ) )[0] ) );
				$length = (string) wp_remote_retrieve_header( $head, 'content-length' );
			}
		}

		if ( '' === $type ) {
			$type = (string) ( wp_check_filetype( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ), $types )['type'] ?? '' );
		}
		if ( ! in_array( $type, $types, true ) ) {
			return new \WP_Error( 'dovira_media_type_invalid', sprintf( 'За адресою не зображення дозволеного типу (%s); можна: %s.', '' === $type ? 'тип невідомий' : $type, implode( ', ', array_unique( $types ) ) ) );
		}

		if ( is_numeric( $length ) && (int) $length > $cap ) {
			return new \WP_Error( 'dovira_media_too_large', self::too_large( (int) $length, $cap ) );
		}

		return $type;
	}

	/**
	 * Base64 (a `data:` prefix allowed) → its bytes and their image type.
	 *
	 * @param array<string, string> $types
	 * @return array{0: string, 1: string}|\WP_Error
	 */
	private static function decode( string $base64, int $cap, array $types ) {
		$base64 = (string) preg_replace( '#^data:[^,]*,#', '', trim( $base64 ) );
		$base64 = (string) preg_replace( '#\s+#', '', $base64 );

		// The decoded size is known from the length, before decoding.
		$estimated = intdiv( strlen( $base64 ) * 3, 4 );
		if ( $estimated > $cap ) {
			return new \WP_Error( 'dovira_media_too_large', self::too_large( $estimated, $cap ) );
		}

		$bytes = base64_decode( $base64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $bytes || '' === $bytes ) {
			return new \WP_Error( 'dovira_media_base64_invalid', 'base64 не розкодовується.' );
		}

		$info = @getimagesizefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$mime = is_array( $info ) ? (string) ( $info['mime'] ?? '' ) : '';
		if ( ! in_array( $mime, $types, true ) ) {
			return new \WP_Error( 'dovira_media_type_invalid', sprintf( 'Це не зображення дозволеного типу; можна: %s.', implode( ', ', array_unique( $types ) ) ) );
		}

		return [ $bytes, $mime ];
	}

	/**
	 * The downloaded or decoded file, checked again by its bytes, into the
	 * media library.
	 *
	 * @param array<string, string> $types
	 * @return array<string, mixed>|\WP_Error `{id, url, alt, mime, width, height}`.
	 */
	private static function upload( string $url, ?string $bytes, string $filename, ?string $title, ?string $alt, int $attach_to, int $cap, array $types ) {
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp = (string) wp_tempnam( $filename );
		try {
			if ( null === $bytes ) {
				$response = wp_safe_remote_get(
					$url,
					[
						'timeout'             => 60,
						'stream'              => true,
						'filename'            => $tmp,
						'limit_response_size' => $cap + 1,
					]
				);
				if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
					return new \WP_Error( 'dovira_media_download_failed', sprintf( 'Зображення не завантажилось: %s. Нічого не додано.', is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . (int) wp_remote_retrieve_response_code( $response ) ) );
				}
			} elseif ( false === file_put_contents( $tmp, $bytes ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				return new \WP_Error( 'dovira_media_upload_failed', 'Тимчасовий файл не записано. Нічого не додано.' );
			}

			$size = (int) filesize( $tmp );
			if ( $size > $cap ) {
				return new \WP_Error( 'dovira_media_too_large', self::too_large( $size, $cap ) );
			}

			$mime = (string) wp_get_image_mime( $tmp );
			if ( ! in_array( $mime, $types, true ) ) {
				return new \WP_Error( 'dovira_media_type_invalid', sprintf( 'Завантажений файл — не зображення дозволеного типу (%s). Нічого не додано.', '' === $mime ? 'тип невідомий' : $mime ) );
			}

			$id = media_handle_sideload(
				[
					'name'     => self::filename( $filename, $mime ),
					'tmp_name' => $tmp,
				],
				$attach_to,
				$title
			);
			if ( is_wp_error( $id ) ) {
				return new \WP_Error( 'dovira_media_upload_failed', 'Зображення не додано до медіатеки: ' . $id->get_error_message() );
			}
			$id = (int) $id;

			if ( null !== $alt ) {
				update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
			}

			$meta = wp_get_attachment_metadata( $id );

			return [
				'id'     => $id,
				'url'    => (string) wp_get_attachment_url( $id ),
				'alt'    => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
				'mime'   => (string) get_post_mime_type( $id ),
				'width'  => is_array( $meta ) ? (int) ( $meta['width'] ?? 0 ) : 0,
				'height' => is_array( $meta ) ? (int) ( $meta['height'] ?? 0 ) : 0,
			];
		} finally {
			if ( '' !== $tmp && file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
		}
	}

	/**
	 * A safe file name whose extension matches the image type.
	 */
	private static function filename( string $name, ?string $mime ): string {
		$name = (string) sanitize_file_name( $name );
		if ( null === $mime || '' === $mime ) {
			return $name;
		}

		$ext = (string) wp_get_default_extension_for_mime_type( $mime );
		if ( '' === $ext ) {
			return $name;
		}

		$base = (string) pathinfo( $name, PATHINFO_FILENAME );

		return ( '' !== $base ? $base : 'image' ) . '.' . $ext;
	}

	private static function too_large( int $bytes, int $cap ): string {
		return sprintf( 'Зображення завелике: %.1f MB, можна до %.1f MB. Нічого не завантажено.', $bytes / 1048576, $cap / 1048576 );
	}
}
