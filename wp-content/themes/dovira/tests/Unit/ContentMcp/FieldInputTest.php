<?php
/**
 * Tests for dovira\ContentMcp\FieldInput: ACF values from the connector's
 * shape back to what ACF stores.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\ContentMcp;

use Brain\Monkey\Functions;
use dovira\ContentMcp\FieldInput;
use dovira\ContentMcp\FieldValues;
use dovira\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/FieldValues.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/FieldInput.php';

/**
 * What get-post shows can be passed back unchanged and stores the same values.
 */
final class FieldInputTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_get_attachment_url' )->alias( static fn( int $id ) => "https://x/{$id}.jpg" );
		Functions\when( 'get_post_meta' )->justReturn( 'alt' );
		Functions\when( 'get_the_title' )->alias( static fn( int $id ) => "Post {$id}" );
		Functions\when( 'get_term' )->alias( static fn( int $id ) => (object) [ 'term_id' => $id, 'name' => "Term {$id}" ] );
		Functions\when( 'attachment_url_to_postid' )->justReturn( 0 );
	}

	/**
	 * @return array<string, array{array<string, mixed>, mixed}>
	 */
	public static function stored(): array {
		$repeater = [
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

		return [
			'image'             => [ [ 'type' => 'image' ], 768 ],
			'empty image'       => [ [ 'type' => 'image' ], '' ],
			'file'              => [ [ 'type' => 'file' ], 12 ],
			'gallery'           => [ [ 'type' => 'gallery' ], [ 768, 769 ] ],
			'post object'       => [ [ 'type' => 'post_object' ], 9 ],
			'post object multi' => [ [ 'type' => 'post_object', 'multiple' => 1 ], [ 9, 10 ] ],
			'relationship'      => [ [ 'type' => 'relationship' ], [ 5, 9 ] ],
			'taxonomy'          => [ [ 'type' => 'taxonomy' ], [ 3 ] ],
			'repeater'          => [ $repeater, [ [ 'photo' => 768, 'links' => [ 'page' => 9 ], 'title' => 'Рядок' ] ] ],
			'text'              => [ [ 'type' => 'text' ], 'Про нас' ],
			'true/false'        => [ [ 'type' => 'true_false' ], false ],
			'link'              => [ [ 'type' => 'link' ], [ 'title' => 'Докладніше', 'url' => 'https://x/', 'target' => '' ] ],
		];
	}

	/**
	 * @param array<string, mixed> $field
	 */
	#[DataProvider( 'stored' )]
	public function test_what_the_reader_shows_converts_back_to_what_acf_stores( array $field, mixed $stored ): void {
		$this->assertSame( $stored, FieldInput::value( $field, FieldValues::value( $field, $stored ) ) );
	}

	public function test_bare_ids_are_accepted_too(): void {
		$this->assertSame( 768, FieldInput::value( [ 'type' => 'image' ], '768' ) );
		$this->assertSame( [ 3, 4 ], FieldInput::value( [ 'type' => 'taxonomy' ], [ [ 'id' => 3, 'name' => 'Акції' ], 4 ] ) );
		$this->assertSame( [], FieldInput::value( [ 'type' => 'relationship' ], null ) );
		$this->assertSame( [], FieldInput::value( [ 'type' => 'repeater' ], 'x' ) );
	}

	public function test_keyed_turns_names_into_keys_and_converts_values(): void {
		$fields = [
			[ 'key' => 'field_hero_heading', 'name' => 'heading', 'type' => 'text' ],
			[ 'key' => 'field_hero_image', 'name' => 'image', 'type' => 'image' ],
			[ 'key' => 'field_hero_tab', 'name' => '', 'type' => 'tab' ],
		];

		$this->assertSame(
			[ 'field_hero_heading' => 'Привіт', 'field_hero_image' => 5 ],
			FieldInput::keyed( $fields, [ 'heading' => 'Привіт', 'image' => [ 'id' => 5, 'url' => 'u', 'alt' => '' ] ] )
		);
	}

	public function test_keyed_refuses_an_unknown_name(): void {
		$result = FieldInput::keyed( [ [ 'key' => 'field_a', 'name' => 'heading', 'type' => 'text' ] ], [ 'title' => 'x' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'dovira_unknown_field', $result->get_error_code() );
		$this->assertStringContainsString( 'title', $result->get_error_message() );
	}
}
