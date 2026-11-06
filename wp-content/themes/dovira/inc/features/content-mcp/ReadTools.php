<?php

namespace dovira\ContentMcp;

/**
 * What every read ability shares: its meta (readonly, kept off the adapter's
 * default server, reachable over REST) and paging (every list is paginated and
 * capped).
 */
final class ReadTools {

	public const PER_PAGE_MAX = 50;
	public const PER_PAGE_DEFAULT = 20;

	/**
	 * @return array<string, mixed>
	 */
	public static function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
			'mcp'          => [ 'public' => false ],
		];
	}

	/**
	 * The `page` / `per_page` input properties.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function paging_schema(): array {
		return [
			'page'     => [
				'type'        => 'integer',
				'minimum'     => 1,
				'default'     => 1,
				'description' => 'Сторінка результатів, від 1.',
			],
			'per_page' => [
				'type'        => 'integer',
				'minimum'     => 1,
				'maximum'     => self::PER_PAGE_MAX,
				'default'     => self::PER_PAGE_DEFAULT,
				'description' => 'Скільки на сторінці, не більше ' . self::PER_PAGE_MAX . '.',
			],
		];
	}

	/**
	 * Page and per-page from the input, clamped: the schema caps `per_page` for
	 * MCP and REST, this caps a direct call too.
	 *
	 * @param array<string, mixed> $input
	 * @return array{0: int, 1: int}
	 */
	public static function paging( array $input ): array {
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
		$per_page = (int) ( $input['per_page'] ?? self::PER_PAGE_DEFAULT );

		return [ $page, min( self::PER_PAGE_MAX, max( 1, $per_page ) ) ];
	}

	/**
	 * The output schema of a paginated list whose items are objects.
	 *
	 * @param array<string, mixed> $item_properties
	 * @return array<string, mixed>
	 */
	public static function list_schema( array $item_properties ): array {
		return [
			'type'       => 'object',
			'properties' => [
				'items'    => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => $item_properties,
					],
				],
				'total'    => [ 'type' => 'integer' ],
				'page'     => [ 'type' => 'integer' ],
				'per_page' => [ 'type' => 'integer' ],
			],
		];
	}
}
