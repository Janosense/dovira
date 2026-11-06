<?php

namespace dovira\ContentMcp;

/**
 * ACF values from the connector's shape back to what ACF stores — the inverse
 * of `FieldValues`: an image, a file or a post reference given as
 * `{id, …}` (or a bare id) becomes its id, a term `{id, name}` its id;
 * repeaters and groups recurse by sub-field name (ACF's repeater and group
 * accept sub-fields by name). A repeater value replaces the whole repeater.
 */
final class FieldInput {

	/**
	 * One value through its field definition.
	 *
	 * @param array<string, mixed> $field
	 */
	public static function value( array $field, mixed $value ): mixed {
		switch ( $field['type'] ?? '' ) {
			case 'image':
			case 'file':
				return self::id( $value );
			case 'gallery':
				return self::ids( $value );
			case 'post_object':
				if ( ! empty( $field['multiple'] ) ) {
					return self::ids( $value );
				}
				return self::id( $value );
			case 'relationship':
			case 'taxonomy':
				return self::ids( $value );
			case 'repeater':
				return is_array( $value ) ? array_values( array_map( fn( $row ) => is_array( $row ) ? self::row( $field, $row ) : $row, $value ) ) : [];
			case 'group':
				return is_array( $value ) ? self::row( $field, $value ) : $value;
			default:
				return $value;
		}
	}

	/**
	 * `{name: value}` → `{field_key: value}` for the top level, each value
	 * converted; a name that is not one of the fields is an error.
	 *
	 * @param list<array<string, mixed>> $fields The field definitions (with `key`, `name`, `type`).
	 * @param array<string, mixed>       $values By field name.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function keyed( array $fields, array $values ) {
		$by_name = self::by_name( $fields );

		$unknown = array_diff( array_map( 'strval', array_keys( $values ) ), array_keys( $by_name ) );
		if ( [] !== $unknown ) {
			return new \WP_Error(
				'dovira_unknown_field',
				sprintf( 'Немає таких полів: %s. Є: %s.', implode( ', ', $unknown ), implode( ', ', array_keys( $by_name ) ) )
			);
		}

		$keyed = [];
		foreach ( $values as $name => $value ) {
			$field                           = $by_name[ (string) $name ];
			$keyed[ (string) $field['key'] ] = self::value( $field, $value );
		}

		return $keyed;
	}

	/**
	 * The field definitions by name, UI-only ones (tab, message, accordion) left out.
	 *
	 * @param list<array<string, mixed>> $fields
	 * @return array<string, array<string, mixed>>
	 */
	public static function by_name( array $fields ): array {
		$by_name = [];
		foreach ( $fields as $field ) {
			$name = (string) ( $field['name'] ?? '' );
			if ( '' === $name || in_array( $field['type'] ?? '', [ 'tab', 'message', 'accordion' ], true ) ) {
				continue;
			}
			$by_name[ $name ] = $field;
		}

		return $by_name;
	}

	/**
	 * @param array<string, mixed> $field
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private static function row( array $field, array $row ): array {
		$definitions = self::by_name( (array) ( $field['sub_fields'] ?? [] ) );

		foreach ( $row as $name => $sub_value ) {
			if ( isset( $definitions[ $name ] ) ) {
				$row[ $name ] = self::value( $definitions[ $name ], $sub_value );
			}
		}

		return $row;
	}

	private static function id( mixed $value ): int|string {
		if ( is_array( $value ) ) {
			$value = $value['id'] ?? $value['ID'] ?? null;
		} elseif ( is_object( $value ) ) {
			$value = $value->ID ?? $value->term_id ?? null;
		}

		return is_numeric( $value ) && (int) $value > 0 ? (int) $value : '';
	}

	/**
	 * @return list<int>
	 */
	private static function ids( mixed $value ): array {
		if ( null === $value || false === $value || '' === $value ) {
			return [];
		}
		$items = is_array( $value ) && array_is_list( $value ) ? $value : [ $value ];

		return array_values( array_filter( array_map( [ self::class, 'id' ], $items ), 'is_int' ) );
	}
}
