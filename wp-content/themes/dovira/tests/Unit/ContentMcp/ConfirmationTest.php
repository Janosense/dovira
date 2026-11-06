<?php
/**
 * Tests for dovira\ContentMcp\Confirmation: a write's token is bound to the
 * user, the session and the payload, and writes once.
 */

declare( strict_types=1 );

namespace dovira\Tests\Unit\ContentMcp;

use Brain\Monkey\Functions;
use dovira\ContentMcp\Confirmation;
use dovira\Tests\TestCase;

require_once dirname( __DIR__, 3 ) . '/inc/features/content-mcp/Confirmation.php';

/**
 * DECISIONS 2026-11-05 — A write happens only with a confirmation token
 * issued by a preview of the same payload in the same session.
 */
final class ConfirmationTest extends TestCase {

	/** @var array<string, array{value: mixed, ttl: int}> */
	private array $transients = [];

	private const PAYLOAD = [
		'service_id' => 18,
		'row_index'  => 3,
		'price'      => 550,
	];

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'set_transient' )->alias(
			function ( string $key, $value, int $ttl ): bool {
				$this->transients[ $key ] = [
					'value' => $value,
					'ttl'   => $ttl,
				];
				return true;
			}
		);
		Functions\when( 'get_transient' )->alias( fn( string $key ) => $this->transients[ $key ]['value'] ?? false );
		Functions\when( 'delete_transient' )->alias(
			function ( string $key ): bool {
				unset( $this->transients[ $key ] );
				return true;
			}
		);
	}

	public function test_the_token_verifies_for_the_same_user_session_and_payload_in_any_key_order(): void {
		$token = Confirmation::issue( 7, 'session-a', self::PAYLOAD, [ 'diff' => [] ] );

		$reordered = [
			'price'        => 550,
			'service_id'   => 18,
			'row_index'    => 3,
			'confirmation' => $token,
		];

		$this->assertTrue( Confirmation::verify( $token, 7, 'session-a', $reordered ) );
	}

	public function test_a_token_writes_once(): void {
		$token = Confirmation::issue( 7, 'session-a', self::PAYLOAD, [] );

		$this->assertTrue( Confirmation::verify( $token, 7, 'session-a', self::PAYLOAD ) );
		$this->assertFalse( Confirmation::verify( $token, 7, 'session-a', self::PAYLOAD ) );
		$this->assertSame( [], $this->transients );
	}

	/**
	 * @return array<string, array{int, string, array<string, mixed>}>
	 */
	public static function other_bindings(): array {
		return [
			'another user'    => [ 8, 'session-a', self::PAYLOAD ],
			'another session' => [ 7, 'session-b', self::PAYLOAD ],
			'no session'      => [ 7, '', self::PAYLOAD ],
			'another payload' => [ 7, 'session-a', [ 'price' => 560 ] + self::PAYLOAD ],
		];
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'other_bindings' )]
	public function test_a_token_is_refused_outside_its_binding( int $user, string $session, array $payload ): void {
		$token = Confirmation::issue( 7, 'session-a', self::PAYLOAD, [] );

		$this->assertFalse( Confirmation::verify( $token, $user, $session, $payload ) );
		$this->assertTrue( Confirmation::verify( $token, 7, 'session-a', self::PAYLOAD ), 'the refused attempt does not spend the token' );
	}

	public function test_an_unknown_or_empty_token_is_refused(): void {
		Confirmation::issue( 7, 'session-a', self::PAYLOAD, [] );

		$this->assertFalse( Confirmation::verify( 'guessed', 7, 'session-a', self::PAYLOAD ) );
		$this->assertFalse( Confirmation::verify( '', 7, 'session-a', self::PAYLOAD ) );
	}

	public function test_an_expired_confirmation_is_refused(): void {
		$token            = Confirmation::issue( 7, 'session-a', self::PAYLOAD, [] );
		$this->transients = [];

		$this->assertFalse( Confirmation::verify( $token, 7, 'session-a', self::PAYLOAD ) );
	}

	public function test_a_new_preview_of_the_same_payload_replaces_the_token(): void {
		$first  = Confirmation::issue( 7, 'session-a', self::PAYLOAD, [] );
		$second = Confirmation::issue( 7, 'session-a', self::PAYLOAD, [] );

		$this->assertNotSame( $first, $second );
		$this->assertFalse( Confirmation::verify( $first, 7, 'session-a', self::PAYLOAD ) );
		$this->assertTrue( Confirmation::verify( $second, 7, 'session-a', self::PAYLOAD ) );
	}

	public function test_the_pending_confirmation_lives_15_minutes_and_never_holds_the_token(): void {
		$diff  = [ 'diff' => [ [ 'field' => 'prices[3].price' ] ], 'warnings' => [] ];
		$token = Confirmation::issue( 7, 'session-a', self::PAYLOAD, $diff );

		$this->assertCount( 1, $this->transients );
		$key    = (string) array_key_first( $this->transients );
		$stored = $this->transients[ $key ];

		$this->assertStringStartsWith( 'dovira_mcp_confirm_', $key );
		$this->assertSame( 900, $stored['ttl'] );
		$this->assertSame( 7, $stored['value']['user_id'] );
		$this->assertSame( 'session-a', $stored['value']['session'] );
		$this->assertSame( $diff, $stored['value']['diff'] );
		$this->assertSame( hash( 'sha256', $token ), $stored['value']['token_hash'] );
		$this->assertStringNotContainsString( $token, (string) json_encode( $stored ) . $key );
	}
}
