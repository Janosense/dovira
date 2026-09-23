<?php
/**
 * Tests for dovira\SearchStats\Schema.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\SearchStats;

use Brain\Monkey\Functions;
use dovira\SearchStats\Schema;
use dovira\Tests\TestCase;
use dovira\Tests\WpdbDouble;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname( __DIR__, 3 ) . '/inc/features/search-stats/Schema.php';

/**
 * The table's SQL, and when dbDelta runs and the schema version is written.
 */
final class SchemaTest extends TestCase {

	/**
	 * Every SQL string handed to dbDelta(), in order.
	 *
	 * @var list<string>
	 */
	private array $delta_calls = [];

	/**
	 * Every update_option() call, in order: its arguments.
	 *
	 * @var list<list<mixed>>
	 */
	private array $option_writes = [];

	/**
	 * What dbDelta() leaves in $wpdb->last_error; '' is a successful run.
	 */
	private string $delta_error = '';

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['wpdb'] = new WpdbDouble( 'wp_' );

		Functions\when( 'dbDelta' )->alias(
			function ( string $sql ): array {
				$this->delta_calls[]            = $sql;
				$GLOBALS['wpdb']->last_error = $this->delta_error;

				return [];
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( ...$args ): bool {
				$this->option_writes[] = $args;

				return true;
			}
		);
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/**
	 * The exact statement dbDelta must receive for a given table prefix.
	 */
	private static function expected_sql( string $prefix ): string {
		return "CREATE TABLE {$prefix}dovira_search_queries (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  level varchar(16) NOT NULL,
  query_text varchar(100) NOT NULL,
  context_id bigint(20) unsigned NOT NULL DEFAULT 0,
  results int(10) unsigned NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY level_created_at (level,created_at),
  KEY created_at (created_at)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci";
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function prefixes(): array {
		return [
			'default prefix' => [ 'wp_' ],
			'another prefix' => [ 'wpk_' ],
		];
	}

	#[DataProvider( 'prefixes' )]
	public function test_dbdelta_receives_the_table_with_every_column_and_key( string $prefix ): void {
		$GLOBALS['wpdb'] = new WpdbDouble( $prefix );

		Schema::install();

		$this->assertSame( [ self::expected_sql( $prefix ) ], $this->delta_calls );
	}

	public function test_a_successful_run_writes_the_version_once(): void {
		Schema::install();

		$this->assertSame( [ [ 'dovira_search_stats_db_version', 1, true ] ], $this->option_writes );
	}

	public function test_a_failed_run_writes_no_version(): void {
		$this->delta_error = "Can't create table 'wp_dovira_search_queries'";

		Schema::install();

		$this->assertCount( 1, $this->delta_calls );
		$this->assertSame( [], $this->option_writes );
	}

	/**
	 * @return array<string, array{callable}>
	 */
	public static function outdated_versions(): array {
		return [
			'option absent' => [ static fn ( string $name, $default = false ) => $default ],
			'option behind' => [ static fn ( string $name, $default = false ) => '0' ],
		];
	}

	#[DataProvider( 'outdated_versions' )]
	public function test_an_outdated_version_runs_dbdelta_and_writes_the_version( callable $get_option ): void {
		Functions\when( 'get_option' )->alias( $get_option );

		Schema::maybe_upgrade();

		$this->assertCount( 1, $this->delta_calls );
		$this->assertSame( [ [ 'dovira_search_stats_db_version', 1, true ] ], $this->option_writes );
	}

	public function test_a_current_version_touches_nothing(): void {
		Functions\when( 'get_option' )->justReturn( '1' );

		Schema::maybe_upgrade();

		$this->assertSame( [], $this->delta_calls );
		$this->assertSame( [], $this->option_writes );
	}
}
