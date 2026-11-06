<?php

namespace dovira\ContentMcp;

/**
 * The write protocol every write ability shares (DECISIONS 2026-11-05 — A
 * write happens only with a confirmation token…; A database dump is taken
 * inside the first confirmed write of a session…):
 *
 * 1. the payload is the input without `confirmation`;
 * 2. `preview()` validates it and computes the diff — an error issues nothing;
 * 3. without `confirmation`: `{diff, warnings, confirmation}` and no write;
 * 4. with it: the token must be this user's, session's and payload's, once;
 * 5. the session's dump (`Dump::ensure()`) — a failure writes nothing;
 * 6. `write()` → `{written, dump, warnings}`.
 */
abstract class WriteAbility {

	/** @var callable(string): string */
	private $dump;

	/**
	 * @param callable|null $dump Takes the session's dump (`Dump::ensure()`); null uses `Dump`.
	 */
	public function __construct( ?callable $dump = null ) {
		$this->dump = $dump ?? static fn( string $session ): string => ( new Dump() )->ensure( $session );
	}

	/**
	 * Validates the payload and computes what would change. Writes nothing.
	 *
	 * @param array<string, mixed> $payload
	 * @return Diff|\WP_Error
	 */
	abstract protected function preview( array $payload );

	/**
	 * Writes what `preview()` computed for the same payload. Keys besides
	 * `written` / `warnings` are added to the result.
	 *
	 * @param array<string, mixed> $payload
	 * @return array{written: list<int>, warnings: list<string>, ...}|\WP_Error
	 */
	abstract protected function write( array $payload );

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|\WP_Error
	 */
	public function run( array $input ) {
		$token   = isset( $input['confirmation'] ) && is_string( $input['confirmation'] ) ? $input['confirmation'] : '';
		$payload = $input;
		unset( $payload['confirmation'] );

		$diff = $this->preview( $payload );
		if ( is_wp_error( $diff ) ) {
			return $diff;
		}

		$user    = (int) get_current_user_id();
		$session = Session::id();

		if ( '' === $token ) {
			if ( $diff->is_empty() ) {
				return [
					'diff'         => [],
					'warnings'     => array_merge( $diff->warnings(), [ 'Нічого не змінюється: значення вже такі, підтвердження не потрібне.' ] ),
					'confirmation' => null,
				];
			}

			$preview = $diff->to_array();

			return $preview + [ 'confirmation' => Confirmation::issue( $user, $session, $payload, $preview ) ];
		}

		if ( ! Confirmation::verify( $token, $user, $session, $payload ) ) {
			return new \WP_Error(
				'dovira_confirmation_invalid',
				'Підтвердження не дійсне для цих змін (інші дані, інша розмова або минуло 15 хвилин). Нічого не записано — викличте інструмент без confirmation, покажіть новий перегляд і дочекайтеся згоди.'
			);
		}

		try {
			$dump = (string) ( $this->dump )( $session );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'dovira_dump_failed', 'Дамп бази не створено, тому нічого не записано. ' . $e->getMessage() );
		}

		$written = $this->write( $payload );
		if ( is_wp_error( $written ) ) {
			return $written;
		}

		// What a write returns besides `written` / `warnings` (`upload-media`'s
		// `media`) goes into the result; it never replaces the protocol's keys.
		return [
			'written'  => $written['written'],
			'dump'     => $dump,
			'warnings' => $written['warnings'],
		] + array_diff_key( $written, array_flip( [ 'written', 'dump', 'warnings' ] ) );
	}

	/**
	 * The `confirmation` input property every write ability adds to its own.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function confirmation_schema(): array {
		return [
			'confirmation' => [
				'type'        => 'string',
				'description' => 'Токен із попереднього перегляду тих самих змін. Без нього нічого не записується — повертається перегляд {diff, warnings, confirmation}. Передавайте лише після згоди людини з diff.',
			],
		];
	}

	/**
	 * The result of a preview or of a write.
	 *
	 * @return array<string, mixed>
	 */
	public static function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'diff'         => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'target' => [ 'type' => 'object' ],
							'field'  => [ 'type' => 'string' ],
						],
					],
				],
				'warnings'     => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'confirmation' => [ 'type' => [ 'string', 'null' ] ],
				'written'      => [
					'type'  => 'array',
					'items' => [ 'type' => 'integer' ],
				],
				'dump'         => [ 'type' => 'string' ],
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => false,
			],
			'show_in_rest' => true,
			'mcp'          => [ 'public' => false ],
		];
	}
}
