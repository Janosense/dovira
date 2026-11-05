<?php

namespace dovira\ContentMcp;

/**
 * Ability `dovira/update-term`: a blog category's or tag's name, slug,
 * description or parent, behind the write protocol (`WriteAbility`,
 * `TermWriter`).
 */
final class UpdateTerm extends WriteAbility {

	public const NAME = 'dovira/update-term';

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
			'label'               => 'Зміна рубрики чи тегу',
			'description'         => 'Змінює рубрику або тег блогу за id (list-terms): name, slug, description, parent (лише рубрика). Міста послуг (service-city) не змінюються. Спершу без confirmation: diff і токен; після згоди — те саме з confirmation.',
			'category'            => Catalogue::CATEGORY,
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'id'          => [
						'type'    => 'integer',
						'minimum' => 1,
					],
					'name'        => [ 'type' => 'string' ],
					'slug'        => [ 'type' => 'string' ],
					'description' => [ 'type' => 'string' ],
					'parent'      => [
						'type'    => 'integer',
						'minimum' => 0,
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
	 * The term's taxonomy's `edit_terms`; a missing term or another taxonomy
	 * passes, so the agent hears why it is refused.
	 *
	 * @param mixed $input
	 */
	public static function can( $input = null ): bool {
		$term = get_term( (int) ( ( (array) $input )['id'] ?? 0 ) );
		if ( ! is_object( $term ) || is_wp_error( $term ) || ! in_array( $term->taxonomy, TermWriter::TAXONOMIES, true ) ) {
			return true;
		}
		$object = get_taxonomy( (string) $term->taxonomy );

		return is_object( $object ) && current_user_can( $object->cap->edit_terms );
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return Diff|\WP_Error
	 */
	protected function preview( array $payload ) {
		$plan = $this->writer->update( (int) ( $payload['id'] ?? 0 ), $payload );
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
