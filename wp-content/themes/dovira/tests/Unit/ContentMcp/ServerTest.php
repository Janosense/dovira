<?php
/**
 * Tests for the dovira MCP server, its catalogue and dovira/describe-site.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\ContentMcp;

use Brain\Monkey\Functions;
use dovira\ContentMcp\Abilities;
use dovira\ContentMcp\Catalogue;
use dovira\ContentMcp\DescribeSite;
use dovira\ContentMcp\Schema;
use dovira\ContentMcp\Server;
use dovira\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/Catalogue.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/Scope.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/Schema.php';
foreach ( [ 'FieldValues', 'CityPrice', 'PostReader', 'ReadTools' ] as $dovira_class ) {
	require_once dirname( __DIR__, 3 ) . "/inc/features/content-mcp/{$dovira_class}.php";
}
require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/DescribeSite.php';
foreach ( [ 'ListPosts', 'GetPost', 'ListTerms', 'ListMedia', 'FindPriceRows', 'Session', 'Confirmation', 'Diff', 'Dump', 'PriceRows', 'WriteAbility', 'UpdatePriceRow' ] as $dovira_class ) {
	require_once dirname( __DIR__, 3 ) . "/inc/features/content-mcp/{$dovira_class}.php";
}
require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/Abilities.php';
require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/Server.php';

/**
 * The server lists exactly the catalogue, every catalogued ability is
 * registered, and describe-site tells an administrator from an editor.
 */
final class ServerTest extends TestCase {

	public function test_every_catalogued_ability_is_registered_in_the_dovira_category(): void {
		$registered = [];
		Functions\when( 'wp_register_ability' )->alias(
			static function ( string $name, array $args ) use ( &$registered ) {
				$registered[ $name ] = $args;
				return null;
			}
		);

		Abilities::register();

		$this->assertSame( Catalogue::TOOLS, array_keys( $registered ) );
		foreach ( $registered as $args ) {
			$this->assertSame( 'dovira', $args['category'] );
		}
	}

	public function test_the_category_is_registered_as_dovira(): void {
		$slugs = [];
		Functions\when( 'wp_register_ability_category' )->alias(
			static function ( string $slug ) use ( &$slugs ) {
				$slugs[] = $slug;
				return null;
			}
		);

		Abilities::register_category();

		$this->assertSame( [ 'dovira' ], $slugs );
	}

	public function test_only_dovira_abilities_are_catalogued_and_none_is_on_the_default_server(): void {
		foreach ( Abilities::definitions() as $name => $args ) {
			$this->assertStringStartsWith( 'dovira/', $name );
			$this->assertFalse( $args['meta']['mcp']['public'] );
			$this->assertTrue( $args['meta']['show_in_rest'] );
		}
	}

	public function test_describe_site_is_annotated_readonly(): void {
		$annotations = DescribeSite::args()['meta']['annotations'];

		$this->assertTrue( $annotations['readonly'] );
		$this->assertFalse( $annotations['destructive'] );
		$this->assertTrue( $annotations['idempotent'] );
	}

	public function test_the_catalogue_reads_finds_prices_and_changes_a_price_row(): void {
		$this->assertSame(
			[ 'dovira/describe-site', 'dovira/list-posts', 'dovira/get-post', 'dovira/find-price-rows', 'dovira/list-terms', 'dovira/list-media', 'dovira/update-price-row' ],
			Catalogue::TOOLS
		);
		$this->assertSame( Catalogue::TOOLS, array_keys( Abilities::definitions() ) );
	}

	public function test_the_instructions_name_the_price_tools_and_the_preview_rule(): void {
		$this->assertStringContainsString( 'dovira-describe-site first', Server::INSTRUCTIONS );
		$this->assertStringContainsString( 'dovira-find-price-rows', Server::INSTRUCTIONS );
		$this->assertStringContainsString( 'dovira-update-price-row', Server::INSTRUCTIONS );
		$this->assertStringContainsString( 'without "confirmation"', Server::INSTRUCTIONS );
		$this->assertStringContainsString( 'Russian copy', Server::INSTRUCTIONS );
	}

	public function test_the_server_lists_exactly_the_catalogue_on_mcp_dovira_behind_the_adapters_gate(): void {
		$adapter = new class() {
			/** @var list<mixed> */
			public array $args = [];

			public function create_server( ...$args ) {
				$this->args = $args;
				return $this;
			}
		};

		Server::register( $adapter );

		$this->assertSame( 'dovira', $adapter->args[0] );
		$this->assertSame( 'mcp', $adapter->args[1] );
		$this->assertSame( 'dovira', $adapter->args[2] );
		$this->assertSame( Server::INSTRUCTIONS, $adapter->args[4] );
		$this->assertSame( [ 'WP\MCP\Transport\HttpTransport' ], $adapter->args[6] );
		$this->assertSame( Catalogue::TOOLS, $adapter->args[9] );
		$this->assertSame( [], $adapter->args[10] );
		$this->assertSame( [], $adapter->args[11] );
		$this->assertNull( $adapter->args[12] );
	}

	public function test_a_failed_server_creation_is_logged_not_fatal(): void {
		$adapter = new class() {
			public function create_server( ...$args ) {
				return new \WP_Error( 'invalid_error_handler', 'nope' );
			}
		};
		Functions\when( 'is_wp_error' )->alias( static fn( $v ) => $v instanceof \WP_Error );
		// error_log() is internal and cannot be stubbed: point it at a temp file.
		$log      = (string) tempnam( sys_get_temp_dir(), 'dovira-mcp' );
		$previous = ini_set( 'error_log', $log );

		try {
			Server::register( $adapter );
			$logged = (string) file_get_contents( $log );
		} finally {
			ini_set( 'error_log', (string) $previous );
			unlink( $log );
		}

		$this->assertStringContainsString( 'the MCP server was not created: nope', $logged );
	}

	/**
	 * @return array<string, array{bool, bool}>
	 */
	public static function roles(): array {
		return [
			'administrator' => [ true, true ],
			'editor'        => [ false, false ],
		];
	}

	#[DataProvider( 'roles' )]
	public function test_can_delete_is_true_only_with_manage_options( bool $manage_options, bool $expected ): void {
		$this->stub_user( [ 'administrator' ], $manage_options );
		Functions\when( 'get_site_url' )->justReturn( 'https://dev.dovira.vet' );

		$result = ( $this->describe( false ) )->execute();

		$this->assertSame( $expected, $result['user']['can_delete'] );
		$this->assertSame( 'tymofii', $result['user']['login'] );
		$this->assertSame( [ 'administrator' ], $result['user']['roles'] );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function urls(): array {
		return [
			'Kharkiv production' => [ 'https://dovira.vet', 'kharkiv' ],
			'Kyiv production'    => [ 'https://kyiv.dovira.vet', 'kyiv' ],
			'dev'                => [ 'https://dev.dovira.vet', 'kharkiv' ],
			'DDEV'               => [ 'https://dovira.ddev.site', 'kharkiv' ],
		];
	}

	#[DataProvider( 'urls' )]
	public function test_the_city_comes_from_the_site_url( string $url, string $city ): void {
		$this->stub_user( [ 'editor' ], false );
		Functions\when( 'get_site_url' )->justReturn( $url );

		$this->assertSame( $city, ( $this->describe( false ) )->execute()['city'] );
	}

	public function test_languages_are_empty_without_polylang(): void {
		$this->stub_user( [ 'editor' ], false );
		Functions\when( 'get_site_url' )->justReturn( 'https://dovira.vet' );

		$this->assertSame( [], ( $this->describe( false ) )->execute()['languages'] );
	}

	public function test_languages_come_from_polylang(): void {
		$this->stub_user( [ 'editor' ], false );
		Functions\when( 'get_site_url' )->justReturn( 'https://dovira.vet' );
		Functions\when( 'pll_languages_list' )->justReturn( [ 'uk', 'ru' ] );

		$this->assertSame( [ 'uk', 'ru' ], ( $this->describe( true ) )->execute()['languages'] );
	}

	/**
	 * @return array<string, array{bool}>
	 */
	public static function read_capability(): array {
		return [
			'can read'    => [ true ],
			'cannot read' => [ false ],
		];
	}

	#[DataProvider( 'read_capability' )]
	public function test_the_permission_callback_asks_for_read( bool $can ): void {
		Functions\expect( 'current_user_can' )->once()->with( 'read' )->andReturn( $can );

		$this->assertSame( $can, call_user_func( DescribeSite::args()['permission_callback'] ) );
	}

	/**
	 * describe-site without ACF and with no post type or taxonomy registered:
	 * these tests are about the city, the languages and the user.
	 */
	private function describe( bool $polylang ): DescribeSite {
		Functions\when( 'get_post_type_object' )->justReturn( null );
		Functions\when( 'get_taxonomy' )->justReturn( false );

		return new DescribeSite( $polylang, new Schema( false, false ) );
	}

	/**
	 * @param list<string> $roles
	 */
	private function stub_user( array $roles, bool $manage_options ): void {
		$user             = new \stdClass();
		$user->user_login = 'tymofii';
		$user->roles      = $roles;
		Functions\when( 'wp_get_current_user' )->justReturn( $user );
		Functions\when( 'current_user_can' )->alias(
			static fn( string $cap ) => 'manage_options' === $cap ? $manage_options : true
		);
	}
}
