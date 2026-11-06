<?php

namespace dovira\ContentMcp;

/**
 * Ability `dovira/get-post`: one post of a type in scope, whole — ACF by field
 * name, blocks, terms, language and translations (`PostReader`).
 */
final class GetPost {

	public const NAME = 'dovira/get-post';

	/**
	 * @return array<string, mixed>
	 */
	public static function args(): array {
		return [
			'label'               => 'Запис повністю',
			'description'         => 'Один запис за id: заголовок, статус, мова й id перекладу (translations.ru), рубрики/міста, усі поля ACF за назвою (у послуги prices[].price — ціна в місті цього сайту, null — ціни немає) і вміст як список блоків {block, fields} або {block, html}.',
			'category'            => Catalogue::CATEGORY,
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'id' => [
						'type'    => 'integer',
						'minimum' => 1,
					],
				],
				'required'             => [ 'id' ],
				'additionalProperties' => false,
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'id'           => [ 'type' => 'integer' ],
					'type'         => [ 'type' => 'string' ],
					'status'       => [ 'type' => 'string' ],
					'lang'         => [ 'type' => [ 'string', 'null' ] ],
					'translations' => [ 'type' => 'object' ],
					'title'        => [ 'type' => 'string' ],
					'slug'         => [ 'type' => 'string' ],
					'parent'       => [ 'type' => 'integer' ],
					'link'         => [ 'type' => [ 'string', 'null' ] ],
					'menu_order'   => [ 'type' => 'integer' ],
					'modified'     => [ 'type' => 'string' ],
					'terms'        => [ 'type' => 'object' ],
					'acf'          => [ 'type' => 'object' ],
					'blocks'       => [
						'type'  => 'array',
						'items' => [ 'type' => 'object' ],
					],
				],
			],
			'execute_callback'    => static fn( $input ) => ( new self() )->execute( (array) $input ),
			'permission_callback' => [ self::class, 'can' ],
			'meta'                => ReadTools::meta(),
		];
	}

	/**
	 * A missing post passes, so the agent hears "not found" rather than
	 * "not allowed"; an existing one needs its type in scope and readable.
	 *
	 * @param mixed $input
	 */
	public static function can( $input = null ): bool {
		$post = get_post( (int) ( ( (array) $input )['id'] ?? 0 ) );
		if ( ! $post ) {
			return true;
		}

		return Scope::can_read_type( (string) $post->post_type );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute( array $input, ?PostReader $reader = null ) {
		$id   = (int) ( $input['id'] ?? 0 );
		$post = get_post( $id );
		if ( ! $post ) {
			return new \WP_Error( 'dovira_post_not_found', sprintf( 'Запису з id %d немає.', $id ) );
		}

		return ( $reader ?? new PostReader() )->read( $post );
	}
}
