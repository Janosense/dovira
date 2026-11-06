<?php

namespace dovira\ContentMcp;

/**
 * Blog categories and tags, created and edited: `category` and `post_tag`
 * only — `service-city` is never written (root invariant 2). A new term is
 * Ukrainian when Polylang translates the taxonomy; it is not translated here.
 * `create()` / `update()` plan and return the diff; `apply` writes.
 */
final class TermWriter {

	public const TAXONOMIES = [ 'category', 'post_tag' ];

	public const FIELDS = [ 'name', 'slug', 'description', 'parent' ];

	private bool $polylang;

	public function __construct( ?bool $polylang = null ) {
		$this->polylang = $polylang ?? function_exists( 'pll_set_term_language' );
	}

	/**
	 * @param array<string, mixed> $fields `name` (required), `slug`, `description`, `parent` (categories).
	 * @return array{diff: Diff, apply: callable(): (int|\WP_Error)}|\WP_Error
	 */
	public function create( string $taxonomy, array $fields, ?Diff $diff = null ) {
		$diff = $diff ?? new Diff();

		$allowed = self::taxonomy( $taxonomy );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		$name = trim( (string) ( $fields['name'] ?? '' ) );
		if ( '' === $name ) {
			return new \WP_Error( 'dovira_term_name_required', 'Потрібна назва (name).' );
		}

		$translated = $this->translated( $taxonomy );
		$lang       = $translated ? PostWriter::SOURCE_LANG : null;
		$parent     = (int) ( $fields['parent'] ?? 0 );

		$valid = $this->parent( $taxonomy, $parent, 0, $lang );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$args = [
			'taxonomy'   => $taxonomy,
			'name'       => $name,
			'hide_empty' => false,
			'number'     => 1,
		];
		if ( 'category' === $taxonomy ) {
			$args['parent'] = $parent;
		}
		if ( null !== $lang ) {
			$args['lang'] = $lang;
		}
		$found = get_terms( $args );
		if ( is_array( $found ) && [] !== $found && is_object( $found[0] ) ) {
			return new \WP_Error( 'dovira_term_exists', sprintf( '«%s» уже є (id %d) — використайте його.', $name, (int) $found[0]->term_id ) );
		}

		$insert = [];
		$diff->add( null, $lang, 'taxonomy', null, $taxonomy );
		$diff->add( null, $lang, 'name', null, $name );
		if ( isset( $fields['slug'] ) && '' !== (string) $fields['slug'] ) {
			$insert['slug'] = sanitize_title( (string) $fields['slug'] );
			$diff->add( null, $lang, 'slug', null, $insert['slug'] );
		}
		if ( isset( $fields['description'] ) && '' !== (string) $fields['description'] ) {
			$insert['description'] = (string) $fields['description'];
			$diff->add( null, $lang, 'description', null, $insert['description'] );
		}
		if ( $parent > 0 ) {
			$insert['parent'] = $parent;
			$diff->add( null, $lang, 'parent', null, $parent );
		}

		return [
			'diff'  => $diff,
			'apply' => static function () use ( $name, $taxonomy, $insert, $lang ) {
				$created = wp_insert_term( $name, $taxonomy, $insert );
				if ( is_wp_error( $created ) ) {
					return $created;
				}
				$id = (int) $created['term_id'];
				if ( null !== $lang ) {
					pll_set_term_language( $id, $lang );
				}
				return $id;
			},
		];
	}

	/**
	 * @param array<string, mixed> $fields `name`, `slug`, `description`, `parent` (categories).
	 * @return array{diff: Diff, apply: callable(): (int|\WP_Error)}|\WP_Error
	 */
	public function update( int $id, array $fields, ?Diff $diff = null ) {
		$diff = $diff ?? new Diff();
		$term = get_term( $id );
		if ( ! is_object( $term ) || is_wp_error( $term ) ) {
			return new \WP_Error( 'dovira_term_unknown', sprintf( 'Терміна з id %d немає.', $id ) );
		}

		$taxonomy = (string) $term->taxonomy;
		$allowed  = self::taxonomy( $taxonomy );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		$fields = array_intersect_key( $fields, array_flip( self::FIELDS ) );
		if ( [] === $fields ) {
			return new \WP_Error( 'dovira_nothing_to_change', 'Вкажіть, що змінити: ' . implode( ', ', self::FIELDS ) . '.' );
		}

		$lang = null;
		if ( $this->translated( $taxonomy ) ) {
			$found = pll_get_term_language( $id );
			$lang  = is_string( $found ) && '' !== $found ? $found : null;
		}

		$update = [];
		if ( array_key_exists( 'name', $fields ) ) {
			$name = trim( (string) $fields['name'] );
			if ( '' === $name ) {
				return new \WP_Error( 'dovira_term_name_required', 'Назва не може бути порожньою.' );
			}
			$update['name'] = $name;
			$diff->add( $id, $lang, 'name', (string) $term->name, $name );
		}
		if ( array_key_exists( 'slug', $fields ) ) {
			$update['slug'] = sanitize_title( (string) $fields['slug'] );
			$diff->add( $id, $lang, 'slug', (string) $term->slug, $update['slug'] );
		}
		if ( array_key_exists( 'description', $fields ) ) {
			$update['description'] = (string) $fields['description'];
			$diff->add( $id, $lang, 'description', (string) $term->description, $update['description'] );
		}
		if ( array_key_exists( 'parent', $fields ) ) {
			$parent = (int) $fields['parent'];
			$valid  = $this->parent( $taxonomy, $parent, $id, $lang );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
			$update['parent'] = $parent;
			$diff->add( $id, $lang, 'parent', (int) $term->parent, $parent );
		}

		return [
			'diff'  => $diff,
			'apply' => static function () use ( $id, $taxonomy, $update ) {
				$updated = wp_update_term( $id, $taxonomy, $update );
				return is_wp_error( $updated ) ? $updated : $id;
			},
		];
	}

	/**
	 * @return true|\WP_Error
	 */
	private static function taxonomy( string $taxonomy ) {
		if ( ! in_array( $taxonomy, self::TAXONOMIES, true ) ) {
			return new \WP_Error( 'dovira_taxonomy_invalid', sprintf( 'Конектор створює й змінює лише рубрики (category) і теги (post_tag), не %s.', '' !== $taxonomy ? $taxonomy : '—' ) );
		}

		return true;
	}

	/**
	 * A parent is a category of the same language, not the term itself or its descendant.
	 *
	 * @return true|\WP_Error
	 */
	private function parent( string $taxonomy, int $parent, int $id, ?string $lang ) {
		if ( 0 === $parent ) {
			return true;
		}
		if ( 'category' !== $taxonomy ) {
			return new \WP_Error( 'dovira_parent_invalid', 'Батьківський термін буває лише в рубрик (category).' );
		}

		$term = get_term( $parent, 'category' );
		if ( ! is_object( $term ) || is_wp_error( $term ) ) {
			return new \WP_Error( 'dovira_parent_invalid', sprintf( 'Рубрики з id %d немає.', $parent ) );
		}
		if ( null !== $lang && pll_get_term_language( $parent ) !== $lang ) {
			return new \WP_Error( 'dovira_parent_invalid', sprintf( 'Рубрика %d іншою мовою.', $parent ) );
		}
		if ( $id > 0 && ( $parent === $id || in_array( $id, array_map( 'intval', (array) get_ancestors( $parent, 'category', 'taxonomy' ) ), true ) ) ) {
			return new \WP_Error( 'dovira_parent_invalid', 'Рубрика не може бути батьківською сама собі чи своїй підрубриці.' );
		}

		return true;
	}

	private function translated( string $taxonomy ): bool {
		return $this->polylang && pll_is_translated_taxonomy( $taxonomy );
	}
}
