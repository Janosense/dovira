<?php
/**
 * Tests for dovira\ContentMcp\MediaWriter and dovira/upload-media: an image
 * from a URL or base64, checked before anything is downloaded.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\ContentMcp;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use dovira\ContentMcp\MediaWriter;
use dovira\ContentMcp\Session;
use dovira\ContentMcp\UploadMedia;
use dovira\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

foreach ( [ 'Catalogue', 'Scope', 'Session', 'Confirmation', 'Diff', 'Dump', 'WriteAbility', 'UpdatePost', 'MediaWriter', 'UploadMedia' ] as $dovira_class ) {
	require_once dirname( __DIR__, 3 ) . "/inc/features/content-mcp/{$dovira_class}.php";
}

/**
 * Only http(s) to public hosts, only raster images, never over the cap; the
 * preview sends a HEAD at most, the upload happens on the confirmed call.
 */
final class MediaWriterTest extends TestCase {

	/** A 1×1 PNG. */
	private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

	/** @var array<string, mixed> The HEAD answer per URL; a missing URL answers a WP_Error. */
	private array $head = [];

	/** The bytes a GET downloads. */
	private string $body = '';

	/** @var list<array{0: string, 1: mixed}> Every request and write, in order. */
	private array $events = [];

	/** @var list<string> Temp files handed out. */
	private array $temps = [];

	/** @var array<string, mixed> */
	private array $transients = [];

	/** @var list<string> */
	private array $caps = [ 'upload_files', 'edit_post' ];

	protected function setUp(): void {
		parent::setUp();
		Session::reset();

		Functions\when( 'get_allowed_mime_types' )->justReturn(
			[
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
				'gif'          => 'image/gif',
				'webp'         => 'image/webp',
				'svg'          => 'image/svg+xml',
				'pdf'          => 'application/pdf',
			]
		);
		Functions\when( 'wp_parse_url' )->alias( static fn( string $url, int $part = -1 ) => parse_url( $url, $part ) );
		Functions\when( 'wp_http_validate_url' )->alias( static fn( string $url ) => in_array( parse_url( $url, PHP_URL_HOST ), [ '127.0.0.1', 'localhost' ], true ) ? false : $url );
		Functions\when( 'wp_safe_remote_head' )->alias(
			function ( string $url ) {
				$this->events[] = [ 'HEAD', $url ];
				return $this->head[ $url ] ?? new \WP_Error( 'http_request_failed', 'no' );
			}
		);
		Functions\when( 'wp_safe_remote_get' )->alias(
			function ( string $url, array $args ) {
				$this->events[] = [ 'GET', $args['limit_response_size'] ];
				file_put_contents( $args['filename'], substr( $this->body, 0, $args['limit_response_size'] ) );
				return [ 'response' => [ 'code' => 200 ] ];
			}
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( static fn( $r ) => $r['response']['code'] ?? '' );
		Functions\when( 'wp_remote_retrieve_header' )->alias( static fn( $r, string $h ) => $r['headers'][ $h ] ?? '' );
		Functions\when( 'wp_check_filetype' )->alias(
			static function ( string $file, array $mimes ) {
				foreach ( $mimes as $ext => $mime ) {
					if ( preg_match( '!\.(' . $ext . ')$!i', $file ) ) {
						return [ 'ext' => $ext, 'type' => $mime ];
					}
				}
				return [ 'ext' => false, 'type' => false ];
			}
		);
		Functions\when( 'wp_tempnam' )->alias(
			function () {
				$file          = (string) tempnam( sys_get_temp_dir(), 'dmw' );
				$this->temps[] = $file;
				return $file;
			}
		);
		Functions\when( 'wp_get_image_mime' )->alias( static fn( string $file ) => ( @getimagesize( $file ) ?: [] )['mime'] ?? false );
		Functions\when( 'wp_delete_file' )->alias( static fn( string $file ) => @unlink( $file ) );
		Functions\when( 'sanitize_file_name' )->alias( static fn( string $name ) => (string) preg_replace( '/[^A-Za-z0-9._-]+/', '-', $name ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( string $text ) => trim( $text ) );
		Functions\when( 'wp_get_default_extension_for_mime_type' )->alias( static fn( string $mime ) => [ 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif' ][ $mime ] ?? false );
		Functions\when( 'media_handle_sideload' )->alias(
			function ( array $file, int $post_id, ?string $title ) {
				$this->events[] = [ 'sideload', [ $file['name'], $post_id, $title ] ];
				return 901;
			}
		);
		Functions\when( 'update_post_meta' )->alias(
			function ( int $id, string $key, $value ) {
				$this->events[] = [ "meta:{$key}", [ $id, $value ] ];
				return true;
			}
		);
		Functions\when( 'get_post_meta' )->justReturn( 'Кіт на огляді' );
		Functions\when( 'wp_get_attachment_metadata' )->justReturn( [ 'width' => 1, 'height' => 1 ] );
		Functions\when( 'wp_get_attachment_url' )->justReturn( 'https://x/wp-content/uploads/photo.png' );
		Functions\when( 'get_post_mime_type' )->justReturn( 'image/png' );
		Functions\when( 'get_post' )->alias(
			static fn( int $id ) => [
				42 => (object) [ 'ID' => 42, 'post_type' => 'employee', 'post_title' => 'Лікар' ],
				70 => (object) [ 'ID' => 70, 'post_type' => 'conversation', 'post_title' => 'x' ],
			][ $id ] ?? null
		);
		Functions\when( 'current_user_can' )->alias( fn( string $cap ) => in_array( $cap, $this->caps, true ) );

		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'set_transient' )->alias(
			function ( string $key, $value ): bool {
				$this->transients[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'get_transient' )->alias( fn( string $key ) => $this->transients[ $key ] ?? false );
		Functions\when( 'delete_transient' )->alias(
			function ( string $key ): bool {
				unset( $this->transients[ $key ] );
				return true;
			}
		);
	}

	protected function tearDown(): void {
		foreach ( $this->temps as $file ) {
			@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		parent::tearDown();
	}

	public function test_a_url_is_checked_by_a_head_request_and_nothing_is_downloaded_at_preview(): void {
		$this->head['https://cdn.example.com/a/photo.png'] = self::head( 'image/png; charset=binary', '1234' );

		$plan = ( new MediaWriter() )->plan( [ 'url' => 'https://cdn.example.com/a/photo.png', 'alt' => 'Кіт на огляді' ] );

		$this->assertSame( [ 'source', 'filename', 'alt', 'mime' ], array_column( $plan['diff']->lines(), 'field' ) );
		$this->assertSame( 'image/png', $plan['diff']->lines()[3]['to'] );
		foreach ( $plan['diff']->lines() as $line ) {
			$this->assertSame( [ 'id' => null, 'lang' => null ], $line['target'] );
		}
		$this->assertSame( [ [ 'HEAD', 'https://cdn.example.com/a/photo.png' ] ], $this->events, 'a HEAD, never a GET' );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function refused_urls(): array {
		return [
			'ftp'         => [ 'ftp://example.com/photo.png' ],
			'file'        => [ 'file:///etc/passwd' ],
			'no scheme'   => [ 'example.com/photo.png' ],
			'a local host' => [ 'http://127.0.0.1/photo.png' ],
		];
	}

	#[DataProvider( 'refused_urls' )]
	public function test_only_a_public_http_url_is_accepted( string $url ): void {
		$result = ( new MediaWriter() )->plan( [ 'url' => $url ] );

		$this->assertSame( 'dovira_media_url_invalid', $result->get_error_code() );
		$this->assertSame( [], $this->events, 'not even a HEAD' );
	}

	public function test_a_file_that_is_not_an_allowed_image_is_refused_at_preview(): void {
		$this->head['https://example.com/setup.exe'] = self::head( 'application/octet-stream', '5000' );
		$this->head['https://example.com/logo.svg']  = self::head( 'image/svg+xml', '500' );

		$this->assertSame( 'dovira_media_type_invalid', ( new MediaWriter() )->plan( [ 'url' => 'https://example.com/setup.exe' ] )->get_error_code() );
		$this->assertSame( 'dovira_media_type_invalid', ( new MediaWriter() )->plan( [ 'url' => 'https://example.com/logo.svg' ] )->get_error_code(), 'never SVG' );
		$this->assertSame( 'dovira_media_type_invalid', ( new MediaWriter() )->plan( [ 'url' => 'https://example.com/no-head.exe' ] )->get_error_code(), 'without a HEAD answer the extension decides' );
		$this->assertSame( 'image/png', ( new MediaWriter() )->plan( [ 'url' => 'https://example.com/no-head.png' ] )['diff']->lines()[2]['to'] );
		$this->assertNotContains( 'GET', array_column( $this->events, 0 ) );
	}

	public function test_a_declared_size_over_the_cap_is_refused_at_preview_and_the_cap_is_a_filter(): void {
		$this->head['https://example.com/big.jpg']   = self::head( 'image/jpeg', (string) ( 20 * 1024 * 1024 ) );
		$this->head['https://example.com/small.jpg'] = self::head( 'image/jpeg', '2000' );

		$this->assertSame( 'dovira_media_too_large', ( new MediaWriter() )->plan( [ 'url' => 'https://example.com/big.jpg' ] )->get_error_code() );
		$this->assertIsArray( ( new MediaWriter() )->plan( [ 'url' => 'https://example.com/small.jpg' ] ) );

		Filters\expectApplied( MediaWriter::MAX_BYTES_FILTER )->andReturn( 1000 );
		$this->assertSame( 'dovira_media_too_large', ( new MediaWriter() )->plan( [ 'url' => 'https://example.com/small.jpg' ] )->get_error_code() );
	}

	public function test_base64_is_decoded_its_type_read_from_the_bytes_and_the_bytes_never_reach_the_diff(): void {
		$plan = ( new MediaWriter() )->plan( [ 'base64' => 'data:image/png;base64,' . self::PNG, 'filename' => 'photo.jpg' ] );

		$this->assertSame( 'base64, 1 KB', $plan['diff']->lines()[0]['to'] );
		$this->assertSame( 'photo.png', $plan['diff']->lines()[1]['to'], 'the extension follows the bytes' );
		$this->assertStringNotContainsString( self::PNG, (string) json_encode( $plan['diff']->to_array() ) );
		$this->assertSame( [], $this->events, 'nothing requested' );
	}

	public function test_bad_base64_is_refused_and_an_oversized_one_before_decoding(): void {
		$this->assertSame( 'dovira_media_base64_invalid', ( new MediaWriter() )->plan( [ 'base64' => '@@@not base64@@@' ] )->get_error_code() );
		$this->assertSame( 'dovira_media_type_invalid', ( new MediaWriter() )->plan( [ 'base64' => base64_encode( 'MZ not an image' ) ] )->get_error_code() );

		Filters\expectApplied( MediaWriter::MAX_BYTES_FILTER )->andReturn( 10 );
		$this->assertSame( 'dovira_media_too_large', ( new MediaWriter() )->plan( [ 'base64' => str_repeat( '@', 100 ) ] )->get_error_code(), 'judged by its length, not decoded' );
	}

	/**
	 * @return array<string, array{array<string, mixed>}>
	 */
	public static function wrong_sources(): array {
		return [
			'neither' => [ [ 'alt' => 'x' ] ],
			'both'    => [ [ 'url' => 'https://example.com/a.png', 'base64' => self::PNG ] ],
		];
	}

	/**
	 * @param array<string, mixed> $input
	 */
	#[DataProvider( 'wrong_sources' )]
	public function test_exactly_one_source_is_given( array $input ): void {
		$this->assertSame( 'dovira_media_source_invalid', ( new MediaWriter() )->plan( $input )->get_error_code() );
	}

	public function test_the_confirmed_upload_downloads_capped_sideloads_writes_the_alt_and_cleans_up(): void {
		$this->head['https://example.com/photo.png'] = self::head( 'image/png', '' );
		$this->body                                  = (string) base64_decode( self::PNG );

		$plan  = ( new MediaWriter() )->plan( [ 'url' => 'https://example.com/photo.png', 'alt' => ' Кіт на огляді ', 'title' => 'Кіт' ] );
		$media = $plan['apply']();

		$this->assertSame( [ 'GET', 10 * 1024 * 1024 + 1 ], $this->events[1] );
		$this->assertSame( [ 'sideload', [ 'photo.png', 0, 'Кіт' ] ], $this->events[2], 'not attached unless asked' );
		$this->assertSame( [ 'meta:_wp_attachment_image_alt', [ 901, 'Кіт на огляді' ] ], $this->events[3] );
		$this->assertSame( [ 'id' => 901, 'url' => 'https://x/wp-content/uploads/photo.png', 'alt' => 'Кіт на огляді', 'mime' => 'image/png', 'width' => 1, 'height' => 1 ], $media );
		$this->assertFileDoesNotExist( $this->temps[0] );
	}

	public function test_a_download_over_the_cap_or_not_an_image_adds_nothing(): void {
		$this->head['https://example.com/photo.png'] = self::head( 'image/png', '' );

		Filters\expectApplied( MediaWriter::MAX_BYTES_FILTER )->andReturn( 50 );
		$this->body = str_repeat( 'x', 500 );
		$result     = ( new MediaWriter() )->plan( [ 'url' => 'https://example.com/photo.png' ] )['apply']();
		$this->assertSame( 'dovira_media_too_large', $result->get_error_code() );

		$this->body = 'MZ' . str_repeat( "\0", 20 );
		$result     = ( new MediaWriter() )->plan( [ 'url' => 'https://example.com/photo.png' ] )['apply']();
		$this->assertSame( 'dovira_media_type_invalid', $result->get_error_code() );

		$this->assertNotContains( 'sideload', array_column( $this->events, 0 ) );
		foreach ( $this->temps as $file ) {
			$this->assertFileDoesNotExist( $file );
		}
	}

	public function test_attach_to_is_a_content_post_and_is_passed_only_when_asked(): void {
		$plan = ( new MediaWriter() )->plan( [ 'base64' => self::PNG, 'attach_to' => 42 ] );

		$this->assertSame( [ 'id' => 42, 'title' => 'Лікар' ], $plan['diff']->lines()[ count( $plan['diff']->lines() ) - 1 ]['to'] );
		$plan['apply']();
		$this->assertSame( [ 'sideload', [ 'image.png', 42, null ] ], $this->events[0] );

		$this->assertSame( 'dovira_read_only_type', ( new MediaWriter() )->plan( [ 'base64' => self::PNG, 'attach_to' => 70 ] )->get_error_code() );
		$this->assertSame( 'dovira_post_not_found', ( new MediaWriter() )->plan( [ 'base64' => self::PNG, 'attach_to' => 99 ] )->get_error_code() );
	}

	public function test_the_ability_previews_then_dumps_uploads_and_returns_the_media_item(): void {
		$input   = [ 'base64' => self::PNG, 'alt' => 'Кіт' ];
		$preview = $this->ability()->run( $input );

		$this->assertIsString( $preview['confirmation'] );
		$this->assertSame( [], $this->events );

		$result = $this->ability()->run( $input + [ 'confirmation' => $preview['confirmation'] ] );

		$this->assertSame( [ 901 ], $result['written'] );
		$this->assertSame( 'dovira-x.sql.gz', $result['dump'] );
		$this->assertSame( 901, $result['media']['id'] );
		$this->assertSame( [ 'dump', 'sideload' ], array_slice( array_column( $this->events, 0 ), 0, 2 ) );
	}

	public function test_permission_needs_upload_files_and_edit_post_of_attach_to(): void {
		$this->assertTrue( UploadMedia::can( [ 'url' => 'https://example.com/a.png' ] ) );
		$this->assertTrue( UploadMedia::can( [ 'attach_to' => 42 ] ) );
		$this->assertTrue( UploadMedia::can( [ 'attach_to' => 99 ] ), 'a missing post hears "not found"' );

		$this->caps = [ 'upload_files' ];
		$this->assertFalse( UploadMedia::can( [ 'attach_to' => 42 ] ) );
		$this->assertTrue( UploadMedia::can( [] ) );

		$this->caps = [ 'edit_post' ];
		$this->assertFalse( UploadMedia::can( [] ) );
	}

	public function test_upload_media_is_a_write_with_the_media_result(): void {
		$args = UploadMedia::args();

		$this->assertFalse( $args['meta']['annotations']['readonly'] );
		$this->assertFalse( $args['meta']['mcp']['public'] );
		$this->assertArrayHasKey( 'confirmation', $args['input_schema']['properties'] );
		$this->assertArrayHasKey( 'media', $args['output_schema']['properties'] );
	}

	private function ability(): UploadMedia {
		return new UploadMedia(
			new MediaWriter(),
			function ( string $session ): string {
				$this->events[] = [ 'dump', $session ];
				return 'dovira-x.sql.gz';
			}
		);
	}

	/**
	 * @return array{response: array{code: int}, headers: array<string, string>}
	 */
	private static function head( string $type, string $length ): array {
		return [
			'response' => [ 'code' => 200 ],
			'headers'  => array_filter(
				[
					'content-type'   => $type,
					'content-length' => $length,
				],
				static fn( $v ) => '' !== $v
			),
		];
	}
}
