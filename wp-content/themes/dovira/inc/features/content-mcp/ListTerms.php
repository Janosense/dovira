<?php

namespace dovira\ContentMcp;

/**
 * Ability `dovira/list-terms`: the terms of `category`, `post_tag` or
 * `service-city` (read only), by name; paginated.
 */
final class ListTerms {

	public const NAME = 'dovira/list-terms';

	private bool $polylang;

	/**
	 * @param bool|null $polylang Whether Polylang's API is there; null asks.
	 */
	public function __construct( ?bool $polylang = null ) {
		$this->polylang = $polylang ?? function_exists( 'pll_is_translated_taxonomy' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function args(): array {
		return [
			'label'               => 'Рубрики, мітки й міста',
			'description'         => 'Терміни таксономії: category (рубрики блогу), post_tag (мітки), service-city (міста послуг — лише читання). Пошук за назвою; мова для рубрик і міток (uk за замовчуванням, all — усі).',
			'category'            => Catalogue::CATEGORY,
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'taxonomy' => [
						'type' => 'string',
						'enum' => Scope::TAXONOMIES,
					],
					'search'   => [ 'type' => 'string' ],
					'lang'     => [
						'type'    => 'string',
						'enum'    => [ 'uk', 'ru', 'all' ],
						'default' => 'uk',
					],
				] + ReadTools::paging_schema(),
				'required'             => [ 'taxonomy' ],
				'additionalProperties' => false,
			],
			'output_schema'       => ReadTools::list_schema(
				[
					'id'     => [ 'type' => 'integer' ],
					'name'   => [ 'type' => 'string' ],
					'slug'   => [ 'type' => 'string' ],
					'parent' => [ 'type' => 'integer' ],
					'count'  => [ 'type' => 'integer' ],
					'lang'   => [ 'type' => [ 'string', 'null' ] ],
				]
			),
			'execute_callback'    => static fn( $input ) => ( new self() )->execute( (array) $input ),
			'permission_callback' => [ self::class, 'can' ],
			'meta'                => ReadTools::meta(),
		];
	}

	/**
	 * The taxonomy's own `assign_terms` capability (editors and administrators).
	 *
	 * @param mixed $input
	 */
	public static function can( $input = null ): bool {
		$taxonomy = (string) ( ( (array) $input )['taxonomy'] ?? '' );
		if ( ! in_array( $taxonomy, Scope::TAXONOMIES, true ) ) {
			return false;
		}

		$object = get_taxonomy( $taxonomy );

		return $object && current_user_can( $object->cap->assign_terms );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int}
	 */
	public function execute( array $input ): array {
		$taxonomy = (string) ( $input['taxonomy'] ?? '' );
		[ $page, $per_page ] = ReadTools::paging( $input );

		$args = [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'lang'       => '',
		];
		if ( $this->polylang && pll_is_translated_taxonomy( $taxonomy ) ) {
			$lang         = (string) ( $input['lang'] ?? 'uk' );
			$args['lang'] = 'all' === $lang ? '' : $lang;
		}
		if ( isset( $input['search'] ) && '' !== $input['search'] ) {
			$args['search'] = (string) $input['search'];
		}

		$terms = get_terms(
			$args + [
				'number' => $per_page,
				'offset' => ( $page - 1 ) * $per_page,
			]
		);
		$total = wp_count_terms( $args );

		return [
			'items'    => is_array( $terms ) ? array_map( fn( $term ) => Schema::term( $term, $this->polylang ), array_values( $terms ) ) : [],
			'total'    => is_numeric( $total ) ? (int) $total : 0,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}
}
