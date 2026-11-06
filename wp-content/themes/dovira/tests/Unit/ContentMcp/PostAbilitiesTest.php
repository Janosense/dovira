<?php
/**
 * Tests for dovira/update-post and dovira/trash-post: the post write
 * abilities behind the write protocol.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\ContentMcp;

use Brain\Monkey\Functions;
use dovira\ContentMcp\PostReader;
use dovira\ContentMcp\PostWriter;
use dovira\ContentMcp\Session;
use dovira\ContentMcp\TrashPost;
use dovira\ContentMcp\UpdatePost;
use dovira\Tests\TestCase;

foreach ( [ 'Catalogue', 'Server', 'Scope', 'Session', 'Confirmation', 'Diff', 'Dump', 'DescribeSite', 'FieldValues', 'FieldInput', 'CityPrice', 'PostReader', 'PostWriter', 'WriteAbility', 'UpdatePost', 'TrashPost' ] as $dovira_class ) {
	require_once dirname( __DIR__, 3 ) . "/inc/features/content-mcp/{$dovira_class}.php";
}

/**
 * Records are never written; a translation is written only when named; no
 * write without the preview's token; trashing is for administrators only.
 */
final class PostAbilitiesTest extends TestCase {

	private const UK = 176;
	private const RU = 1700;

	/** @var array<string, mixed> */
	private array $transients = [];

	/** @var list<string> */
	private array $events = [];

	/** @var list<string> */
	private array $caps = [ 'edit_post', 'delete_post', 'manage_options' ];

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
		Functions\when( 'get_post' )->alias(
			static fn( int $id ) => match ( $id ) {
				self::UK  => self::post( self::UK, 'page' ),
				self::RU  => self::post( self::RU, 'page' ),
				5         => self::post( 5, 'page' ),
				900       => self::post( 900, 'conversation' ),
				950       => self::post( 950, 'attachment' ),
				960       => self::post( 960, 'page', 'trash' ),
				default   => null,
			}
		);
		Functions\when( 'pll_is_translated_post_type' )->justReturn( true );
		Functions\when( 'pll_get_post_language' )->alias( static fn( int $id ) => self::RU === $id ? 'ru' : 'uk' );
		Functions\when( 'pll_get_post_translations' )->alias( static fn( int $id ) => in_array( $id, [ self::UK, self::RU ], true ) ? [ 'uk' => self::UK, 'ru' => self::RU ] : [] );
		Functions\when( 'wp_slash' )->returnArg();
		Functions\when( 'wp_update_post' )->alias(
			function ( array $postarr ) {
				$this->events[] = 'update:' . $postarr['ID'];
				return $postarr['ID'];
			}
		);
	}

	public function test_update_post_previews_without_a_token_and_writes_nothing(): void {
		$result = $this->update()->run( [ 'id' => self::UK, 'title' => 'Про нас' ] );

		$this->assertSame( 'title', $result['diff'][0]['field'] );
		$this->assertIsString( $result['confirmation'] );
		$this->assertSame( [], $this->events );
	}

	public function test_update_post_writes_with_the_token_and_refuses_another_payloads(): void {
		$token = $this->update()->run( [ 'id' => self::UK, 'title' => 'Про нас' ] )['confirmation'];

		$other = $this->update()->run( [ 'id' => self::UK, 'title' => 'Про нас!', 'confirmation' => $token ] );
		$this->assertSame( 'dovira_confirmation_invalid', $other->get_error_code() );
		$this->assertSame( [], $this->events );

		$result = $this->update()->run( [ 'id' => self::UK, 'title' => 'Про нас', 'confirmation' => $token ] );
		$this->assertSame( [ self::UK ], $result['written'] );
		$this->assertSame( [ 'dump', 'update:' . self::UK ], $this->events );
	}

	public function test_the_translation_is_untouched_unless_named_and_both_are_written_when_it_is(): void {
		$alone = $this->update()->run( [ 'id' => self::UK, 'title' => 'Про нас' ] );
		$this->assertSame( [ self::UK ], array_column( array_column( $alone['diff'], 'target' ), 'id' ) );

		$input = [ 'id' => self::UK, 'title' => 'Про нас', 'translation' => [ 'id' => self::RU, 'title' => 'О нас' ] ];
		$both  = $this->update()->run( $input );
		$this->assertSame( [ [ 'id' => self::UK, 'lang' => 'uk' ], [ 'id' => self::RU, 'lang' => 'ru' ] ], array_column( $both['diff'], 'target' ) );

		$result = $this->update()->run( $input + [ 'confirmation' => $both['confirmation'] ] );
		$this->assertSame( [ self::UK, self::RU ], $result['written'] );
		$this->assertSame( [ 'dump', 'update:' . self::UK, 'update:' . self::RU ], $this->events );
	}

	public function test_a_translation_id_that_is_not_the_posts_translation_is_refused(): void {
		$result = $this->update()->run( [ 'id' => self::UK, 'title' => 'x', 'translation' => [ 'id' => 5, 'title' => 'y' ] ] );

		$this->assertSame( 'dovira_not_a_translation', $result->get_error_code() );
		$this->assertSame( [], $this->transients );
	}

	/**
	 * @return array<string, array{int, string}>
	 */
	public static function refused_posts(): array {
		return [
			'a record'           => [ 900, 'dovira_read_only_type' ],
			'a type out of scope' => [ 950, 'dovira_type_out_of_scope' ],
			'a missing post'     => [ 404, 'dovira_post_not_found' ],
			'a trashed post'     => [ 960, 'dovira_post_in_trash' ],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'refused_posts' )]
	public function test_update_post_refuses_before_any_token( int $id, string $code ): void {
		$this->assertSame( $code, $this->update()->run( [ 'id' => $id, 'title' => 'x' ] )->get_error_code() );
		$this->assertSame( [], $this->transients );
	}

	public function test_update_post_needs_edit_post_of_every_named_post(): void {
		$this->assertTrue( UpdatePost::can( [ 'id' => self::UK, 'translation' => [ 'id' => self::RU ] ] ) );
		$this->assertTrue( UpdatePost::can( [ 'id' => 404 ] ), 'a missing post is reported as not found' );

		$this->caps = [];
		$this->assertFalse( UpdatePost::can( [ 'id' => self::UK ] ) );
		$this->assertFalse( UpdatePost::can( [ 'id' => 404, 'translation' => [ 'id' => self::RU ] ] ) );
	}

	public function test_update_post_is_a_write_with_the_confirmation_input(): void {
		$args = UpdatePost::args();

		$this->assertFalse( $args['meta']['annotations']['readonly'] );
		$this->assertFalse( $args['meta']['mcp']['public'] );
		$this->assertArrayHasKey( 'confirmation', $args['input_schema']['properties'] );
		$this->assertArrayHasKey( 'blocks', $args['input_schema']['properties']['translation']['properties'] );
		$this->assertArrayNotHasKey( 'content_html', $args['input_schema']['properties'] );
	}

	public function test_trash_post_needs_manage_options_and_delete_post(): void {
		$this->assertTrue( TrashPost::can( [ 'id' => self::UK, 'translation_id' => self::RU ] ) );

		$this->caps = [ 'edit_post', 'delete_post' ];
		$this->assertFalse( TrashPost::can( [ 'id' => self::UK ] ), 'an editor' );

		$this->caps = [ 'manage_options' ];
		$this->assertFalse( TrashPost::can( [ 'id' => self::UK ] ) );
	}

	public function test_trash_post_previews_the_post_and_warns_that_the_translation_stays(): void {
		Functions\expect( 'wp_trash_post' )->never();

		$result = $this->trash()->run( [ 'id' => self::UK ] );

		$this->assertSame( [ [ 'target' => [ 'id' => self::UK, 'lang' => 'uk' ], 'field' => 'status', 'from' => 'publish', 'to' => 'trash' ] ], $result['diff'] );
		$this->assertStringContainsString( (string) self::RU, $result['warnings'][0] );
		$this->assertIsString( $result['confirmation'] );
	}

	public function test_trash_post_moves_to_the_trash_and_never_deletes(): void {
		Functions\expect( 'wp_delete_post' )->never();
		Functions\when( 'wp_trash_post' )->alias(
			function ( int $id ) {
				$this->events[] = "trash:{$id}";
				return self::post( $id, 'page', 'trash' );
			}
		);

		$input  = [ 'id' => self::UK, 'translation_id' => self::RU ];
		$token  = $this->trash()->run( $input )['confirmation'];
		$result = $this->trash()->run( $input + [ 'confirmation' => $token ] );

		$this->assertSame( [ self::UK, self::RU ], $result['written'] );
		$this->assertSame( [], $result['warnings'] );
		$this->assertSame( [ 'dump', 'trash:' . self::UK, 'trash:' . self::RU ], $this->events );
	}

	/**
	 * @return array<string, array{array<string, int>, string}>
	 */
	public static function refused_trash(): array {
		return [
			'a record'            => [ [ 'id' => 900 ], 'dovira_read_only_type' ],
			'already in the trash' => [ [ 'id' => 960 ], 'dovira_post_in_trash' ],
			'not its translation' => [ [ 'id' => self::UK, 'translation_id' => 5 ], 'dovira_not_a_translation' ],
			'a missing post'      => [ [ 'id' => 404 ], 'dovira_post_not_found' ],
		];
	}

	/**
	 * @param array<string, int> $input
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'refused_trash' )]
	public function test_trash_post_refuses_before_any_token( array $input, string $code ): void {
		$this->assertSame( $code, $this->trash()->run( $input )->get_error_code() );
		$this->assertSame( [], $this->transients );
	}

	public function test_trash_post_is_a_write(): void {
		$args = TrashPost::args();

		$this->assertFalse( $args['meta']['annotations']['readonly'] );
		$this->assertTrue( $args['meta']['annotations']['destructive'] );
		$this->assertArrayHasKey( 'confirmation', $args['input_schema']['properties'] );
	}

	private function trash(): TrashPost {
		return new TrashPost(
			function ( string $session ): string {
				$this->events[] = 'dump';
				return 'dovira-x-2026-11-05-101500.sql.gz';
			},
			true
		);
	}

	private function update(): UpdatePost {
		return new UpdatePost(
			new PostWriter( true, true, new PostReader( true, true ), static fn() => [] ),
			function ( string $session ): string {
				$this->events[] = 'dump';
				return 'dovira-x-2026-11-05-101500.sql.gz';
			},
			true
		);
	}

	private static function post( int $id, string $type, string $status = 'publish' ): object {
		return (object) [
			'ID'           => $id,
			'post_type'    => $type,
			'post_status'  => $status,
			'post_title'   => 'Сторінка',
			'post_name'    => 'storinka',
			'post_parent'  => 0,
			'menu_order'   => 0,
			'post_content' => '',
		];
	}
}
