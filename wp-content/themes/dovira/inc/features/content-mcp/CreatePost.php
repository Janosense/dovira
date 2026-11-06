<?php

namespace dovira\ContentMcp;

/**
 * Ability `dovira/create-post`: a new post of a content type, in Ukrainian,
 * in the status the person asked for (no default), behind the write protocol
 * (`WriteAbility`, `PostWriter::create()`). The Russian copy is
 * `create-translation`'s.
 */
final class CreatePost extends WriteAbility {

	public const NAME = 'dovira/create-post';

	private PostWriter $writer;

	/** @var (callable(): (int|\WP_Error))|null */
	private $apply = null;

	private ?Diff $diff = null;

	public function __construct( ?PostWriter $writer = null, ?callable $dump = null ) {
		parent::__construct( $dump );
		$this->writer = $writer ?? new PostWriter();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function args(): array {
		$fields = UpdatePost::fields_schema();

		return [
			'label'               => 'Новий запис',
			'description'         => 'Створює новий запис українською: post_type (service, employee, vacancy, page, post — новини лише там, де є блог), title, status — обов’язково той, який назвала людина (publish|draft|pending|private; без значення за замовчуванням), і за потреби slug, parent, menu_order, terms {taxonomy: [id або назва наявного терміна]}, acf {поле: значення у форматі get-post; обов’язкові поля типу — див. describe-site}, blocks у форматі get-post. Для нової послуги acf.prices — рядки {title, description, price, linked_page?}: загальна ціна в місті цього сайту. Нову рубрику чи тег спершу створіть через create-term. Спершу без confirmation: diff і токен; після згоди — те саме з confirmation. Російську копію — create-translation.',
			'category'            => Catalogue::CATEGORY,
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'post_type' => [
						'type' => 'string',
						'enum' => Scope::CONTENT,
					],
					'lang'      => [
						'type'        => 'string',
						'enum'        => [ PostWriter::SOURCE_LANG ],
						'description' => 'Лише uk: новий запис — українською.',
					],
				] + $fields + self::confirmation_schema(),
				'required'             => [ 'post_type', 'title', 'status' ],
				'additionalProperties' => false,
			],
			'output_schema'       => self::output_schema(),
			'execute_callback'    => static fn( $input ) => ( new self() )->run( (array) $input ),
			'permission_callback' => [ self::class, 'can' ],
			'meta'                => self::meta(),
		];
	}

	/**
	 * The type's `create_posts`, and its `publish_posts` for `publish` /
	 * `private`. A type outside the content types passes, so the agent hears
	 * why it is refused.
	 *
	 * @param mixed $input
	 */
	public static function can( $input = null ): bool {
		$input = (array) $input;
		$type  = (string) ( $input['post_type'] ?? '' );
		if ( ! in_array( $type, Scope::CONTENT, true ) ) {
			return true;
		}

		$object = get_post_type_object( $type );
		if ( ! $object || ! current_user_can( $object->cap->create_posts ) ) {
			return false;
		}

		return ! in_array( $input['status'] ?? '', [ 'publish', 'private' ], true ) || current_user_can( $object->cap->publish_posts );
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return Diff|\WP_Error
	 */
	protected function preview( array $payload ) {
		$plan = $this->writer->create( $payload );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		$this->apply = $plan['apply'];
		$this->diff  = $plan['diff'];

		return $plan['diff'];
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array{written: list<int>, warnings: list<string>}|\WP_Error
	 */
	protected function write( array $payload ) {
		if ( null === $this->apply ) {
			$diff = $this->preview( $payload );
			if ( is_wp_error( $diff ) ) {
				return $diff;
			}
		}

		$id = ( $this->apply )();
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return [
			'written'  => [ (int) $id ],
			'warnings' => null !== $this->diff ? $this->diff->warnings() : [],
		];
	}
}
