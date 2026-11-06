<?php

namespace dovira\ContentMcp;

/**
 * ACF values in the connector's shape, by field name: whatever return format a
 * field has, an image is `{id, url, alt}`, a file `{id, url, title}`, a post
 * reference `{id, title}`, a term `{id, name}`; repeaters and groups recurse.
 * Every other type is passed through as ACF formats it.
 */
final class FieldValues {

	/**
	 * From the result of `get_field_objects()`: name => field array with `value`.
	 *
	 * @param array<string, array<string, mixed>> $objects
	 * @return array<string, mixed>
	 */
	public static function from_objects( array $objects ): array {
		$values = [];
		foreach ( $objects as $name => $field ) {
			$values[ (string) ( $field['name'] ?? $name ) ] = self::value( $field, $field['value'] ?? null );
		}

		return $values;
	}

	/**
	 * One value through its field definition.
	 *
	 * @param array<string, mixed> $field
	 */
	public static function value( array $field, mixed $value ): mixed {
		switch ( $field['type'] ?? '' ) {
			case 'image':
				return self::image( $value );
			case 'file':
				return self::file( $value );
			case 'gallery':
				return array_values( array_filter( array_map( [ self::class, 'image' ], is_array( $value ) ? $value : [] ) ) );
			case 'post_object':
			case 'relationship':
				return self::posts( $value, ! empty( $field['multiple'] ) || 'relationship' === $field['type'] );
			case 'taxonomy':
				return self::terms( $value );
			case 'repeater':
				return self::repeater( $field, $value );
			case 'group':
				return is_array( $value ) ? self::row( $field, $value ) : $value;
			default:
				return $value;
		}
	}

	/**
	 * @return array{id: int, url: string, alt: string}|null
	 */
	private static function image( mixed $value ): ?array {
		$id = self::attachment_id( $value );
		if ( null === $id ) {
			return null;
		}

		return [
			'id'  => $id,
			'url' => is_array( $value ) && isset( $value['url'] ) ? (string) $value['url'] : (string) wp_get_attachment_url( $id ),
			'alt' => is_array( $value ) && isset( $value['alt'] ) ? (string) $value['alt'] : (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
		];
	}

	/**
	 * @return array{id: int, url: string, title: string}|null
	 */
	private static function file( mixed $value ): ?array {
		$id = self::attachment_id( $value );
		if ( null === $id ) {
			return null;
		}

		return [
			'id'    => $id,
			'url'   => is_array( $value ) && isset( $value['url'] ) ? (string) $value['url'] : (string) wp_get_attachment_url( $id ),
			'title' => is_array( $value ) && isset( $value['title'] ) ? (string) $value['title'] : (string) get_the_title( $id ),
		];
	}

	/**
	 * An attachment given as an id, a URL or ACF's array.
	 */
	private static function attachment_id( mixed $value ): ?int {
		if ( is_array( $value ) ) {
			$value = $value['ID'] ?? $value['id'] ?? null;
		}
		if ( is_numeric( $value ) && (int) $value > 0 ) {
			return (int) $value;
		}
		if ( is_string( $value ) && '' !== $value ) {
			$id = (int) attachment_url_to_postid( $value );
			return $id > 0 ? $id : null;
		}

		return null;
	}

	/**
	 * @return array{id: int, title: string}|list<array{id: int, title: string}>|null
	 */
	private static function posts( mixed $value, bool $multiple ): ?array {
		$items = is_array( $value ) && array_is_list( $value ) ? $value : ( null === $value || false === $value || '' === $value ? [] : [ $value ] );

		$posts = [];
		foreach ( $items as $item ) {
			$id = is_object( $item ) ? (int) ( $item->ID ?? 0 ) : (int) $item;
			if ( $id > 0 ) {
				$posts[] = [
					'id'    => $id,
					'title' => is_object( $item ) && isset( $item->post_title ) ? (string) $item->post_title : (string) get_the_title( $id ),
				];
			}
		}

		if ( $multiple ) {
			return $posts;
		}

		return $posts[0] ?? null;
	}

	/**
	 * @return list<array{id: int, name: string}>
	 */
	private static function terms( mixed $value ): array {
		$items = is_array( $value ) ? $value : ( empty( $value ) ? [] : [ $value ] );

		$terms = [];
		foreach ( $items as $item ) {
			$term = is_object( $item ) ? $item : get_term( (int) $item );
			if ( is_object( $term ) && isset( $term->term_id ) ) {
				$terms[] = [
					'id'   => (int) $term->term_id,
					'name' => (string) $term->name,
				];
			}
		}

		return $terms;
	}

	/**
	 * @param array<string, mixed> $field
	 * @return list<array<string, mixed>>
	 */
	private static function repeater( array $field, mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		return array_values( array_map( fn( $row ) => is_array( $row ) ? self::row( $field, $row ) : $row, $value ) );
	}

	/**
	 * A repeater row or a group's value, each sub-field through its definition.
	 *
	 * @param array<string, mixed> $field
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private static function row( array $field, array $row ): array {
		$definitions = [];
		foreach ( (array) ( $field['sub_fields'] ?? [] ) as $sub ) {
			$definitions[ (string) ( $sub['name'] ?? '' ) ] = $sub;
		}

		foreach ( $row as $name => $sub_value ) {
			if ( isset( $definitions[ $name ] ) ) {
				$row[ $name ] = self::value( $definitions[ $name ], $sub_value );
			}
		}

		return $row;
	}
}
