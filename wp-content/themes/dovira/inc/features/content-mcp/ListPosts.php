<?php

namespace dovira\ContentMcp;

/**
 * Ability `dovira/list-posts`: the posts of one type in scope, newest change
 * first, filtered by search, status, language and terms; paginated.
 */
final class ListPosts {

	public const NAME = 'dovira/list-posts';

	private const STATUSES = [ 'publish', 'draft', 'pending', 'private', 'future', 'any' ];

	/** @var callable(array<string, mixed>): array{0: list<object>, 1: int} */
	private $query;
	private PostReader $reader;
	private bool $polylang;

	/**
	 * @param callable|null   $query    Runs the query args → [posts, total]; null uses WP_Query.
	 * @param PostReader|null $reader   Gives each item's language and translations.
	 * @param bool|null       $polylang Whether Polylang's API is there; null asks.
	 */
	public function __construct( ?callable $query = null, ?PostReader $reader = null, ?bool $polylang = null ) {
		$this->query    = $query ?? static function ( array $args ): array {
			$wp_query = new \WP_Query( $args );
			return [ $wp_query->posts, (int) $wp_query->found_posts ];
		};
		$this->reader   = $reader ?? new PostReader();
		$this->polylang = $polylang ?? function_exists( 'pll_is_translated_post_type' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function args(): array {
		return [
			'label'               => 'Список записів',
			'description'         => 'Записи одного типу (послуги, лікарі, вакансії, сторінки, блог; адміністратору — ще звернення, заявки й анкети донорів), найновіші зміни першими. Пошук за назвою й текстом, статус, мова (uk за замовчуванням, all — усі), рубрики/мітки/міста. Щоб побачити запис повністю — get-post.',
			'category'            => Catalogue::CATEGORY,
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'type'   => [
						'type' => 'string',
						'enum' => Scope::types(),
					],
					'search' => [ 'type' => 'string' ],
					'status' => [
						'type'    => 'string',
						'enum'    => self::STATUSES,
						'default' => 'any',
					],
					'lang'   => [
						'type'        => 'string',
						'enum'        => [ 'uk', 'ru', 'all' ],
						'default'     => 'uk',
						'description' => 'Для звернень, заявок і анкет не застосовується.',
					],
					'terms'  => [
						'type'                 => 'object',
						'description'          => 'Таксономія → id термінів, напр. {"category": [3]}.',
						'properties'           => array_fill_keys(
							Scope::TAXONOMIES,
							[
								'type'  => 'array',
								'items' => [ 'type' => 'integer' ],
							]
						),
						'additionalProperties' => false,
					],
				] + ReadTools::paging_schema(),
				'required'             => [ 'type' ],
				'additionalProperties' => false,
			],
			'output_schema'       => ReadTools::list_schema(
				[
					'id'           => [ 'type' => 'integer' ],
					'type'         => [ 'type' => 'string' ],
					'status'       => [ 'type' => 'string' ],
					'lang'         => [ 'type' => [ 'string', 'null' ] ],
					'translations' => [ 'type' => 'object' ],
					'title'        => [ 'type' => 'string' ],
					'modified'     => [ 'type' => 'string' ],
					'link'         => [ 'type' => [ 'string', 'null' ] ],
				]
			),
			'execute_callback'    => static fn( $input ) => ( new self() )->execute( (array) $input ),
			'permission_callback' => [ self::class, 'can' ],
			'meta'                => ReadTools::meta(),
		];
	}

	/**
	 * @param mixed $input
	 */
	public static function can( $input = null ): bool {
		return Scope::can_read_type( (string) ( ( (array) $input )['type'] ?? '' ) );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int}
	 */
	public function execute( array $input ): array {
		[ $posts, $total ] = ( $this->query )( $this->query_args( $input ) );
		[ $page, $per_page ] = ReadTools::paging( $input );

		$items = [];
		foreach ( $posts as $post ) {
			[ $lang, $translations ] = $this->reader->language( (int) $post->ID, (string) $post->post_type );

			$items[] = [
				'id'           => (int) $post->ID,
				'type'         => (string) $post->post_type,
				'status'       => (string) $post->post_status,
				'lang'         => $lang,
				'translations' => $translations,
				'title'        => (string) $post->post_title,
				'modified'     => (string) $post->post_modified,
				'link'         => is_post_type_viewable( (string) $post->post_type ) ? (string) get_permalink( (int) $post->ID ) : null,
			];
		}

		return [
			'items'    => $items,
			'total'    => (int) $total,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function query_args( array $input ): array {
		$type   = (string) ( $input['type'] ?? '' );
		$status = (string) ( $input['status'] ?? 'any' );
		[ $page, $per_page ] = ReadTools::paging( $input );

		$args = [
			'post_type'        => $type,
			'post_status'      => in_array( $status, self::STATUSES, true ) ? $status : 'any',
			'posts_per_page'   => $per_page,
			'paged'            => $page,
			'orderby'          => 'modified',
			'order'            => 'DESC',
			'suppress_filters' => false,
		];

		if ( isset( $input['search'] ) && '' !== $input['search'] ) {
			$args['s'] = (string) $input['search'];
		}

		if ( $this->polylang && pll_is_translated_post_type( $type ) ) {
			$lang         = (string) ( $input['lang'] ?? 'uk' );
			$args['lang'] = 'all' === $lang ? '' : $lang;
		}

		$tax_query = [];
		foreach ( (array) ( $input['terms'] ?? [] ) as $taxonomy => $ids ) {
			if ( in_array( $taxonomy, Scope::TAXONOMIES, true ) && [] !== (array) $ids ) {
				$tax_query[] = [
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => array_map( 'intval', (array) $ids ),
				];
			}
		}
		if ( [] !== $tax_query ) {
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		return $args;
	}
}
