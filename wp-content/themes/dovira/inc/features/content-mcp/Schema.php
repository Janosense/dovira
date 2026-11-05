<?php

namespace dovira\ContentMcp;

/**
 * What `describe-site` tells the agent about the content model: the post types
 * in scope with their ACF fields, the theme's ACF blocks with theirs, and the
 * taxonomies with their terms. Built from the registered types and the ACF
 * local field groups, so a new field or block appears without a change here.
 */
final class Schema {

	/** At most this many terms per taxonomy; `terms_total` tells the rest. */
	public const TERMS_CAP = 50;

	/** How `service.prices[].price` is described: the reader resolves it per install. */
	public const CITY_PRICE_DESCRIPTION = 'Ціна в місті цього сайту (загальна ціна рядка або ціна його міста); null — ціни немає, сайт показує телефон.';

	/** Field types that only arrange the edit screen and hold no value. */
	private const UI_TYPES = [ 'message', 'tab', 'accordion' ];

	private bool $acf;
	private bool $polylang;

	/**
	 * @param bool|null $acf      Whether ACF's API is there; null asks.
	 * @param bool|null $polylang Whether Polylang's API is there; null asks.
	 *                            Tests pass booleans, since a function stubbed
	 *                            once stays declared for the whole run.
	 */
	public function __construct( ?bool $acf = null, ?bool $polylang = null ) {
		$this->acf      = $acf ?? function_exists( 'acf_get_local_field_groups' );
		$this->polylang = $polylang ?? function_exists( 'pll_is_translated_post_type' );
	}

	/**
	 * @return array{post_types: list<array<string, mixed>>, blocks: list<array<string, mixed>>, taxonomies: list<array<string, mixed>>}
	 */
	public function describe(): array {
		return [
			'post_types' => $this->post_types(),
			'blocks'     => $this->blocks(),
			'taxonomies' => $this->taxonomies(),
		];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function post_types(): array {
		$types = [];

		foreach ( Scope::types() as $type ) {
			$object = get_post_type_object( $type );
			if ( ! $object ) {
				continue;
			}

			$types[] = [
				'name'         => $type,
				'label'        => (string) $object->label,
				'hierarchical' => (bool) $object->hierarchical,
				'translated'   => $this->polylang && pll_is_translated_post_type( $type ),
				'read_only'    => Scope::is_record( $type ),
				'taxonomies'   => array_values( array_intersect( Scope::TAXONOMIES, (array) get_object_taxonomies( $type ) ) ),
				'fields'       => $this->fields_located( 'post_type', $type ),
			];
		}

		return $types;
	}

	/**
	 * The theme's `acf/*` blocks with their fields.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function blocks(): array {
		if ( ! $this->acf ) {
			return [];
		}

		$blocks = [];
		foreach ( (array) acf_get_block_types() as $name => $block ) {
			$name = (string) ( $block['name'] ?? $name );
			if ( ! str_starts_with( $name, 'acf/' ) ) {
				continue;
			}

			$blocks[] = [
				'name'   => $name,
				'title'  => (string) ( $block['title'] ?? $name ),
				'fields' => $this->fields_located( 'block', $name ),
			];
		}

		return $blocks;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function taxonomies(): array {
		$taxonomies = [];

		foreach ( Scope::TAXONOMIES as $taxonomy ) {
			$object = get_taxonomy( $taxonomy );
			if ( ! $object ) {
				continue;
			}

			$args  = [
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'lang'       => '',
			];
			$terms = get_terms( $args + [ 'number' => self::TERMS_CAP ] );
			$total = wp_count_terms( $args );

			$taxonomies[] = [
				'name'         => $taxonomy,
				'label'        => (string) $object->label,
				'object_types' => array_values( (array) $object->object_type ),
				'translated'   => $this->polylang && pll_is_translated_taxonomy( $taxonomy ),
				'read_only'    => 'service-city' === $taxonomy,
				'terms'        => is_array( $terms ) ? array_map( fn( $term ) => self::term( $term, $this->polylang ), array_values( $terms ) ) : [],
				'terms_total'  => is_numeric( $total ) ? (int) $total : 0,
			];
		}

		return $taxonomies;
	}

	/**
	 * One term (`WP_Term`) as the connector shows it everywhere.
	 *
	 * @return array{id: int, name: string, slug: string, parent: int, count: int, lang: string|null}
	 */
	public static function term( object $term, bool $polylang ): array {
		$lang = $polylang ? pll_get_term_language( $term->term_id ) : false;

		return [
			'id'     => (int) $term->term_id,
			'name'   => (string) $term->name,
			'slug'   => (string) $term->slug,
			'parent' => (int) $term->parent,
			'count'  => (int) $term->count,
			'lang'   => is_string( $lang ) && '' !== $lang ? $lang : null,
		];
	}

	/**
	 * The fields of every local group whose location names `{param} == {value}`.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function fields_located( string $param, string $value ): array {
		if ( ! $this->acf ) {
			return [];
		}

		$fields = [];
		foreach ( (array) acf_get_local_field_groups() as $group ) {
			if ( ! self::located( $group, $param, $value ) ) {
				continue;
			}

			foreach ( (array) acf_get_fields( $group['key'] ) as $field ) {
				$described = self::field( $field );
				if ( null !== $described ) {
					$fields[] = $described;
				}
			}
		}

		return 'post_type' === $param && 'service' === $value ? self::city_price( $fields ) : $fields;
	}

	/**
	 * @param array<string, mixed> $group
	 */
	private static function located( array $group, string $param, string $value ): bool {
		foreach ( (array) ( $group['location'] ?? [] ) as $rules ) {
			foreach ( (array) $rules as $rule ) {
				if ( ( $rule['param'] ?? '' ) === $param && ( $rule['operator'] ?? '' ) === '==' && ( $rule['value'] ?? '' ) === $value ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $field
	 * @return array<string, mixed>|null Null for a field that holds no value.
	 */
	private static function field( array $field ): ?array {
		$type = (string) ( $field['type'] ?? '' );
		if ( in_array( $type, self::UI_TYPES, true ) ) {
			return null;
		}

		$described = [
			'name'     => (string) ( $field['name'] ?? '' ),
			'label'    => (string) ( $field['label'] ?? '' ),
			'type'     => $type,
			'required' => ! empty( $field['required'] ),
		];

		if ( ! empty( $field['choices'] ) && is_array( $field['choices'] ) ) {
			$described['choices'] = $field['choices'];
		}

		if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
			$described['sub_fields'] = array_values( array_filter( array_map( [ self::class, 'field' ], $field['sub_fields'] ) ) );
		}

		return $described;
	}

	/**
	 * `service.prices`: the legacy per-city columns are hidden and `price`
	 * means the price in this install's city (see `CityPrice`).
	 *
	 * @param list<array<string, mixed>> $fields
	 * @return list<array<string, mixed>>
	 */
	private static function city_price( array $fields ): array {
		foreach ( $fields as $i => $field ) {
			if ( 'prices' !== $field['name'] || empty( $field['sub_fields'] ) ) {
				continue;
			}

			$sub_fields = [];
			foreach ( $field['sub_fields'] as $sub ) {
				if ( 'city_prices' === $sub['name'] ) {
					continue;
				}
				if ( 'price' === $sub['name'] ) {
					$sub['description'] = self::CITY_PRICE_DESCRIPTION;
				}
				$sub_fields[] = $sub;
			}
			$fields[ $i ]['sub_fields'] = $sub_fields;
		}

		return $fields;
	}
}
