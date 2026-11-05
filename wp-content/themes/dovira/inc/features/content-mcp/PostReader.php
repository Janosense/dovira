<?php

namespace dovira\ContentMcp;

/**
 * One post in the connector's shape (FEATURE.md → Interfaces):
 * `{id, type, status, lang, translations, title, slug, parent, link,
 * menu_order, modified, terms, acf, blocks}`.
 *
 * - `acf`: the post's ACF values by field name (`FieldValues`); a service's
 *   `prices[].price` is the price in this install's city (`CityPrice`).
 * - `blocks`: the post content of every type, `acf/*` blocks as
 *   `{block, fields}` read through ACF's own block meta, any other block as
 *   `{block, html}` (its serialized markup).
 */
final class PostReader {

	private bool $acf;
	private bool $polylang;

	/** The install's own `service-city` term id, looked up once. */
	private ?int $city_term = null;
	private bool $city_term_known = false;

	/**
	 * @param bool|null $acf      Whether ACF's API is there; null asks.
	 * @param bool|null $polylang Whether Polylang's API is there; null asks.
	 */
	public function __construct( ?bool $acf = null, ?bool $polylang = null ) {
		$this->acf      = $acf ?? function_exists( 'get_field_objects' );
		$this->polylang = $polylang ?? function_exists( 'pll_get_post_translations' );
	}

	/**
	 * @param object $post A `WP_Post`.
	 * @return array<string, mixed>
	 */
	public function read( object $post ): array {
		$id   = (int) $post->ID;
		$type = (string) $post->post_type;

		[ $lang, $translations ] = $this->language( $id, $type );

		return [
			'id'           => $id,
			'type'         => $type,
			'status'       => (string) $post->post_status,
			'lang'         => $lang,
			'translations' => $translations,
			'title'        => (string) $post->post_title,
			'slug'         => (string) $post->post_name,
			'parent'       => (int) $post->post_parent,
			'link'         => is_post_type_viewable( $type ) ? (string) get_permalink( $id ) : null,
			'menu_order'   => (int) $post->menu_order,
			'modified'     => (string) $post->post_modified,
			'terms'        => $this->terms( $id, $type ),
			'acf'          => $this->acf( $id, $type ),
			'blocks'       => $this->blocks( (string) $post->post_content ),
		];
	}

	/**
	 * The post's language and its translations' ids; untranslated types have neither.
	 *
	 * @return array{0: string|null, 1: object}
	 */
	public function language( int $id, string $type ): array {
		if ( ! $this->polylang || ! pll_is_translated_post_type( $type ) ) {
			return [ null, new \stdClass() ];
		}

		$lang         = pll_get_post_language( $id );
		$translations = array_map( 'intval', (array) pll_get_post_translations( $id ) );

		return [ is_string( $lang ) && '' !== $lang ? $lang : null, (object) $translations ];
	}

	/**
	 * The terms of every taxonomy in scope the type has.
	 */
	private function terms( int $id, string $type ): object {
		$terms = [];
		foreach ( array_intersect( Scope::TAXONOMIES, (array) get_object_taxonomies( $type ) ) as $taxonomy ) {
			$found = wp_get_post_terms( $id, $taxonomy );

			$terms[ $taxonomy ] = is_array( $found ) ? array_values(
				array_map(
					static fn( $term ) => [
						'id'   => (int) $term->term_id,
						'name' => (string) $term->name,
					],
					$found
				)
			) : [];
		}

		return (object) $terms;
	}

	private function acf( int $id, string $type ): object {
		if ( ! $this->acf ) {
			return new \stdClass();
		}

		$values = FieldValues::from_objects( (array) ( get_field_objects( $id ) ?: [] ) );

		if ( 'service' === $type && isset( $values['prices'] ) && is_array( $values['prices'] ) ) {
			$values['prices'] = CityPrice::rows( $values['prices'], $this->city_term() );
		}

		return (object) $values;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function blocks( string $content ): array {
		if ( '' === trim( $content ) ) {
			return [];
		}

		$blocks = [];
		foreach ( parse_blocks( $content ) as $block ) {
			$name = $block['blockName'] ?? null;

			if ( null === $name ) {
				$html = (string) ( $block['innerHTML'] ?? '' );
				if ( '' !== trim( $html ) ) {
					$blocks[] = [
						'block' => 'core/freeform',
						'html'  => $html,
					];
				}
				continue;
			}

			if ( str_starts_with( $name, 'acf/' ) ) {
				$blocks[] = [
					'block'  => $name,
					'fields' => $this->block_fields( (array) ( $block['attrs']['data'] ?? [] ) ),
				];
				continue;
			}

			$blocks[] = [
				'block' => $name,
				'html'  => serialize_block( $block ),
			];
		}

		return $blocks;
	}

	/**
	 * An ACF block's data, stored flattened like postmeta (`years_0_year` with
	 * `_years_0_year` → field key), read by name through ACF's own block meta.
	 *
	 * @param array<string, mixed> $data
	 */
	private function block_fields( array $data ): object {
		if ( ! $this->acf || [] === $data ) {
			return new \stdClass();
		}

		$block_id = 'block_dovira_mcp_' . md5( (string) wp_json_encode( $data ) );

		acf_setup_meta( $data, $block_id, true );
		try {
			$objects = (array) ( get_field_objects( $block_id ) ?: [] );
		} finally {
			acf_reset_meta( $block_id );
		}

		return (object) FieldValues::from_objects( $objects );
	}

	private function city_term(): ?int {
		if ( ! $this->city_term_known ) {
			$this->city_term       = CityPrice::term_id( DescribeSite::city( (string) get_site_url() ) );
			$this->city_term_known = true;
		}

		return $this->city_term;
	}
}
