<?php

namespace dovira\ContentMcp;

/**
 * Ability `dovira/update-post`: a partial change of an existing post of a
 * content type — and of its translation only when the payload names it —
 * behind the write protocol (`WriteAbility`, `PostWriter`).
 */
final class UpdatePost extends WriteAbility {

	public const NAME = 'dovira/update-post';

	private PostWriter $writer;
	private bool $polylang;

	/** @var list<array{id: int, apply: callable(): (true|\WP_Error)}> */
	private array $planned = [];

	private ?Diff $diff = null;

	public function __construct( ?PostWriter $writer = null, ?callable $dump = null, ?bool $polylang = null ) {
		parent::__construct( $dump );
		$this->writer   = $writer ?? new PostWriter();
		$this->polylang = $polylang ?? function_exists( 'pll_get_post_translations' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function args(): array {
		$fields = self::fields_schema();

		return [
			'label'               => 'Зміна запису',
			'description'         => 'Змінює наявний запис (послуга, лікар, вакансія, сторінка, блог): лише передані поля — title, status (publish|draft|pending|private), slug, parent, menu_order, terms {taxonomy: [id або назва наявного терміна]} — повний новий набір, acf {поле: значення у форматі get-post; repeater замінюється цілком}, blocks — увесь вміст у форматі get-post (у блоці acf/* досить назвати змінені поля, решта лишається). Ціни послуг — лише update-price-row. Російську копію змінює лише translation {id, …} з її id, коли людина на це погодилась. Спершу без confirmation: diff і токен; після згоди — те саме з confirmation.',
			'category'            => Catalogue::CATEGORY,
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'id'          => [
						'type'    => 'integer',
						'minimum' => 1,
					],
				] + $fields + [
					'translation' => [
						'type'                 => 'object',
						'description'          => 'Переклад цього запису (id з translations у get-post) і його зміни; без нього переклад не змінюється.',
						'properties'           => [
							'id' => [
								'type'    => 'integer',
								'minimum' => 1,
							],
						] + $fields,
						'required'             => [ 'id' ],
						'additionalProperties' => false,
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
	 * `edit_post` of the post and of the named translation; a missing post
	 * passes, so the agent hears "not found".
	 *
	 * @param mixed $input
	 */
	public static function can( $input = null ): bool {
		$input = (array) $input;
		$ids   = [ (int) ( $input['id'] ?? 0 ), (int) ( ( (array) ( $input['translation'] ?? [] ) )['id'] ?? 0 ) ];

		foreach ( array_filter( $ids ) as $id ) {
			if ( get_post( $id ) && ! current_user_can( 'edit_post', $id ) ) {
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
		$post = self::content_post( $id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$targets = [ [ $post, self::changes( $payload ) ] ];

		if ( isset( $payload['translation'] ) ) {
			$translation = (array) $payload['translation'];
			$other       = (int) ( $translation['id'] ?? 0 );
			$linked      = $this->polylang ? array_map( 'intval', (array) pll_get_post_translations( $id ) ) : [];
			if ( $other === $id || ! in_array( $other, $linked, true ) ) {
				return new \WP_Error( 'dovira_not_a_translation', sprintf( 'Запис %d не є перекладом запису %d (translations у get-post).', $other, $id ) );
			}
			$other_post = self::content_post( $other );
			if ( is_wp_error( $other_post ) ) {
				return $other_post;
			}
			$targets[] = [ $other_post, self::changes( $translation ) ];
		}

		foreach ( $targets as [ $target, $changes ] ) {
			$plan = $this->writer->plan( $target, $changes, $this->diff );
			if ( is_wp_error( $plan ) ) {
				return $plan;
			}
			$this->planned[] = [
				'id'    => (int) $target->ID,
				'apply' => $plan['apply'],
			];
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
		foreach ( $this->planned as $target ) {
			$result = ( $target['apply'] )();
			if ( is_wp_error( $result ) ) {
				return new \WP_Error( $result->get_error_code(), sprintf( 'Записано: %s; на записі %d — помилка: %s', [] === $written ? 'нічого' : implode( ', ', $written ), $target['id'], $result->get_error_message() ) );
			}
			$written[] = $target['id'];
		}

		return [
			'written'  => $written,
			'warnings' => null !== $this->diff ? $this->diff->warnings() : [],
		];
	}

	/**
	 * A post of a content type; records are read only, other types out of scope.
	 *
	 * @return object|\WP_Error
	 */
	public static function content_post( int $id ) {
		$post = get_post( $id );
		if ( ! $post ) {
			return new \WP_Error( 'dovira_post_not_found', sprintf( 'Запису з id %d немає.', $id ) );
		}

		$type = (string) $post->post_type;
		if ( Scope::is_record( $type ) ) {
			return new \WP_Error( 'dovira_read_only_type', 'Звернення, заявки й анкети через конектор лише читаються.' );
		}
		if ( ! in_array( $type, Scope::CONTENT, true ) ) {
			return new \WP_Error( 'dovira_type_out_of_scope', sprintf( 'Записи типу %s конектор не змінює.', $type ) );
		}

		return $post;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	private static function changes( array $input ): array {
		return array_intersect_key( $input, array_flip( PostWriter::FIELDS ) );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function fields_schema(): array {
		return [
			'title'      => [ 'type' => 'string' ],
			'status'     => [
				'type' => 'string',
				'enum' => PostWriter::STATUSES,
			],
			'slug'       => [ 'type' => 'string' ],
			'parent'     => [
				'type'    => 'integer',
				'minimum' => 0,
			],
			'menu_order' => [ 'type' => 'integer' ],
			'terms'      => [
				'type'                 => 'object',
				'properties'           => array_fill_keys(
					Scope::TAXONOMIES,
					[
						'type'  => 'array',
						'items' => [ 'type' => [ 'integer', 'string' ] ],
					]
				),
				'additionalProperties' => false,
			],
			'acf'        => [ 'type' => 'object' ],
			'blocks'     => [
				'type'  => 'array',
				'items' => [
					'type'       => 'object',
					'properties' => [
						'block'  => [ 'type' => 'string' ],
						'fields' => [ 'type' => 'object' ],
						'html'   => [ 'type' => 'string' ],
					],
					'required'   => [ 'block' ],
				],
			],
		];
	}
}
