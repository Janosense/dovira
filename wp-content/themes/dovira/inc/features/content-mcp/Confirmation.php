<?php

namespace dovira\ContentMcp;

/**
 * Confirmation tokens: a write happens only with the token a preview of the
 * same payload issued to the same user in the same session (DECISIONS
 * 2026-11-05 — A write happens only with a confirmation token…).
 *
 * The transient `dovira_mcp_confirm_{hash}` (15 minutes) is keyed by the
 * binding — user, session, payload — and holds only the SHA-256 of the random
 * token, so the token itself is the secret and is never stored.
 */
final class Confirmation {

	public const TTL = 900;

	private const PREFIX = 'dovira_mcp_confirm_';

	/**
	 * Stores a pending confirmation for this payload and returns its token.
	 * A second preview of the same payload replaces the first one's token.
	 *
	 * @param array<string, mixed> $payload The write's input without `confirmation`.
	 * @param array<string, mixed> $diff    What the preview showed (`Diff::to_array()`).
	 */
	public static function issue( int $user_id, string $session_id, array $payload, array $diff ): string {
		$token = bin2hex( random_bytes( 16 ) );

		set_transient(
			self::key( $user_id, $session_id, $payload ),
			[
				'user_id'      => $user_id,
				'session'      => $session_id,
				'payload_hash' => self::payload_hash( $payload ),
				'token_hash'   => hash( 'sha256', $token ),
				'diff'         => $diff,
				'issued_at'    => time(),
			],
			self::TTL
		);

		return $token;
	}

	/**
	 * True once: for the token this user's preview of this payload issued in
	 * this session. The pending confirmation is deleted before true is returned.
	 *
	 * @param array<string, mixed> $payload The write's input without `confirmation`.
	 */
	public static function verify( string $token, int $user_id, string $session_id, array $payload ): bool {
		if ( '' === $token ) {
			return false;
		}

		$key     = self::key( $user_id, $session_id, $payload );
		$pending = get_transient( $key );

		if ( ! is_array( $pending ) || ! isset( $pending['token_hash'] ) || ! is_string( $pending['token_hash'] ) ) {
			return false;
		}

		if ( ! hash_equals( $pending['token_hash'], hash( 'sha256', $token ) ) ) {
			return false;
		}

		delete_transient( $key );

		return true;
	}

	/**
	 * The payload as canonical JSON: keys sorted at every level, no `confirmation`.
	 *
	 * @param array<string, mixed> $payload
	 */
	public static function canonical( array $payload ): string {
		unset( $payload['confirmation'] );

		return (string) json_encode( self::sorted( $payload ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION );
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private static function key( int $user_id, string $session_id, array $payload ): string {
		return self::PREFIX . hash( 'sha256', $user_id . "\n" . $session_id . "\n" . self::canonical( $payload ) );
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private static function payload_hash( array $payload ): string {
		return hash( 'sha256', self::canonical( $payload ) );
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	private static function sorted( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( ! array_is_list( $value ) ) {
			ksort( $value );
		}

		return array_map( [ self::class, 'sorted' ], $value );
	}
}
