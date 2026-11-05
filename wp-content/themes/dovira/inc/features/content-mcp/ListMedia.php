<?php

namespace dovira\ContentMcp;

/**
 * Ability `dovira/list-media`: the media library, newest first, by search and
 * mime type; paginated.
 */
final class ListMedia {

	public const NAME = 'dovira/list-media';

	/** @var callable(array<string, mixed>): array{0: list<object>, 1: int} */
	private $query;

	/**
	 * @param callable|null $query Runs the query args → [attachments, total]; null uses WP_Query.
	 */
	public function __construct( ?callable $query = null ) {
		$this->query = $query ?? static function ( array $args ): array {
			$wp_query = new \WP_Query( $args );
			return [ $wp_query->posts, (int) $wp_query->found_posts ];
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function args(): array {
		return [
			'label'               => 'Медіатека',
			'description'         => 'Файли медіатеки, найновіші першими: id, назва, адреса, alt, тип, розміри. Пошук за назвою; mime — напр. image або application/pdf.',
			'category'            => Catalogue::CATEGORY,
			'input_schema'        => [
				'type'                 => 'object',
				'properties'           => [
					'search' => [ 'type' => 'string' ],
					'mime'   => [ 'type' => 'string' ],
				] + ReadTools::paging_schema(),
				'additionalProperties' => false,
				'default'              => [],
			],
			'output_schema'       => ReadTools::list_schema(
				[
					'id'     => [ 'type' => 'integer' ],
					'title'  => [ 'type' => 'string' ],
					'url'    => [ 'type' => 'string' ],
					'alt'    => [ 'type' => 'string' ],
					'mime'   => [ 'type' => 'string' ],
					'width'  => [ 'type' => [ 'integer', 'null' ] ],
					'height' => [ 'type' => [ 'integer', 'null' ] ],
					'date'   => [ 'type' => 'string' ],
				]
			),
			'execute_callback'    => static fn( $input ) => ( new self() )->execute( (array) $input ),
			'permission_callback' => [ self::class, 'can' ],
			'meta'                => ReadTools::meta(),
		];
	}

	public static function can(): bool {
		return current_user_can( 'upload_files' );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int}
	 */
	public function execute( array $input ): array {
		[ $page, $per_page ] = ReadTools::paging( $input );

		$args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];
		if ( isset( $input['search'] ) && '' !== $input['search'] ) {
			$args['s'] = (string) $input['search'];
		}
		if ( isset( $input['mime'] ) && '' !== $input['mime'] ) {
			$args['post_mime_type'] = (string) $input['mime'];
		}

		[ $attachments, $total ] = ( $this->query )( $args );

		$items = [];
		foreach ( $attachments as $attachment ) {
			$id   = (int) $attachment->ID;
			$meta = wp_get_attachment_metadata( $id );

			$items[] = [
				'id'     => $id,
				'title'  => (string) $attachment->post_title,
				'url'    => (string) wp_get_attachment_url( $id ),
				'alt'    => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
				'mime'   => (string) $attachment->post_mime_type,
				'width'  => is_array( $meta ) && isset( $meta['width'] ) ? (int) $meta['width'] : null,
				'height' => is_array( $meta ) && isset( $meta['height'] ) ? (int) $meta['height'] : null,
				'date'   => (string) $attachment->post_date,
			];
		}

		return [
			'items'    => $items,
			'total'    => (int) $total,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}
}
