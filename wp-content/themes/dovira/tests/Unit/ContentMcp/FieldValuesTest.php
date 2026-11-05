<?php
/**
 * Tests for dovira\ContentMcp\FieldValues: ACF values in the connector's shape.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\ContentMcp;

use Brain\Monkey\Functions;
use dovira\ContentMcp\FieldValues;
use dovira\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/FieldValues.php';

/**
 * Whatever the return format, references come out the same way.
 */
final class FieldValuesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_get_attachment_url' )->alias( static fn( int $id ) => "https://x/{$id}.jpg" );
		Functions\when( 'get_post_meta' )->justReturn( 'alt text' );
		Functions\when( 'attachment_url_to_postid' )->alias( static fn( string $url ) => str_contains( $url, '768' ) ? 768 : 0 );
		Functions\when( 'get_the_title' )->alias( static fn( int $id ) => "Post {$id}" );
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function images(): array {
		return [
			'array' => [ [ 'ID' => 768, 'id' => 768, 'url' => 'https://x/768.jpg', 'alt' => 'alt text', 'sizes' => [] ] ],
			'id'    => [ 768 ],
			'id as string' => [ '768' ],
			'url'   => [ 'https://x/768.jpg' ],
		];
	}

	#[DataProvider( 'images' )]
	public function test_an_image_in_any_return_format_is_id_url_alt( mixed $value ): void {
		$this->assertSame(
			[ 'id' => 768, 'url' => 'https://x/768.jpg', 'alt' => 'alt text' ],
			FieldValues::value( [ 'type' => 'image' ], $value )
		);
	}

	public function test_an_empty_image_is_null(): void {
		$this->assertNull( FieldValues::value( [ 'type' => 'image' ], false ) );
		$this->assertNull( FieldValues::value( [ 'type' => 'image' ], 'https://x/unknown.jpg' ) );
	}

	public function test_a_file_is_id_url_title(): void {
		$this->assertSame(
			[ 'id' => 12, 'url' => 'https://x/12.jpg', 'title' => 'Post 12' ],
			FieldValues::value( [ 'type' => 'file' ], 12 )
		);
	}

	public function test_a_gallery_is_a_list_of_images(): void {
		$this->assertSame(
			[ [ 'id' => 768, 'url' => 'https://x/768.jpg', 'alt' => 'alt text' ] ],
			FieldValues::value( [ 'type' => 'gallery' ], [ 768, false ] )
		);
	}

	public function test_a_post_object_is_id_title_and_a_relationship_a_list(): void {
		$post = (object) [ 'ID' => 5, 'post_title' => 'Терапія' ];

		$this->assertSame( [ 'id' => 5, 'title' => 'Терапія' ], FieldValues::value( [ 'type' => 'post_object' ], $post ) );
		$this->assertSame( [ 'id' => 9, 'title' => 'Post 9' ], FieldValues::value( [ 'type' => 'post_object' ], 9 ) );
		$this->assertNull( FieldValues::value( [ 'type' => 'post_object' ], '' ) );
		$this->assertSame(
			[ [ 'id' => 5, 'title' => 'Терапія' ], [ 'id' => 9, 'title' => 'Post 9' ] ],
			FieldValues::value( [ 'type' => 'relationship' ], [ $post, 9 ] )
		);
		$this->assertSame( [], FieldValues::value( [ 'type' => 'relationship' ], false ) );
	}

	public function test_a_taxonomy_value_is_a_list_of_id_name(): void {
		Functions\when( 'get_term' )->justReturn( (object) [ 'term_id' => 3, 'name' => 'Акції' ] );

		$this->assertSame( [ [ 'id' => 3, 'name' => 'Акції' ] ], FieldValues::value( [ 'type' => 'taxonomy' ], 3 ) );
	}

	public function test_repeaters_and_groups_normalize_their_sub_fields(): void {
		$field = [
			'type'       => 'repeater',
			'sub_fields' => [
				[ 'name' => 'photo', 'type' => 'image' ],
				[
					'name'       => 'links',
					'type'       => 'group',
					'sub_fields' => [ [ 'name' => 'page', 'type' => 'post_object' ] ],
				],
				[ 'name' => 'title', 'type' => 'text' ],
			],
		];

		$this->assertSame(
			[
				[
					'photo' => [ 'id' => 768, 'url' => 'https://x/768.jpg', 'alt' => 'alt text' ],
					'links' => [ 'page' => [ 'id' => 9, 'title' => 'Post 9' ] ],
					'title' => 'Рядок',
				],
			],
			FieldValues::value( $field, [ [ 'photo' => 768, 'links' => [ 'page' => 9 ], 'title' => 'Рядок' ] ] )
		);
		$this->assertSame( [], FieldValues::value( $field, false ) );
	}

	public function test_other_types_pass_through(): void {
		$link = [ 'title' => 'Докладніше', 'url' => 'https://x/', 'target' => '' ];

		$this->assertSame( $link, FieldValues::value( [ 'type' => 'link' ], $link ) );
		$this->assertTrue( FieldValues::value( [ 'type' => 'true_false' ], true ) );
		$this->assertSame( '#000000', FieldValues::value( [ 'type' => 'color_picker' ], '#000000' ) );
	}

	public function test_from_objects_keys_values_by_field_name(): void {
		$this->assertSame(
			[ 'heading' => 'Про нас', 'image' => null ],
			FieldValues::from_objects(
				[
					'heading' => [ 'name' => 'heading', 'type' => 'text', 'value' => 'Про нас' ],
					'image'   => [ 'name' => 'image', 'type' => 'image', 'value' => false ],
				]
			)
		);
	}
}
