<?php
/**
 * Tests for dovira\ContentMcp\Dump: the session's database dump and its retention.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\ContentMcp;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use dovira\ContentMcp\Dump;
use dovira\Tests\TestCase;

require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/Dump.php';

/**
 * DECISIONS 2026-11-05 — A database dump is taken inside the first confirmed
 * write of a session, into a web-closed folder, a new timestamped file every time.
 */
final class DumpTest extends TestCase {

	private string $root;

	/** @var array<string, mixed> */
	private array $transients = [];

	/** @var list<array{0: int, 1: string, 2: string}> */
	private array $scheduled = [];

	protected function setUp(): void {
		parent::setUp();

		$this->root = sys_get_temp_dir() . '/dovira-dump-test-' . bin2hex( random_bytes( 4 ) );
		mkdir( $this->root );

		Functions\when( 'wp_date' )->alias( static fn( string $format ) => 'c' === $format ? '2026-11-05T10:15:00+02:00' : '2026-11-05-101500' );
		Functions\when( 'wp_mkdir_p' )->alias( static fn( string $dir ) => is_dir( $dir ) || mkdir( $dir, 0755, true ) );
		Functions\when( 'wp_delete_file' )->alias(
			static function ( string $file ): void {
				if ( file_exists( $file ) ) {
					unlink( $file );
				}
			}
		);
		Functions\when( 'get_transient' )->alias( fn( string $key ) => $this->transients[ $key ] ?? false );
		Functions\when( 'set_transient' )->alias(
			function ( string $key, $value, int $ttl ): bool {
				$this->transients[ $key ] = $value;
				$this->transients[ "ttl:{$key}" ] = $ttl;
				return true;
			}
		);
	}

	protected function tearDown(): void {
		$this->remove( $this->root );
		parent::tearDown();
	}

	public function test_the_file_is_named_by_host_and_site_time_and_a_taken_name_gets_a_suffix(): void {
		$dump = $this->dump( self::db() );

		$this->assertSame( 'dovira-dev.dovira.vet-2026-11-05-101500.sql.gz', $dump->ensure( '' ) );
		$this->assertSame( 'dovira-dev.dovira.vet-2026-11-05-101500-2.sql.gz', $dump->ensure( '' ) );
		$this->assertSame( 'dovira-dev.dovira.vet-2026-11-05-101500-3.sql.gz', $dump->ensure( '' ) );
		$this->assertMatchesRegularExpression( Dump::PATTERN, 'dovira-dev.dovira.vet-2026-11-05-101500-3.sql.gz' );
	}

	public function test_the_sql_holds_every_table_in_batches_of_500_with_escaped_values_and_a_footer(): void {
		$rows = [];
		for ( $i = 1; $i <= 501; $i++ ) {
			$rows[] = [ (string) $i, 1 === $i ? "it's" : 'v', null ];
		}
		$db = self::db( [ 'wp_options' => $rows, 'wp_posts' => [ [ '1', 'Привіт' ] ] ] );

		$sql = $this->read( $this->root . '/dumps/' . $this->dump( $db )->ensure( '' ) );

		$this->assertStringContainsString( "SET NAMES utf8mb4;\n", $sql );
		$this->assertStringContainsString( "DROP TABLE IF EXISTS `wp_options`;\nCREATE TABLE `wp_options` (x);\n", $sql );
		$this->assertSame( 2, substr_count( $sql, 'INSERT INTO `wp_options` VALUES' ), '501 rows = 500 + 1' );
		$this->assertStringContainsString( "('1','it\\'s',NULL)", $sql );
		$this->assertStringContainsString( "INSERT INTO `wp_posts` VALUES\n('1','Привіт');", $sql );
		$this->assertStringContainsString( '-- host: dev.dovira.vet', $sql );
		$this->assertMatchesRegularExpression( '/-- tables: 2\n-- finished in \d+\.\d\d s, peak memory \d+\.\d MB\n$/', $sql );
		$this->assertContains( 'SELECT * FROM `wp_options` LIMIT 500 OFFSET 500', $db->queries );
	}

	public function test_the_file_is_readable_by_its_owner_only(): void {
		$name = $this->dump( self::db() )->ensure( '' );

		$this->assertSame( 0600, fileperms( $this->root . '/dumps/' . $name ) & 0777 );
	}

	public function test_a_configured_folder_is_created_and_left_without_htaccess_or_index(): void {
		$this->dump( self::db() )->ensure( '' );

		$this->assertDirectoryExists( $this->root . '/dumps' );
		$this->assertFileDoesNotExist( $this->root . '/dumps/.htaccess' );
		$this->assertFileDoesNotExist( $this->root . '/dumps/index.php' );
	}

	public function test_the_fallback_folder_gets_a_deny_htaccess_and_an_empty_index(): void {
		$dump = new Dump( self::db(), null, $this->root . '/backup-db', 'dev.dovira.vet' );

		$name = $dump->ensure( '' );

		$this->assertFileExists( $this->root . '/backup-db/' . $name );
		$this->assertStringContainsString( 'Require all denied', (string) file_get_contents( $this->root . '/backup-db/.htaccess' ) );
		$this->assertSame( '', file_get_contents( $this->root . '/backup-db/index.php' ) );
	}

	public function test_a_session_dumps_once_and_its_mark_lives_a_day(): void {
		$dump = $this->dump( self::db() );

		$first = $dump->ensure( 'session-a' );

		$this->assertSame( 'existing', $dump->ensure( 'session-a' ) );
		$this->assertSame( $first, $this->transients['dovira_mcp_dumped_session-a'] );
		$this->assertSame( DAY_IN_SECONDS, $this->transients['ttl:dovira_mcp_dumped_session-a'] );
		$this->assertCount( 1, glob( $this->root . '/dumps/*.sql.gz' ) );
		$this->assertNotSame( 'existing', $dump->ensure( 'session-b' ), 'another session dumps again' );
	}

	public function test_without_a_session_every_call_dumps_and_nothing_is_marked(): void {
		$dump = $this->dump( self::db() );

		$dump->ensure( '' );
		$dump->ensure( '' );

		$this->assertCount( 2, glob( $this->root . '/dumps/*.sql.gz' ) );
		$this->assertSame( [], $this->transients );
	}

	public function test_a_failing_table_throws_and_leaves_no_file_and_no_mark(): void {
		$db           = self::db();
		$db->fail_on = 'wp_posts';

		try {
			$this->dump( $db )->ensure( 'session-a' );
			$this->fail( 'The dump must throw.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'Table crashed', $e->getMessage() );
		}

		$this->assertSame( [], glob( $this->root . '/dumps/*' ) );
		$this->assertSame( [], $this->transients );
	}

	public function test_a_database_without_tables_is_a_failure(): void {
		$this->expectException( \RuntimeException::class );

		$this->dump( self::db( [] ) )->ensure( '' );
	}

	public function test_an_unwritable_folder_throws(): void {
		mkdir( $this->root . '/locked', 0500 );
		if ( is_writable( $this->root . '/locked' ) ) {
			chmod( $this->root . '/locked', 0700 );
			$this->markTestSkipped( 'Running as a user who can write anywhere.' );
		}

		try {
			( new Dump( self::db(), $this->root . '/locked', null, 'dev.dovira.vet' ) )->ensure( '' );
			$this->fail( 'The dump must throw.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'недоступна для запису', $e->getMessage() );
		} finally {
			chmod( $this->root . '/locked', 0700 );
		}
	}

	public function test_retention_deletes_only_this_features_dumps_older_than_the_days(): void {
		$dir = $this->root . '/dumps';
		mkdir( $dir );
		$now   = 1_800_000_000;
		$files = [
			'dovira-dev.dovira.vet-2026-01-01-101500.sql.gz'   => 91,
			'dovira-dev.dovira.vet-2026-01-01-101500-2.sql.gz' => 120,
			'dovira-dev.dovira.vet-2026-08-01-101500.sql.gz'   => 89,
			'other-backup.sql.gz'                              => 400,
			'dovira-notes.txt'                                 => 400,
			'.htaccess'                                        => 400,
		];
		foreach ( $files as $name => $days ) {
			touch( "{$dir}/{$name}", $now - $days * DAY_IN_SECONDS );
		}

		$this->assertSame( 2, $this->dump( self::db() )->prune( $now ) );

		$this->assertSame(
			[ '.htaccess', 'dovira-dev.dovira.vet-2026-08-01-101500.sql.gz', 'dovira-notes.txt', 'other-backup.sql.gz' ],
			array_values( array_diff( (array) scandir( $dir ), [ '.', '..' ] ) )
		);
	}

	public function test_retention_follows_the_filter_but_never_below_one_day(): void {
		$dir = $this->root . '/dumps';
		mkdir( $dir );
		$now = 1_800_000_000;
		touch( "{$dir}/dovira-x-2026-01-01-000000.sql.gz", $now - 3600 );
		touch( "{$dir}/dovira-x-2026-01-02-000000.sql.gz", $now - 2 * DAY_IN_SECONDS );

		Filters\expectApplied( 'dovira_mcp_dump_retention_days' )->once()->with( 90 )->andReturn( 0 );

		$this->assertSame( 1, $this->dump( self::db() )->prune( $now ) );
		$this->assertFileExists( "{$dir}/dovira-x-2026-01-01-000000.sql.gz" );
	}

	public function test_the_daily_event_is_scheduled_once(): void {
		$next = false;
		Functions\when( 'wp_next_scheduled' )->alias( static function () use ( &$next ) {
			return $next;
		} );
		Functions\when( 'wp_schedule_event' )->alias(
			function ( int $time, string $recurrence, string $hook ) use ( &$next ): bool {
				$this->scheduled[] = [ $time, $recurrence, $hook ];
				$next              = $time + DAY_IN_SECONDS;
				return true;
			}
		);

		Dump::schedule();
		Dump::schedule();

		$this->assertCount( 1, $this->scheduled );
		$this->assertSame( [ 'daily', 'dovira_mcp_dump_retention' ], array_slice( $this->scheduled[0], 1 ) );
	}

	private function dump( object $db ): Dump {
		return new Dump( $db, $this->root . '/dumps', $this->root . '/unused-fallback', 'dev.dovira.vet' );
	}

	private function read( string $file ): string {
		return (string) gzdecode( (string) file_get_contents( $file ) );
	}

	/**
	 * @param array<string, list<list<string|null>>>|null $tables
	 */
	private static function db( ?array $tables = null ): object {
		return new class( $tables ?? [ 'wp_options' => [ [ '1', 'siteurl' ] ], 'wp_posts' => [] ] ) {
			public string $last_error = '';
			public ?string $fail_on   = null;
			/** @var list<string> */
			public array $queries = [];

			/** @param array<string, list<list<string|null>>> $tables */
			public function __construct( private array $tables ) {}

			/** @return list<string> */
			public function get_col( string $query ): array {
				$this->queries[] = $query;
				return array_keys( $this->tables );
			}

			/** @return list<string>|null */
			public function get_row( string $query, string $output ): ?array {
				$this->queries[] = $query;
				preg_match( '/`(.+)`/', $query, $m );
				return [ $m[1], "CREATE TABLE `{$m[1]}` (x)" ];
			}

			/** @return list<list<string|null>>|null */
			public function get_results( string $query, string $output ): ?array {
				$this->queries[] = $query;
				preg_match( '/FROM `(.+)` LIMIT (\d+) OFFSET (\d+)/', $query, $m );
				if ( $m[1] === $this->fail_on ) {
					$this->last_error = 'Table crashed';
					return null;
				}
				return array_slice( $this->tables[ $m[1] ], (int) $m[3], (int) $m[2] );
			}

			public function _real_escape( string $value ): string { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
				return addslashes( $value );
			}

			public function remove_placeholder_escape( string $value ): string {
				return $value;
			}
		};
	}

	private function remove( string $path ): void {
		if ( is_dir( $path ) ) {
			chmod( $path, 0700 );
			foreach ( array_diff( (array) scandir( $path ), [ '.', '..' ] ) as $name ) {
				$this->remove( "{$path}/{$name}" );
			}
			rmdir( $path );
		} elseif ( file_exists( $path ) ) {
			unlink( $path );
		}
	}
}
