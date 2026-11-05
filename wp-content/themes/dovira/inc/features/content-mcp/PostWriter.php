<?php

namespace dovira\ContentMcp;

/**
 * A partial change of one post, planned and then applied: `title`, `status`,
 * `slug`, `parent`, `menu_order`, `terms`, `acf`, `blocks` in the shape
 * `PostReader` gives. `plan()` validates and computes the diff and writes
 * nothing; the returned `apply` writes exactly what the diff shows, as the
 * connected user, through the functions the admin uses (`wp_update_post`,
 * `wp_set_object_terms`, `update_field`; block data through ACF's own
 * `acf_setup_meta()` capture).
 */
final class PostWriter {

	public const FIELDS = [ 'title', 'status', 'slug', 'parent', 'menu_order', 'terms', 'acf', 'blocks' ];

	/** Statuses a change may set: `trash` is `trash-post`, `future` needs a date the connector has no input for. */
	public const STATUSES = [ 'publish', 'draft', 'pending', 'private' ];

	private bool $acf;
	private bool $polylang;
	private PostReader $reader;

	/** @var callable(object): list<string> */
	private $allowed;

	/**
	 * @param bool|null       $acf      Whether ACF's API is there; null asks.
	 * @param bool|null       $polylang Whether Polylang's API is there; null asks.
	 * @param PostReader|null $reader   Gives the current values in the reader's shape.
	 * @param callable|null   $allowed  The block names the editor allows on a post; null asks the theme.
	 */
	public function __construct( ?bool $acf = null, ?bool $polylang = null, ?PostReader $reader = null, ?callable $allowed = null ) {
		$this->acf      = $acf ?? function_exists( 'update_field' );
		$this->polylang = $polylang ?? function_exists( 'pll_get_post_language' );
		$this->reader   = $reader ?? new PostReader( $this->acf, $this->polylang );
		$this->allowed  = $allowed ?? [ self::class, 'allowed_blocks' ];
	}

	/**
	 * The blocks the editor offers on this post (root invariant 5): the
	 * theme's `acf/*` blocks, and every registered block on a `post`.
	 *
	 * @return list<string>
	 */
	public static function allowed_blocks( object $post ): array {
		$allowed = dovira_allowed_block_types( true, new \WP_Block_Editor_Context( [ 'post' => $post ] ) );

		if ( true === $allowed ) {
			return array_merge( array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() ), [ 'core/freeform' ] );
		}

		return array_values( array_map( 'strval', (array) $allowed ) );
	}

	/**
	 * @param object               $post    A `WP_Post`.
	 * @param array<string, mixed> $changes By field of `FIELDS`.
	 * @param Diff|null            $diff    The diff to add to (several targets share one).
	 * @return array{diff: Diff, apply: callable(): (true|\WP_Error)}|\WP_Error
	 */
	public function plan( object $post, array $changes, ?Diff $diff = null ) {
		$diff    = $diff ?? new Diff();
		$id      = (int) $post->ID;
		$type    = (string) $post->post_type;
		$lang    = $this->reader->language( $id, $type )[0];
		$changes = array_intersect_key( $changes, array_flip( self::FIELDS ) );

		if ( 'trash' === $post->post_status ) {
			return new \WP_Error( 'dovira_post_in_trash', sprintf( 'Запис %d у кошику; спершу відновіть його у wp-admin.', $id ) );
		}
		if ( [] === $changes ) {
			return new \WP_Error( 'dovira_nothing_to_change', 'Вкажіть, що змінити: ' . implode( ', ', self::FIELDS ) . '.' );
		}

		$postarr = [ 'ID' => $id ];
		$terms   = [];
		$fields  = [];

		$planned = $this->post_fields( $post, $changes, $lang, $diff, $postarr );
		if ( is_wp_error( $planned ) ) {
			return $planned;
		}

		if ( array_key_exists( 'terms', $changes ) ) {
			$terms = $this->terms( $post, (array) $changes['terms'], $lang, $diff );
			if ( is_wp_error( $terms ) ) {
				return $terms;
			}
		}

		if ( array_key_exists( 'acf', $changes ) ) {
			$fields = $this->acf_fields( $post, (array) $changes['acf'], $lang, $diff );
			if ( is_wp_error( $fields ) ) {
				return $fields;
			}
		}

		if ( array_key_exists( 'blocks', $changes ) ) {
			$content = $this->blocks( $post, $changes['blocks'], $lang, $diff );
			if ( is_wp_error( $content ) ) {
				return $content;
			}
			$postarr['post_content'] = $content;
		}

		return [
			'diff'  => $diff,
			'apply' => static function () use ( $id, $postarr, $terms, $fields ) {
				if ( count( $postarr ) > 1 ) {
					$updated = wp_update_post( wp_slash( $postarr ), true );
					if ( is_wp_error( $updated ) ) {
						return $updated;
					}
				}
				foreach ( $terms as $taxonomy => $ids ) {
					$set = wp_set_object_terms( $id, $ids, $taxonomy, false );
					if ( is_wp_error( $set ) ) {
						return $set;
					}
				}
				foreach ( $fields as $key => $value ) {
					update_field( $key, $value, $id );
				}
				return true;
			},
		];
	}

	/**
	 * `title`, `status`, `slug`, `parent`, `menu_order` into `$postarr`.
	 *
	 * @param array<string, mixed> $changes
	 * @param array<string, mixed> $postarr
	 * @return true|\WP_Error
	 */
	private function post_fields( object $post, array $changes, ?string $lang, Diff $diff, array &$postarr ) {
		$id     = (int) $post->ID;
		$type   = (string) $post->post_type;
		$status = (string) $post->post_status;
		$parent = (int) $post->post_parent;

		if ( array_key_exists( 'title', $changes ) ) {
			$postarr['post_title'] = (string) $changes['title'];
			$diff->add( $id, $lang, 'title', (string) $post->post_title, $postarr['post_title'] );
		}

		if ( array_key_exists( 'status', $changes ) ) {
			$status = (string) $changes['status'];
			if ( 'trash' === $status ) {
				return new \WP_Error( 'dovira_status_invalid', 'У кошик переміщує лише dovira-trash-post (адміністратор).' );
			}
			if ( ! in_array( $status, self::STATUSES, true ) ) {
				return new \WP_Error( 'dovira_status_invalid', 'Статус — один із: ' . implode( ', ', self::STATUSES ) . '.' );
			}
			if ( in_array( $status, [ 'publish', 'private' ], true ) && $status !== $post->post_status ) {
				$object = get_post_type_object( $type );
				if ( ! $object || ! current_user_can( $object->cap->publish_posts ) ) {
					return new \WP_Error( 'dovira_cannot_publish', 'Вам не можна публікувати записи цього типу.' );
				}
			}
			$postarr['post_status'] = $status;
			$diff->add( $id, $lang, 'status', (string) $post->post_status, $status );
		}

		if ( array_key_exists( 'parent', $changes ) ) {
			$parent = (int) $changes['parent'];
			$valid  = $this->parent( $post, $parent );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
			$postarr['post_parent'] = $parent;
			$diff->add( $id, $lang, 'parent', (int) $post->post_parent, $parent );
		}

		if ( array_key_exists( 'slug', $changes ) ) {
			$slug = sanitize_title( (string) $changes['slug'] );
			if ( '' === $slug ) {
				return new \WP_Error( 'dovira_slug_invalid', 'Порожній slug.' );
			}
			$slug                 = wp_unique_post_slug( $slug, $id, $status, $type, $parent );
			$postarr['post_name'] = $slug;
			$diff->add( $id, $lang, 'slug', (string) $post->post_name, $slug );
		}

		if ( array_key_exists( 'menu_order', $changes ) ) {
			$postarr['menu_order'] = (int) $changes['menu_order'];
			$diff->add( $id, $lang, 'menu_order', (int) $post->menu_order, $postarr['menu_order'] );
		}

		return true;
	}

	/**
	 * @return true|\WP_Error
	 */
	private function parent( object $post, int $parent ) {
		if ( ! is_post_type_hierarchical( (string) $post->post_type ) ) {
			return new \WP_Error( 'dovira_parent_invalid', 'У записів цього типу немає батьківського запису.' );
		}
		if ( 0 === $parent ) {
			return true;
		}

		$candidate = get_post( $parent );
		if ( ! $candidate || $candidate->post_type !== $post->post_type ) {
			return new \WP_Error( 'dovira_parent_invalid', sprintf( 'Батьківський запис %d не існує або іншого типу.', $parent ) );
		}
		if ( $parent === (int) $post->ID || in_array( (int) $post->ID, array_map( 'intval', (array) get_post_ancestors( $parent ) ), true ) ) {
			return new \WP_Error( 'dovira_parent_invalid', 'Запис не може бути батьківським сам собі чи своєму нащадку.' );
		}

		return true;
	}

	/**
	 * Each named taxonomy's whole new set of term ids.
	 *
	 * @param array<string, mixed> $wanted Taxonomy → ids or names.
	 * @return array<string, list<int>>|\WP_Error
	 */
	private function terms( object $post, array $wanted, ?string $lang, Diff $diff ) {
		$id         = (int) $post->ID;
		$taxonomies = array_intersect( Scope::TAXONOMIES, (array) get_object_taxonomies( (string) $post->post_type ) );
		$planned    = [];

		foreach ( $wanted as $taxonomy => $items ) {
			$taxonomy = (string) $taxonomy;
			if ( ! in_array( $taxonomy, $taxonomies, true ) ) {
				return new \WP_Error( 'dovira_taxonomy_invalid', sprintf( 'Таксономія %s недоступна для цього запису; є: %s.', $taxonomy, implode( ', ', $taxonomies ) ) );
			}

			$translated = $this->polylang && null !== $lang && pll_is_translated_taxonomy( $taxonomy );
			$terms      = [];
			foreach ( (array) $items as $item ) {
				$term = $this->term( $taxonomy, $item, $translated ? $lang : null );
				if ( is_wp_error( $term ) ) {
					return $term;
				}
				$terms[ (int) $term->term_id ] = [
					'id'   => (int) $term->term_id,
					'name' => (string) $term->name,
				];
			}
			ksort( $terms );

			$current = [];
			foreach ( (array) wp_get_object_terms( $id, $taxonomy ) as $term ) {
				if ( is_object( $term ) ) {
					$current[ (int) $term->term_id ] = [
						'id'   => (int) $term->term_id,
						'name' => (string) $term->name,
					];
				}
			}
			ksort( $current );

			$diff->add( $id, $lang, "terms.{$taxonomy}", array_values( $current ), array_values( $terms ) );
			$planned[ $taxonomy ] = array_keys( $terms );
		}

		return $planned;
	}

	/**
	 * An existing term by id or (not for `service-city`) by name, in the
	 * post's language when the taxonomy is translated. Nothing is created.
	 *
	 * @return object|\WP_Error
	 */
	private function term( string $taxonomy, mixed $item, ?string $lang ) {
		if ( is_array( $item ) ) {
			$item = $item['id'] ?? $item['name'] ?? '';
		}

		if ( is_numeric( $item ) ) {
			$term = get_term( (int) $item, $taxonomy );
			if ( ! is_object( $term ) || is_wp_error( $term ) ) {
				return new \WP_Error( 'dovira_term_unknown', sprintf( 'Терміна %d у %s немає.', (int) $item, $taxonomy ) );
			}
			if ( null !== $lang && pll_get_term_language( (int) $term->term_id ) !== $lang ) {
				return new \WP_Error( 'dovira_term_language', sprintf( 'Термін %d «%s» іншою мовою, ніж запис (%s).', (int) $term->term_id, $term->name, $lang ) );
			}
			return $term;
		}

		if ( 'service-city' === $taxonomy ) {
			return new \WP_Error( 'dovira_term_unknown', 'Міста послуг указують лише за id (list-terms service-city); нові міста не створюються.' );
		}

		$args = [
			'taxonomy'   => $taxonomy,
			'name'       => (string) $item,
			'hide_empty' => false,
			'number'     => 1,
		];
		if ( null !== $lang ) {
			$args['lang'] = $lang;
		}
		$found = get_terms( $args );
		if ( ! is_array( $found ) || [] === $found || ! is_object( $found[0] ) ) {
			return new \WP_Error( 'dovira_term_unknown', sprintf( 'Терміна «%s» у %s немає; створення термінів з’явиться пізніше (create-term).', (string) $item, $taxonomy ) );
		}

		return $found[0];
	}

	/**
	 * The post's own ACF fields by name → `{field_key: value to store}`.
	 *
	 * @param array<string, mixed> $values
	 * @return array<string, mixed>|\WP_Error
	 */
	private function acf_fields( object $post, array $values, ?string $lang, Diff $diff ) {
		if ( ! $this->acf ) {
			return new \WP_Error( 'dovira_acf_missing', 'ACF не активний.' );
		}

		$id   = (int) $post->ID;
		$type = (string) $post->post_type;

		if ( 'service' === $type && array_key_exists( 'prices', $values ) ) {
			return new \WP_Error( 'dovira_prices_through_price_tool', 'Ціни послуги змінює лише dovira-update-price-row (find-price-rows → update-price-row).' );
		}

		$definitions = [];
		foreach ( (array) acf_get_field_groups( [ 'post_type' => $type ] ) as $group ) {
			foreach ( (array) acf_get_fields( $group ) as $field ) {
				$definitions[] = $field;
			}
		}

		$keyed = FieldInput::keyed( $definitions, $values );
		if ( is_wp_error( $keyed ) ) {
			return $keyed;
		}

		$by_name = FieldInput::by_name( $definitions );
		foreach ( $values as $name => $value ) {
			$field = $by_name[ (string) $name ];
			$diff->add(
				$id,
				$lang,
				"acf.{$name}",
				FieldValues::value( $field, get_field( (string) $field['key'], $id ) ),
				FieldValues::value( $field, $keyed[ (string) $field['key'] ] )
			);
		}

		return $keyed;
	}

	/**
	 * The post's whole new content from blocks in the reader's shape.
	 *
	 * @return string|\WP_Error The serialized content.
	 */
	private function blocks( object $post, mixed $blocks, ?string $lang, Diff $diff ) {
		if ( ! is_array( $blocks ) || ! array_is_list( $blocks ) ) {
			return new \WP_Error( 'dovira_blocks_invalid', 'blocks — список блоків [{block, fields} | {block, html}].' );
		}

		$id      = (int) $post->ID;
		$allowed = ( $this->allowed )( $post );
		$raw     = self::parsed( (string) $post->post_content );
		$shown   = $this->reader->blocks( (string) $post->post_content );
		$written = [];

		foreach ( $blocks as $i => $block ) {
			$block = (array) $block;
			$name  = (string) ( $block['block'] ?? '' );

			if ( ! in_array( $name, $allowed, true ) ) {
				return new \WP_Error( 'dovira_block_not_allowed', sprintf( 'Блок «%s» недоступний для цього запису. Доступні: %s.', $name, implode( ', ', $allowed ) ) );
			}

			$same = isset( $raw[ $i ] ) && ( $raw[ $i ]['blockName'] ?? null ) === $name;

			if ( str_starts_with( $name, 'acf/' ) ) {
				$built = $this->acf_block( $name, (array) ( $block['fields'] ?? [] ), $same ? $raw[ $i ] : null, $same ? (array) ( $shown[ $i ]['fields'] ?? [] ) : [] );
				if ( is_wp_error( $built ) ) {
					return $built;
				}
				$written[] = $built['block'];

				if ( $same ) {
					foreach ( $built['changed'] as $field => [ $from, $to ] ) {
						$diff->add( $id, $lang, "blocks[{$i}].{$name}.{$field}", $from, $to );
					}
				} else {
					$diff->add( $id, $lang, "blocks[{$i}]", $shown[ $i ] ?? null, [ 'block' => $name, 'fields' => $built['fields'] ] );
				}
				continue;
			}

			$html   = (string) ( $block['html'] ?? '' );
			$parsed = self::markup_block( $name, $html );
			if ( is_wp_error( $parsed ) ) {
				return $parsed;
			}
			$written[] = $parsed;
			$diff->add( $id, $lang, "blocks[{$i}]", $shown[ $i ] ?? null, [ 'block' => $name, 'html' => $html ] );
		}

		for ( $i = count( $blocks ); $i < count( $shown ); $i++ ) {
			$diff->add( $id, $lang, "blocks[{$i}]", $shown[ $i ], null );
		}

		return implode( "\n\n", array_map( 'serialize_block', $written ) );
	}

	/**
	 * An `acf/*` block: the named fields over the current ones of the same
	 * block, flattened by ACF itself (`acf_setup_meta()` simulates a save of
	 * field-key values and returns the `name` / `_name` data it would store).
	 *
	 * @param array<string, mixed>      $fields  By name, the reader's shape.
	 * @param array<string, mixed>|null $current The same block now, parsed.
	 * @param array<string, mixed>      $shown   Its fields now, the reader's shape.
	 * @return array{block: array<string, mixed>, fields: array<string, mixed>, changed: array<string, array{0: mixed, 1: mixed}>}|\WP_Error
	 */
	private function acf_block( string $name, array $fields, ?array $current, array $shown ) {
		if ( ! $this->acf ) {
			return new \WP_Error( 'dovira_acf_missing', 'ACF не активний.' );
		}

		$definitions = (array) acf_get_block_fields( [ 'name' => $name ] );
		$by_name     = FieldInput::by_name( $definitions );

		// Named fields are converted from the reader's shape; the others go
		// back exactly as stored (unformatted), so an edit of one field never
		// rewrites another (a wysiwyg's formatting, a true_false's '1').
		$keep = null !== $current ? $this->stored_block_fields( (array) ( $current['attrs']['data'] ?? [] ) ) : [];
		$keep = array_intersect_key( array_diff_key( $keep, $fields ), $by_name );

		$keyed = FieldInput::keyed( $definitions, $fields );
		if ( is_wp_error( $keyed ) ) {
			return $keyed;
		}
		foreach ( $keep as $field => $value ) {
			$keyed[ (string) $by_name[ (string) $field ]['key'] ] = $value;
		}

		// In the order of the block's fields, as ACF itself saves them.
		$ordered = [];
		foreach ( $by_name as $definition ) {
			$key = (string) $definition['key'];
			if ( array_key_exists( $key, $keyed ) ) {
				$ordered[ $key ] = $keyed[ $key ];
			}
		}
		$keyed = $ordered;

		$block_id = 'block_dovira_mcp_' . md5( $name . (string) wp_json_encode( $keyed ) );
		try {
			$data = [] === $keyed ? [] : (array) acf_setup_meta( $keyed, $block_id, true );
		} finally {
			acf_reset_meta( $block_id );
		}

		$attrs         = null !== $current ? (array) ( $current['attrs'] ?? [] ) : [ 'name' => $name, 'mode' => 'preview' ];
		$attrs['name'] = $name;
		$attrs['data'] = $data;

		$changed = [];
		foreach ( $fields as $field => $value ) {
			$changed[ (string) $field ] = [
				$shown[ $field ] ?? null,
				FieldValues::value( $by_name[ (string) $field ], $keyed[ (string) $by_name[ (string) $field ]['key'] ] ),
			];
		}

		return [
			'block'   => [
				'blockName'    => $name,
				'attrs'        => $attrs,
				'innerBlocks'  => [],
				'innerHTML'    => '',
				'innerContent' => [],
			],
			'fields'  => array_merge( $shown, $fields ),
			'changed' => $changed,
		];
	}

	/**
	 * A block's stored data by field name, unformatted, read through ACF's
	 * block meta (the reader's way, without formatting).
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function stored_block_fields( array $data ): array {
		if ( [] === $data ) {
			return [];
		}

		$block_id = 'block_dovira_mcp_' . md5( (string) wp_json_encode( $data ) );
		acf_setup_meta( $data, $block_id, true );
		try {
			$values = get_fields( $block_id, false );
		} finally {
			acf_reset_meta( $block_id );
		}

		return is_array( $values ) ? $values : [];
	}

	/**
	 * A non-ACF block from its markup: exactly one block of that name;
	 * `core/freeform` is raw HTML.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function markup_block( string $name, string $html ) {
		if ( 'core/freeform' === $name ) {
			return [
				'blockName'    => null,
				'attrs'        => [],
				'innerBlocks'  => [],
				'innerHTML'    => $html,
				'innerContent' => [ $html ],
			];
		}

		$parsed = self::parsed( $html );
		if ( 1 !== count( $parsed ) || ( $parsed[0]['blockName'] ?? null ) !== $name ) {
			return new \WP_Error( 'dovira_block_markup_invalid', sprintf( 'html блоку «%s» має бути розміткою рівно одного такого блоку (як у get-post).', $name ) );
		}

		return $parsed[0];
	}

	/**
	 * `parse_blocks()` without the whitespace between blocks, the way the
	 * reader indexes them.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function parsed( string $content ): array {
		if ( '' === trim( $content ) ) {
			return [];
		}

		return array_values(
			array_filter(
				parse_blocks( $content ),
				static fn( $block ) => null !== ( $block['blockName'] ?? null ) || '' !== trim( (string) ( $block['innerHTML'] ?? '' ) )
			)
		);
	}
}
