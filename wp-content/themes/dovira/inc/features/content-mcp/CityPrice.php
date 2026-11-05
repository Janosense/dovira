<?php

namespace dovira\ContentMcp;

/**
 * The price of a `service` price row in this install's city — the site's own
 * rule (`single-service.php`, `search.php`): the row's general `price` when
 * `is_price_general`, otherwise its `city_prices.price_city_{term_id}` for the
 * install's `service-city` term. No amount → null: the site shows the city's
 * phone instead, never a made-up number (root invariant 3).
 */
final class CityPrice {

	/**
	 * The install's own `service-city` term: the one whose slug is the city of
	 * the site URL (`kharkiv` / `kyiv`). Null when the install has none.
	 */
	public static function term_id( string $city ): ?int {
		$term = get_term_by( 'slug', $city, 'service-city' );

		return is_object( $term ) && isset( $term->term_id ) ? (int) $term->term_id : null;
	}

	/**
	 * @param array<string, mixed> $row A formatted `prices` row.
	 */
	public static function resolve( array $row, ?int $term_id ): int|float|null {
		if ( ! empty( $row['is_price_general'] ) ) {
			$price = $row['price'] ?? null;
		} elseif ( null !== $term_id ) {
			$price = $row['city_prices'][ 'price_city_' . $term_id ] ?? null;
		} else {
			$price = null;
		}

		// The templates show the phone for every empty() amount, 0 included.
		if ( empty( $price ) || ! is_numeric( $price ) ) {
			return null;
		}

		return 0 + $price;
	}

	/**
	 * Every row with `price` resolved for this city and the per-city columns removed.
	 *
	 * @param list<array<string, mixed>> $rows
	 * @return list<array<string, mixed>>
	 */
	public static function rows( array $rows, ?int $term_id ): array {
		return array_map(
			static function ( $row ) use ( $term_id ) {
				if ( ! is_array( $row ) ) {
					return $row;
				}
				$row['price'] = self::resolve( $row, $term_id );
				unset( $row['city_prices'] );
				return $row;
			},
			array_values( $rows )
		);
	}
}
