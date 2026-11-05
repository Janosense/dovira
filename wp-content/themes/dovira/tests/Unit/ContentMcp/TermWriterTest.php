<?php
/**
 * Tests for dovira\ContentMcp\TermWriter and dovira/create-term,
 * dovira/update-term: blog categories and tags only.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\ContentMcp;

use Brain\Monkey\Functions;
use dovira\ContentMcp\CreateTerm;
use dovira\ContentMcp\TermWriter;
use dovira\ContentMcp\UpdateTerm;
use dovira\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

foreach ( [ 'Catalogue', 'Server', 'Scope', 'Session', 'Confirmation', 'Diff', 'Dump', 'DescribeSite', 'FieldValues', 'FieldInput', 'CityPrice', 'PostReader', 'PostWriter', 'WriteAbility', 'TermWriter', 'CreateTerm', 'UpdateTerm' ] as $dovira_class ) {
	require_once dirname( __DIR__, 3 ) . "/inc/features/content-mcp/{$dovira_class}.php";
}

/**
 * `service-city` is never written; a parent only for categories; no duplicate;
 * a new term is Ukrainian; the taxonomy's capabilities gate it.
 */
final class TermWriterTest extends TestCase {

	/** @var list<array{0: string, 1: mixed}> */
	private array $writes = [];

	/** @var list<string> */
	private array $caps = [ 'manage_categories', 'edit_categories', 'manage_post_tags', 'edit_post_tags' ];

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'sanitize_title' )->alias( static fn( string $t ) => strtolower( (string) preg_replace( '/[^a-z0-9]+/i', '-', $t ) ) );
		Functions\when( 'pll_is_translated_taxonomy' )->justReturn( true );
		Functions\when( 'pll_get_term_language' )->alias( static fn( int $id ) => 9 === $id ? 'ru' : 'uk' );
		Functions\when( 'get_terms' )->alias( static fn( array $args ) => 'Новини' === $args['name'] ? [ (object) [ 'term_id' => 1 ] ] : [] );
		Functions\when( 'get_term' )->alias(
			static fn( int $id ) => match ( $id ) {
				1       => (object) [ 'term_id' => 1, 'taxonomy' => 'category', 'name' => 'Новини', 'slug' => 'novyny', 'description' => '', 'parent' => 0 ],
				2       => (object) [ 'term_id' => 2, 'taxonomy' => 'category', 'name' => 'Підрубрика', 'slug' => 'pidrubryka', 'description' => '', 'parent' => 1 ],
				9       => (object) [ 'term_id' => 9, 'taxonomy' => 'category', 'name' => 'Новости', 'slug' => 'novosti', 'description' => '', 'parent' => 0 ],
				4       => (object) [ 'term_id' => 4, 'taxonomy' => 'service-city', 'name' => 'Харків', 'slug' => 'kharkiv', 'description' => '', 'parent' => 0 ],
				default => null,
			}
		);
		Functions\when( 'get_ancestors' )->alias( static fn( int $id ) => 2 === $id ? [ 1 ] : [] );
		Functions\when( 'wp_insert_term' )->alias(
			function ( string $name, string $tax, array $args ) {
				$this->writes[] = [ 'insert', [ $name, $tax, $args ] ];
				return [ 'term_id' => 80, 'term_taxonomy_id' => 80 ];
			}
		);
		Functions\when( 'pll_set_term_language' )->alias(
			function ( int $id, string $lang ): void {
				$this->writes[] = [ 'lang', [ $id, $lang ] ];
			}
		);
		Functions\when( 'wp_update_term' )->alias(
			function ( int $id, string $tax, array $args ) {
				$this->writes[] = [ 'update', [ $id, $tax, $args ] ];
				return [ 'term_id' => $id ];
			}
		);
		Functions\when( 'current_user_can' )->alias( fn( string $cap ) => in_array( $cap, $this->caps, true ) );
		Functions\when( 'get_taxonomy' )->alias(
			static fn( string $tax ) => (object) [
				'cap' => 'post_tag' === $tax
					? (object) [ 'manage_terms' => 'manage_post_tags', 'edit_terms' => 'edit_post_tags' ]
					: (object) [ 'manage_terms' => 'manage_categories', 'edit_terms' => 'edit_categories' ],
			]
		);
	}

	public function test_a_new_category_is_previewed_and_created_in_ukrainian(): void {
		$plan = ( new TermWriter( true ) )->create( 'category', [ 'name' => 'Акції', 'slug' => 'aktsii', 'parent' => 1 ] );

		$this->assertSame( [ 'taxonomy', 'name', 'slug', 'parent' ], array_column( $plan['diff']->lines(), 'field' ) );
		$this->assertSame( [ 'id' => null, 'lang' => 'uk' ], $plan['diff']->lines()[0]['target'] );
		$this->assertSame( [], $this->writes );

		$this->assertSame( 80, $plan['apply']() );
		$this->assertSame(
			[
				[ 'insert', [ 'Акції', 'category', [ 'slug' => 'aktsii', 'parent' => 1 ] ] ],
				[ 'lang', [ 80, 'uk' ] ],
			],
			$this->writes
		);
	}

	public function test_a_tag_of_an_untranslated_taxonomy_gets_no_language(): void {
		Functions\when( 'pll_is_translated_taxonomy' )->justReturn( false );

		( new TermWriter( true ) )->create( 'post_tag', [ 'name' => 'вакцинація' ] )['apply']();

		$this->assertSame( [ [ 'insert', [ 'вакцинація', 'post_tag', [] ] ] ], $this->writes );
	}

	/**
	 * @return array<string, array{string, array<string, mixed>, string}>
	 */
	public static function refused_creations(): array {
		return [
			'a city'                  => [ 'service-city', [ 'name' => 'Одеса' ], 'dovira_taxonomy_invalid' ],
			'another taxonomy'        => [ 'post_format', [ 'name' => 'x' ], 'dovira_taxonomy_invalid' ],
			'no name'                 => [ 'category', [ 'name' => '  ' ], 'dovira_term_name_required' ],
			'a duplicate'             => [ 'category', [ 'name' => 'Новини' ], 'dovira_term_exists' ],
			'a parent for a tag'      => [ 'post_tag', [ 'name' => 'x', 'parent' => 1 ], 'dovira_parent_invalid' ],
			'a missing parent'        => [ 'category', [ 'name' => 'x', 'parent' => 404 ], 'dovira_parent_invalid' ],
			'a Russian parent'        => [ 'category', [ 'name' => 'x', 'parent' => 9 ], 'dovira_parent_invalid' ],
		];
	}

	/**
	 * @param array<string, mixed> $fields
	 */
	#[DataProvider( 'refused_creations' )]
	public function test_a_creation_is_refused_before_anything_is_written( string $taxonomy, array $fields, string $code ): void {
		Functions\expect( 'wp_insert_term' )->never();

		$this->assertSame( $code, ( new TermWriter( true ) )->create( $taxonomy, $fields )->get_error_code() );
	}

	public function test_a_duplicate_names_the_existing_id(): void {
		$this->assertStringContainsString( 'id 1', ( new TermWriter( true ) )->create( 'category', [ 'name' => 'Новини' ] )->get_error_message() );
	}

	public function test_an_update_changes_only_what_is_named(): void {
		$plan = ( new TermWriter( true ) )->update( 1, [ 'name' => 'Новини клініки', 'description' => 'Опис' ] );

		$this->assertSame(
			[
				[ 'target' => [ 'id' => 1, 'lang' => 'uk' ], 'field' => 'name', 'from' => 'Новини', 'to' => 'Новини клініки' ],
				[ 'target' => [ 'id' => 1, 'lang' => 'uk' ], 'field' => 'description', 'from' => '', 'to' => 'Опис' ],
			],
			$plan['diff']->lines()
		);
		$plan['apply']();
		$this->assertSame( [ [ 'update', [ 1, 'category', [ 'name' => 'Новини клініки', 'description' => 'Опис' ] ] ] ], $this->writes );
	}

	/**
	 * @return array<string, array{int, array<string, mixed>, string}>
	 */
	public static function refused_updates(): array {
		return [
			'a city'                   => [ 4, [ 'name' => 'Київ' ], 'dovira_taxonomy_invalid' ],
			'a missing term'           => [ 404, [ 'name' => 'x' ], 'dovira_term_unknown' ],
			'nothing named'            => [ 1, [ 'colour' => 'red' ], 'dovira_nothing_to_change' ],
			'an empty name'            => [ 1, [ 'name' => '' ], 'dovira_term_name_required' ],
			'itself as parent'         => [ 1, [ 'parent' => 1 ], 'dovira_parent_invalid' ],
			'its descendant as parent' => [ 1, [ 'parent' => 2 ], 'dovira_parent_invalid' ],
		];
	}

	/**
	 * @param array<string, mixed> $fields
	 */
	#[DataProvider( 'refused_updates' )]
	public function test_an_update_is_refused_before_anything_is_written( int $id, array $fields, string $code ): void {
		Functions\expect( 'wp_update_term' )->never();

		$this->assertSame( $code, ( new TermWriter( true ) )->update( $id, $fields )->get_error_code() );
	}

	public function test_create_term_needs_the_taxonomys_manage_terms(): void {
		$this->assertTrue( CreateTerm::can( [ 'taxonomy' => 'category' ] ) );
		$this->assertTrue( CreateTerm::can( [ 'taxonomy' => 'post_tag' ] ) );

		$this->caps = [ 'edit_categories' ];
		$this->assertFalse( CreateTerm::can( [ 'taxonomy' => 'category' ] ) );
		$this->assertFalse( CreateTerm::can( [ 'taxonomy' => 'post_tag' ] ) );
	}

	public function test_update_term_needs_the_terms_edit_terms_and_lets_others_through_to_be_refused(): void {
		$this->assertTrue( UpdateTerm::can( [ 'id' => 1 ] ) );

		$this->caps = [];
		$this->assertFalse( UpdateTerm::can( [ 'id' => 1 ] ) );
		$this->assertTrue( UpdateTerm::can( [ 'id' => 4 ] ), 'a city passes the gate and is refused with a reason' );
	}

	public function test_both_are_writes_and_the_taxonomy_is_an_enum_of_two(): void {
		foreach ( [ CreateTerm::args(), UpdateTerm::args() ] as $args ) {
			$this->assertFalse( $args['meta']['annotations']['readonly'] );
			$this->assertFalse( $args['meta']['mcp']['public'] );
			$this->assertArrayHasKey( 'confirmation', $args['input_schema']['properties'] );
		}
		$this->assertSame( [ 'category', 'post_tag' ], CreateTerm::args()['input_schema']['properties']['taxonomy']['enum'] );
	}
}
