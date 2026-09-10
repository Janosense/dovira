<?php
/**
 * The posts WordPress finds at the page paths a report lists.
 *
 * @package GaTelegramBridge
 */

declare( strict_types=1 );

namespace GaTelegramBridge\Tests;

use Brain\Monkey\Functions;

/**
 * Stubs the three lookups a page is named with — url_to_postid(),
 * get_permalink() and get_post_field() — from a table of posts.
 *
 * They are WordPress globals, not the plugin's parser, so they are stubbed with
 * what WordPress really answered (docs/TESTING.md → Never mocked): the recorded
 * table is what a local copy of the Kharkiv site returned on 2026-09-10 for the
 * paths in batch-run-reports-daily-call-1.json.
 */
final class SitePosts {

	/**
	 * The recorded paths, each with the id and title of the post found there.
	 */
	public const RECORDED = array(
		'/'                               => array( 9, 'Головна сторінка' ),
		'/services/'                      => array( 12, 'Послуги' ),
		'/contacts/'                      => array( 219, 'Контакти' ),
		'/services/reception-department/' => array( 18, 'Приймальне відділення' ),
		'/services/dental-services/'      => array( 84, 'Стоматологічні послуги' ),
		'/about/'                         => array( 176, 'Про нас' ),
	);

	/**
	 * Stubs the lookups for posts that live exactly where they are looked for.
	 *
	 * @param string                            $home  What home_url() returns in the test.
	 * @param array<string, array{int, string}> $posts Post id and title, by path.
	 */
	public static function given( string $home, array $posts = self::RECORDED ): void {
		$lookups = array();

		foreach ( $posts as $path => $post ) {
			$lookups[ $home . $path ] = array( $post[0], $home . $path, $post[1] );
		}

		self::given_lookups( $lookups );
	}

	/**
	 * Stubs the lookups from what each address resolves to.
	 *
	 * An address that is not in the table belongs to no post, as url_to_postid()
	 * answers with 0.
	 *
	 * @param array<string, array{int, string, string}> $lookups Post id, its permalink and its title, by the address asked about.
	 */
	public static function given_lookups( array $lookups ): void {
		$by_id = array();

		foreach ( $lookups as $post ) {
			$by_id[ $post[0] ] = $post;
		}

		Functions\when( 'url_to_postid' )->alias(
			static fn( string $url ): int => isset( $lookups[ $url ] ) ? $lookups[ $url ][0] : 0
		);
		Functions\when( 'get_permalink' )->alias(
			static fn( int $post_id ) => isset( $by_id[ $post_id ] ) ? $by_id[ $post_id ][1] : false
		);
		Functions\when( 'get_post_field' )->alias(
			static fn( string $field, int $post_id ): string => isset( $by_id[ $post_id ] ) ? $by_id[ $post_id ][2] : ''
		);
	}
}
