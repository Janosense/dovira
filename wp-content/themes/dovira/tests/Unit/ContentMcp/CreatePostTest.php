<?php
/**
 * Tests for dovira/create-post: a new post behind the write protocol.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\ContentMcp;

use Brain\Monkey\Functions;
use dovira\ContentMcp\CreatePost;
use dovira\ContentMcp\PostReader;
use dovira\ContentMcp\PostWriter;
use dovira\ContentMcp\Session;
use dovira\Tests\TestCase;

foreach ( [ 'Catalogue', 'Server', 'Scope', 'Session', 'Confirmation', 'Diff', 'Dump', 'DescribeSite', 'FieldValues', 'FieldInput', 'CityPrice', 'PostReader', 'PostWriter', 'WriteAbility', 'UpdatePost', 'CreatePost' ] as $dovira_class ) {
	require_once dirname( __DIR__, 3 ) . "/inc/features/content-mcp/{$dovira_class}.php";
}

/**
 * Nothing is inserted without the preview's token; the status is the
 * person's; publishing needs the type's publish capability.
 */
final class CreatePostTest extends TestCase {

	/** @var array<string, mixed> */
	private array $transients = [];

	/** @var list<string> */
	private array $events = [];

	/** @var list<string> */
	private array $caps = [ 'edit_pages', 'publish_pages' ];

	private const PAGE = [
		'post_type' => 'page',
		'title'     => 'Банк крові',
		'status'    => 'publish',
	];

	protected function setUp(): void {
		parent::setUp();
		Session::reset();

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
		Functions\when( 'current_user_can' )->alias( fn( string $cap ) => in_array( $cap, $this->caps, true ) );
		Functions\when( 'get_post_type_object' )->alias(
			static fn( string $type ) => (object) [
				'cap' => 'post' === $type
					? (object) [ 'create_posts' => 'edit_posts', 'publish_posts' => 'publish_posts' ]
					: (object) [ 'create_posts' => 'edit_pages', 'publish_posts' => 'publish_pages' ],
			]
		);
		Functions\when( 'acf_get_field_groups' )->justReturn( [] );
		Functions\when( 'wp_slash' )->returnArg();
		Functions\when( 'pll_set_post_language' )->justReturn( null );
		Functions\when( 'wp_insert_post' )->alias(
			function ( array $postarr ) {
				$this->events[] = 'insert:' . $postarr['post_status'];
				return 777;
			}
		);
	}

	public function test_without_a_token_nothing_is_inserted(): void {
		$result = $this->create()->run( self::PAGE );

		$this->assertSame( [ 'post_type', 'title', 'status' ], array_column( $result['diff'], 'field' ) );
		$this->assertIsString( $result['confirmation'] );
		$this->assertSame( [], $this->events );
	}

	public function test_the_token_dumps_then_inserts_and_the_result_carries_the_new_id(): void {
		$token = $this->create()->run( self::PAGE )['confirmation'];

		$result = $this->create()->run( self::PAGE + [ 'confirmation' => $token ] );

		$this->assertSame( [ 777 ], $result['written'] );
		$this->assertSame( [ 'dump', 'insert:publish' ], $this->events );
	}

	public function test_a_token_of_another_payload_inserts_nothing(): void {
		$token = $this->create()->run( self::PAGE )['confirmation'];

		$result = $this->create()->run( [ 'status' => 'draft' ] + self::PAGE + [ 'confirmation' => $token ] );

		$this->assertSame( 'dovira_confirmation_invalid', $result->get_error_code() );
		$this->assertSame( [], $this->events );
	}

	public function test_permission_needs_create_posts_and_publish_posts_to_publish(): void {
		$this->assertTrue( CreatePost::can( self::PAGE ) );
		$this->assertTrue( CreatePost::can( [ 'status' => 'private' ] + self::PAGE ) );

		$this->caps = [ 'edit_pages' ];
		$this->assertFalse( CreatePost::can( self::PAGE ), 'publish without publish_pages' );
		$this->assertFalse( CreatePost::can( [ 'status' => 'private' ] + self::PAGE ) );
		$this->assertTrue( CreatePost::can( [ 'status' => 'draft' ] + self::PAGE ) );

		$this->caps = [];
		$this->assertFalse( CreatePost::can( [ 'status' => 'draft' ] + self::PAGE ) );
		$this->assertFalse( CreatePost::can( [ 'post_type' => 'post', 'title' => 'x', 'status' => 'draft' ] ) );
	}

	public function test_a_type_outside_the_content_types_passes_the_gate_and_is_refused_with_a_reason(): void {
		$this->assertTrue( CreatePost::can( [ 'post_type' => 'conversation' ] ) );
		$this->assertSame( 'dovira_read_only_type', $this->create()->run( [ 'post_type' => 'conversation', 'title' => 'x', 'status' => 'draft' ] )->get_error_code() );
	}

	public function test_create_post_is_a_write_with_status_required_and_no_default(): void {
		$args   = CreatePost::args();
		$schema = $args['input_schema'];

		$this->assertFalse( $args['meta']['annotations']['readonly'] );
		$this->assertFalse( $args['meta']['mcp']['public'] );
		$this->assertSame( [ 'post_type', 'title', 'status' ], $schema['required'] );
		$this->assertArrayNotHasKey( 'default', $schema['properties']['status'] );
		$this->assertSame( [ 'uk' ], $schema['properties']['lang']['enum'] );
		$this->assertArrayNotHasKey( 'content_html', $schema['properties'] );
	}

	private function create(): CreatePost {
		return new CreatePost(
			new PostWriter( true, false, new PostReader( true, false ), static fn() => [] ),
			function ( string $session ): string {
				$this->events[] = 'dump';
				return 'dovira-x-2026-11-05-101500.sql.gz';
			}
		);
	}
}
