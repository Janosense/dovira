<?php
/**
 * Tests for the read abilities: who may read what, and paging.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\ContentMcp;

use Brain\Monkey\Functions;
use dovira\ContentMcp\Abilities;
use dovira\ContentMcp\CreatePost;
use dovira\ContentMcp\CreateTerm;
use dovira\ContentMcp\CreateTranslation;
use dovira\ContentMcp\FindPriceRows;
use dovira\ContentMcp\GetPost;
use dovira\ContentMcp\ListMedia;
use dovira\ContentMcp\ListPosts;
use dovira\ContentMcp\ListTerms;
use dovira\ContentMcp\PostReader;
use dovira\ContentMcp\ReadTools;
use dovira\ContentMcp\Scope;
use dovira\ContentMcp\TrashPost;
use dovira\ContentMcp\UpdatePost;
use dovira\ContentMcp\UpdatePriceRow;
use dovira\ContentMcp\UpdateTerm;
use dovira\ContentMcp\UploadMedia;
use dovira\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

foreach ( [ 'Catalogue', 'Scope', 'Schema', 'FieldValues', 'CityPrice', 'PostReader', 'ReadTools', 'DescribeSite', 'ListPosts', 'GetPost', 'ListTerms', 'ListMedia', 'FindPriceRows', 'Server', 'Session', 'Confirmation', 'Diff', 'Dump', 'PriceRows', 'FieldInput', 'PostWriter', 'WriteAbility', 'UpdatePriceRow', 'UpdatePost', 'CreatePost', 'Translator', 'CreateTranslation', 'MediaWriter', 'UploadMedia', 'TrashPost', 'TermWriter', 'CreateTerm', 'UpdateTerm', 'Abilities' ] as $dovira_class ) {
	require_once dirname( __DIR__, 3 ) . "/inc/features/content-mcp/{$dovira_class}.php";
}

/**
 * An editor reads content but never the form records; an administrator reads
 * both; nothing outside the scope is readable; every list is capped.
 */
final class ReadAbilitiesTest extends TestCase {

	/** Capabilities of the connected user in the current test. */
	private array $caps = [];

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'get_post_type_object' )->alias(
			static function ( string $type ) {
				$base = in_array( $type, Scope::RECORDS, true ) ? 'conversations' : ( in_array( $type, [ 'post' ], true ) ? 'posts' : 'pages' );
				return (object) [
					'cap' => (object) [
						'edit_posts'        => "edit_{$base}",
						'edit_others_posts' => "edit_others_{$base}",
					],
				];
			}
		);
		Functions\when( 'current_user_can' )->alias( fn( string $cap ) => in_array( $cap, $this->caps, true ) );
	}

	/**
	 * @return array<string, array{list<string>}>
	 */
	public static function roles(): array {
		return [
			'editor'        => [ [ 'read', 'edit_pages', 'edit_others_pages', 'edit_posts', 'edit_others_posts', 'upload_files' ] ],
			'administrator' => [ [ 'read', 'edit_pages', 'edit_others_pages', 'edit_posts', 'edit_others_posts', 'upload_files', 'edit_conversations', 'edit_others_conversations', 'manage_options' ] ],
		];
	}

	/**
	 * @param list<string> $caps
	 */
	#[DataProvider( 'roles' )]
	public function test_records_are_readable_only_with_edit_others_conversations( array $caps ): void {
		$this->caps = $caps;
		$admin      = in_array( 'manage_options', $caps, true );

		foreach ( Scope::RECORDS as $type ) {
			$this->assertSame( $admin, ListPosts::can( [ 'type' => $type ] ), "list-posts {$type}" );

			Functions\when( 'get_post' )->justReturn( (object) [ 'post_type' => $type ] );
			$this->assertSame( $admin, GetPost::can( [ 'id' => 5 ] ), "get-post {$type}" );
		}
	}

	/**
	 * @param list<string> $caps
	 */
	#[DataProvider( 'roles' )]
	public function test_content_is_readable_by_editors_and_administrators( array $caps ): void {
		$this->caps = $caps;

		foreach ( Scope::CONTENT as $type ) {
			$this->assertTrue( ListPosts::can( [ 'type' => $type ] ), "list-posts {$type}" );

			Functions\when( 'get_post' )->justReturn( (object) [ 'post_type' => $type ] );
			$this->assertTrue( GetPost::can( [ 'id' => 5 ] ), "get-post {$type}" );
		}
	}

	public function test_a_published_record_is_refused_to_an_editor_although_read_post_would_allow_it(): void {
		// Core maps read_post of a published post to plain `read`; the gate does not use it.
		$this->caps = [ 'read', 'edit_pages', 'edit_posts' ];
		Functions\when( 'get_post' )->justReturn( (object) [ 'post_type' => 'conversation', 'post_status' => 'publish' ] );

		$this->assertFalse( GetPost::can( [ 'id' => 1730 ] ) );
	}

	public function test_types_outside_the_scope_are_refused_even_to_an_administrator(): void {
		$this->caps = self::roles()['administrator'][0];

		$this->assertFalse( ListPosts::can( [ 'type' => 'attachment' ] ) );
		$this->assertFalse( ListPosts::can( [] ) );

		Functions\when( 'get_post' )->justReturn( (object) [ 'post_type' => 'revision' ] );
		$this->assertFalse( GetPost::can( [ 'id' => 5 ] ) );
	}

	public function test_a_missing_post_passes_the_gate_and_is_reported_not_found(): void {
		Functions\when( 'get_post' )->justReturn( null );

		$this->assertTrue( GetPost::can( [ 'id' => 999999 ] ) );
		$result = ( new GetPost() )->execute( [ 'id' => 999999 ] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'dovira_post_not_found', $result->get_error_code() );
	}

	public function test_get_post_reads_through_the_post_reader(): void {
		$post = (object) [ 'ID' => 18, 'post_type' => 'service', 'post_status' => 'publish', 'post_title' => 'Т', 'post_name' => 't', 'post_parent' => 0, 'menu_order' => 0, 'post_modified' => '', 'post_content' => '' ];
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_object_taxonomies' )->justReturn( [] );
		Functions\when( 'is_post_type_viewable' )->justReturn( true );
		Functions\when( 'get_permalink' )->justReturn( 'https://dovira.vet/services/t/' );

		$read = ( new GetPost() )->execute( [ 'id' => 18 ], new PostReader( false, false ) );

		$this->assertSame( 18, $read['id'] );
		$this->assertSame( 'https://dovira.vet/services/t/', $read['link'] );
	}

	public function test_find_price_rows_needs_the_services_edit_posts(): void {
		$this->caps = [ 'read' ];
		$this->assertFalse( FindPriceRows::can() );

		$this->caps = [ 'read', 'edit_pages' ];
		$this->assertTrue( FindPriceRows::can() );
	}

	public function test_list_media_needs_upload_files(): void {
		$this->caps = [ 'read' ];
		$this->assertFalse( ListMedia::can() );

		$this->caps = [ 'read', 'upload_files' ];
		$this->assertTrue( ListMedia::can() );
	}

	public function test_list_terms_needs_the_taxonomys_assign_terms_and_a_taxonomy_in_scope(): void {
		Functions\when( 'get_taxonomy' )->justReturn( (object) [ 'cap' => (object) [ 'assign_terms' => 'edit_posts' ] ] );

		$this->caps = [ 'read' ];
		$this->assertFalse( ListTerms::can( [ 'taxonomy' => 'category' ] ) );

		$this->caps = [ 'read', 'edit_posts' ];
		$this->assertTrue( ListTerms::can( [ 'taxonomy' => 'service-city' ] ) );
		$this->assertFalse( ListTerms::can( [ 'taxonomy' => 'nav_menu' ] ) );
	}

	/**
	 * @return array<string, array{array<string, mixed>, int, int}>
	 */
	public static function paging(): array {
		return [
			'defaults'       => [ [], 1, 20 ],
			'over the cap'   => [ [ 'per_page' => 500 ], 1, 50 ],
			'below one'      => [ [ 'page' => 0, 'per_page' => 0 ], 1, 1 ],
			'a later page'   => [ [ 'page' => 3, 'per_page' => 10 ], 3, 10 ],
		];
	}

	/**
	 * @param array<string, mixed> $input
	 */
	#[DataProvider( 'paging' )]
	public function test_every_list_is_paginated_and_capped( array $input, int $page, int $per_page ): void {
		$this->assertSame( [ $page, $per_page ], ReadTools::paging( $input ) );
	}

	public function test_list_posts_caps_a_direct_call_and_builds_the_query(): void {
		Functions\when( 'pll_is_translated_post_type' )->justReturn( true );
		$asked = [];
		$query = static function ( array $args ) use ( &$asked ): array {
			$asked = $args;
			return [ [], 0 ];
		};

		$result = ( new ListPosts( $query, new PostReader( false, false ), true ) )->execute(
			[ 'type' => 'post', 'per_page' => 500, 'search' => 'вакцинація', 'lang' => 'all', 'terms' => [ 'category' => [ '3' ], 'nav_menu' => [ 1 ] ] ]
		);

		$this->assertSame( 50, $asked['posts_per_page'] );
		$this->assertSame( 50, $result['per_page'] );
		$this->assertSame( 'any', $asked['post_status'] );
		$this->assertSame( 'вакцинація', $asked['s'] );
		$this->assertSame( '', $asked['lang'], 'all languages' );
		$this->assertSame( [ [ 'taxonomy' => 'category', 'field' => 'term_id', 'terms' => [ 3 ] ] ], $asked['tax_query'] );
	}

	public function test_list_posts_defaults_to_ukrainian_and_ignores_language_for_records(): void {
		Functions\when( 'pll_is_translated_post_type' )->alias( static fn( string $type ) => ! in_array( $type, Scope::RECORDS, true ) );
		$list = new ListPosts( static fn() => [ [], 0 ], new PostReader( false, false ), true );

		$this->assertSame( 'uk', $list->query_args( [ 'type' => 'service' ] )['lang'] );
		$this->assertArrayNotHasKey( 'lang', $list->query_args( [ 'type' => 'conversation', 'lang' => 'ru' ] ) );
	}

	public function test_list_posts_items_carry_language_and_a_link_only_for_viewable_types(): void {
		Functions\when( 'is_post_type_viewable' )->alias( static fn( string $type ) => 'conversation' !== $type );
		Functions\when( 'get_permalink' )->justReturn( 'https://dovira.vet/x/' );
		$posts = [
			(object) [ 'ID' => 1, 'post_type' => 'conversation', 'post_status' => 'publish', 'post_title' => 'Олена', 'post_modified' => 'm' ],
		];

		$result = ( new ListPosts( static fn() => [ $posts, 89 ], new PostReader( false, false ), false ) )->execute( [ 'type' => 'conversation' ] );

		$this->assertSame( 89, $result['total'] );
		$this->assertNull( $result['items'][0]['link'] );
		$this->assertNull( $result['items'][0]['lang'] );
	}

	public function test_list_media_filters_by_mime_and_shapes_items(): void {
		Functions\when( 'wp_get_attachment_metadata' )->justReturn( [ 'width' => 800, 'height' => 600 ] );
		Functions\when( 'wp_get_attachment_url' )->justReturn( 'https://dovira.vet/a.jpg' );
		Functions\when( 'get_post_meta' )->justReturn( 'Кіт' );
		$asked = [];
		$query = static function ( array $args ) use ( &$asked ): array {
			$asked = $args;
			return [ [ (object) [ 'ID' => 7, 'post_title' => 'a', 'post_mime_type' => 'image/jpeg', 'post_date' => 'd' ] ], 1 ];
		};

		$result = ( new ListMedia( $query ) )->execute( [ 'mime' => 'image', 'per_page' => 99 ] );

		$this->assertSame( 'image', $asked['post_mime_type'] );
		$this->assertSame( 50, $asked['posts_per_page'] );
		$this->assertSame( [ 'id' => 7, 'title' => 'a', 'url' => 'https://dovira.vet/a.jpg', 'alt' => 'Кіт', 'mime' => 'image/jpeg', 'width' => 800, 'height' => 600, 'date' => 'd' ], $result['items'][0] );
	}

	public function test_list_terms_pages_with_offset_and_counts_the_total(): void {
		$asked = [];
		Functions\when( 'get_terms' )->alias(
			static function ( array $args ) use ( &$asked ) {
				$asked = $args;
				return [];
			}
		);
		Functions\when( 'wp_count_terms' )->justReturn( '12' );

		$result = ( new ListTerms( false ) )->execute( [ 'taxonomy' => 'post_tag', 'page' => 2, 'per_page' => 5 ] );

		$this->assertSame( 5, $asked['number'] );
		$this->assertSame( 5, $asked['offset'] );
		$this->assertSame( 12, $result['total'] );
	}

	public function test_every_catalogued_read_ability_is_readonly_with_an_output_schema(): void {
		foreach ( array_diff_key( Abilities::definitions(), array_flip( [ UpdatePriceRow::NAME, UpdatePost::NAME, CreatePost::NAME, CreateTranslation::NAME, UploadMedia::NAME, TrashPost::NAME, CreateTerm::NAME, UpdateTerm::NAME ] ) ) as $name => $args ) {
			$this->assertTrue( $args['meta']['annotations']['readonly'], $name );
			$this->assertFalse( $args['meta']['mcp']['public'], $name );
			$this->assertNotEmpty( $args['output_schema'], $name );
		}
		$this->assertSame( ReadTools::PER_PAGE_MAX, ListPosts::args()['input_schema']['properties']['per_page']['maximum'] );
	}

	public function test_every_catalogued_list_caps_per_page_at_fifty(): void {
		$lists = [];
		foreach ( Abilities::definitions() as $name => $args ) {
			$output = (array) ( $args['output_schema']['properties'] ?? [] );
			if ( isset( $output['items'], $output['per_page'] ) ) {
				$lists[] = $name;
				$this->assertSame( 50, $args['input_schema']['properties']['per_page']['maximum'] ?? null, $name );
			}
		}

		$this->assertSame( [ ListPosts::NAME, FindPriceRows::NAME, ListTerms::NAME, ListMedia::NAME ], $lists );
	}
}
