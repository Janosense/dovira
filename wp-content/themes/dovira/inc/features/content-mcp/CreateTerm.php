<?php

namespace dovira\ContentMcp;

/**
 * Ability `dovira/create-term`: a new blog category or tag, in Ukrainian,
 * behind the write protocol (`WriteAbility`, `TermWriter`).
 */
final class CreateTerm extends WriteAbility {

	public const NAME = 'dovira/create-term';

	private TermWriter $writer;

	/** @var (callable(): (int|\WP_Error))|null */
	private $apply = null;

	public function __construct( ?TermWriter $writer = null, ?callable $dump = null ) {
		parent::__construct( $dump );
		$this->writer = $writer ?? new TermWriter();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function args(): array {
		return [
			'label'               => 'Нова рубрика чи тег',
			'description'         => 'Створює рубрику (category) або тег (post_tag) блогу українською: name, за потреби slug, description, parent (лише для рубрики). Наявну назву не дублює — поверне її id. Спершу без confirmation: diff і токен; після згоди — те саме з confirmation. Потім термін можна вказати в create-post / update-post.',
			'category'            => Catalogue::CATEGORY,
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'taxonomy'    => [
						'type' => 'string',
						'enum' => TermWriter::TAXONOMIES,
					],
					'name'        => [
						'type'      => 'string',
						'minLength' => 1,
					],
					'slug'        => [ 'type' => 'string' ],
					'description' => [ 'type' => 'string' ],
					'parent'      => [
						'type'    => 'integer',
						'minimum' => 0,
					],
				] + self::confirmation_schema(),
				'required'             => [ 'taxonomy', 'name' ],
				'additionalProperties' => false,
			],
			'output_schema'       => self::output_schema(),
			'execute_callback'    => static fn( $input ) => ( new self() )->run( (array) $input ),
			'permission_callback' => [ self::class, 'can' ],
			'meta'                => self::meta(),
		];
	}

	/**
	 * The taxonomy's `manage_terms` (`manage_categories` here).
	 *
	 * @param mixed $input
	 */
	public static function can( $input = null ): bool {
		$taxonomy = (string) ( ( (array) $input )['taxonomy'] ?? '' );
		if ( ! in_array( $taxonomy, TermWriter::TAXONOMIES, true ) ) {
			return true;
		}
		$object = get_taxonomy( $taxonomy );

		return is_object( $object ) && current_user_can( $object->cap->manage_terms );
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return Diff|\WP_Error
	 */
	protected function preview( array $payload ) {
		$plan = $this->writer->create( (string) ( $payload['taxonomy'] ?? '' ), $payload );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		$this->apply = $plan['apply'];

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

		return is_wp_error( $id ) ? $id : [
			'written'  => [ (int) $id ],
			'warnings' => [],
		];
	}
}
