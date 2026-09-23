<?php
/**
 * A stand-in for WordPress's $wpdb that records what the code under test asks of it.
 */

declare( strict_types=1 );

namespace dovira\Tests;

/**
 * Tests assert the SQL a class builds against this double, never against a
 * database (docs/TESTING.md → Rules).
 */
final class WpdbDouble {

	public const CHARSET_COLLATE = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';

	public string $prefix;

	public string $last_error = '';

	/**
	 * Every prepare() call, in order: the query and its arguments.
	 *
	 * @var list<array{0: string, 1: list<mixed>}>
	 */
	public array $prepared = [];

	/**
	 * Every SQL string passed to query(), in order.
	 *
	 * @var list<string>
	 */
	public array $queries = [];

	/**
	 * Every insert() call, in order: the table, the data and the formats.
	 *
	 * @var list<array{0: string, 1: array<string, mixed>, 2: list<string>|string|null}>
	 */
	public array $inserts = [];

	/**
	 * What insert() returns: the number of rows written, or false for a failed insert.
	 */
	public int|false $insert_result = 1;

	/**
	 * Every SQL string passed to get_results(), in order.
	 *
	 * @var list<string>
	 */
	public array $selected = [];

	/**
	 * What get_results() answers, one entry per call in order: a list of rows
	 * shaped as WordPress returns them (stdClass, every value a string or null),
	 * or null. An empty queue answers [].
	 *
	 * @var list<list<\stdClass>|null>
	 */
	public array $results = [];

	public function __construct( string $prefix = 'wp_' ) {
		$this->prefix = $prefix;
	}

	public function get_charset_collate(): string {
		return self::CHARSET_COLLATE;
	}

	/**
	 * Records the call and returns the query with each %s replaced by its quoted argument.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		$this->prepared[] = [ $query, $args ];

		return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
	}

	/**
	 * Records the query and answers with the next entry of $results.
	 *
	 * @return list<\stdClass>|null
	 */
	public function get_results( string $query, string $output = 'OBJECT' ): ?array {
		$this->selected[] = $query;

		return [] === $this->results ? [] : array_shift( $this->results );
	}

	public function query( string $sql ): int {
		$this->queries[] = $sql;

		return 0;
	}

	/**
	 * @param array<string, mixed>      $data
	 * @param list<string>|string|null $format
	 */
	public function insert( string $table, array $data, array|string|null $format = null ): int|false {
		$this->inserts[] = [ $table, $data, $format ];

		return $this->insert_result;
	}
}
