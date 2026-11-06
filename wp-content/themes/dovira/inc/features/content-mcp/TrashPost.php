<?php

namespace dovira\ContentMcp;

/**
 * Ability `dovira/trash-post`: moves a content post — and its translation
 * only when named — to the WordPress trash, never deleting it; administrators
 * only (`manage_options` and `delete_post`), behind the write protocol.
 */
final class TrashPost extends WriteAbility {

	public const NAME = 'dovira/trash-post';

	private bool $polylang;

	/** @var list<int> */
	private array $planned = [];

	private ?Diff $diff = null;

	public function __construct( ?callable $dump = null, ?bool $polylang = null ) {
		parent::__construct( $dump );
		$this->polylang = $polylang ?? function_exists( 'pll_get_post_translations' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function args(): array {
		return [
			'label'               => 'Запис у кошик',
			'description'         => 'Переміщує запис у кошик WordPress (відновлюється у wp-admin; назавжди не видаляє). Лише для адміністратора. Переклад переміщується, лише якщо передати його id у translation_id. Спершу без confirmation: diff і токен; після згоди — те саме з confirmation.',
			'category'            => Catalogue::CATEGORY,
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'id'             => [
						'type'    => 'integer',
						'minimum' => 1,
					],
					'translation_id' => [
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => 'Id перекладу (translations у get-post), якщо його теж у кошик.',
					],
				] + self::confirmation_schema(),
				'required'             => [ 'id' ],
				'additionalProperties' => false,
			],
			'output_schema'       => self::output_schema(),
			'execute_callback'    => static fn( $input ) => ( new self() )->run( (array) $input ),
			'permission_callback' => [ self::class, 'can' ],
			'meta'                => self::meta(),
		];
	}

	/**
	 * `manage_options` and `delete_post` of every named post that exists.
	 *
	 * @param mixed $input
	 */
	public static function can( $input = null ): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$input = (array) $input;
		foreach ( array_filter( [ (int) ( $input['id'] ?? 0 ), (int) ( $input['translation_id'] ?? 0 ) ] ) as $id ) {
			if ( get_post( $id ) && ! current_user_can( 'delete_post', $id ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return Diff|\WP_Error
	 */
	protected function preview( array $payload ) {
		$this->planned = [];
		$this->diff    = new Diff();

		$id   = (int) ( $payload['id'] ?? 0 );
		$post = $this->trashable( $id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$translations = $this->polylang ? array_map( 'intval', (array) pll_get_post_translations( $id ) ) : [];
		$others       = array_values( array_diff( $translations, [ $id ] ) );
		$named        = (int) ( $payload['translation_id'] ?? 0 );
		$targets      = [ $post ];

		if ( $named > 0 ) {
			if ( ! in_array( $named, $others, true ) ) {
				return new \WP_Error( 'dovira_not_a_translation', sprintf( 'Запис %d не є перекладом запису %d (translations у get-post).', $named, $id ) );
			}
			$other = $this->trashable( $named );
			if ( is_wp_error( $other ) ) {
				return $other;
			}
			$targets[] = $other;
		}

		foreach ( $targets as $target ) {
			$this->diff->add( (int) $target->ID, $this->lang( (int) $target->ID ), 'status', (string) $target->post_status, 'trash' );
			$this->planned[] = (int) $target->ID;
		}

		foreach ( array_diff( $others, $this->planned ) as $other ) {
			$this->diff->warn( sprintf( 'Переклад цього запису (id %d) у кошик не переміщується — лише якщо назвати його в translation_id.', $other ) );
		}

		return $this->diff;
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array{written: list<int>, warnings: list<string>}|\WP_Error
	 */
	protected function write( array $payload ) {
		if ( [] === $this->planned ) {
			$diff = $this->preview( $payload );
			if ( is_wp_error( $diff ) ) {
				return $diff;
			}
		}

		$written = [];
		foreach ( $this->planned as $id ) {
			if ( ! wp_trash_post( $id ) ) {
				return new \WP_Error( 'dovira_trash_failed', sprintf( 'У кошику: %s; запис %d перемістити не вдалося.', [] === $written ? 'нічого' : implode( ', ', $written ), $id ) );
			}
			$written[] = $id;
		}

		return [
			'written'  => $written,
			'warnings' => null !== $this->diff ? $this->diff->warnings() : [],
		];
	}

	/**
	 * @return object|\WP_Error
	 */
	private function trashable( int $id ) {
		$post = UpdatePost::content_post( $id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( 'trash' === $post->post_status ) {
			return new \WP_Error( 'dovira_post_in_trash', sprintf( 'Запис %d уже в кошику.', $id ) );
		}

		return $post;
	}

	private function lang( int $id ): ?string {
		if ( ! $this->polylang ) {
			return null;
		}
		$lang = pll_get_post_language( $id );

		return is_string( $lang ) && '' !== $lang ? $lang : null;
	}
}
