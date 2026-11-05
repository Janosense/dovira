<?php

namespace dovira\ContentMcp;

/**
 * Ability `dovira/find-price-rows`: the price rows of the Ukrainian services
 * whose title or description contains a text, with the price in this city.
 */
final class FindPriceRows {

	public const NAME = 'dovira/find-price-rows';

	/**
	 * @return array<string, mixed>
	 */
	public static function args(): array {
		return [
			'label'               => 'Пошук рядків цін',
			'description'         => 'Рядки прайсу послуг (українські копії), у назві чи описі яких є текст, без різниці регістру. price — ціна в місті цього сайту (null — ціни немає, сайт показує телефон); per_city — рядок має окремі ціни за містами. Щоб змінити рядок — update-price-row з service.id і row_index.',
			'category'            => Catalogue::CATEGORY,
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'text' => [
						'type'        => 'string',
						'minLength'   => 1,
						'description' => 'Частина назви або опису рядка, напр. «екстрений прийом».',
					],
				] + ReadTools::paging_schema(),
				'required'             => [ 'text' ],
				'additionalProperties' => false,
			],
			'output_schema'       => ReadTools::list_schema(
				[
					'service'     => [
						'type'       => 'object',
						'properties' => [
							'id'    => [ 'type' => 'integer' ],
							'title' => [ 'type' => 'string' ],
						],
					],
					'row_index'   => [ 'type' => 'integer' ],
					'title'       => [ 'type' => 'string' ],
					'description' => [ 'type' => 'string' ],
					'price'       => [ 'type' => [ 'number', 'null' ] ],
					'per_city'    => [ 'type' => 'boolean' ],
				]
			),
			'execute_callback'    => static fn( $input ) => ( new self() )->execute( (array) $input ),
			'permission_callback' => [ self::class, 'can' ],
			'meta'                => ReadTools::meta(),
		];
	}

	public static function can(): bool {
		return Scope::can_read_type( 'service' );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int}
	 */
	public function execute( array $input, ?PriceRows $rows = null ): array {
		$found               = ( $rows ?? new PriceRows() )->find( (string) ( $input['text'] ?? '' ) );
		[ $page, $per_page ] = ReadTools::paging( $input );

		return [
			'items'    => array_slice( $found, ( $page - 1 ) * $per_page, $per_page ),
			'total'    => count( $found ),
			'page'     => $page,
			'per_page' => $per_page,
		];
	}
}
