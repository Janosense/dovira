<?php

namespace dovira\ContentMcp;

/**
 * The rows of a `service`'s `prices` repeater: found by text, and changed one
 * row at a time (DECISIONS 2026-11-05 — … prices have their own tools, and a
 * price change mirrors into the Russian copy by row index).
 *
 * A row's price is the price in this install's city (`CityPrice`): the general
 * `price`, or the row's `city_prices.price_city_{term}` column of the install's
 * own `service-city` term. A change writes into that same column through
 * `update_field()` on the whole repeater, so `is_price_general`, `cities` and
 * every other value go back as they were read.
 */
final class PriceRows {

	/** The source language: prices are changed here and follow into `ru`. */
	public const SOURCE = 'uk';
	public const COPY = 'ru';

	private bool $polylang;

	/** @var callable(): list<object> */
	private $services;

	private ?int $term = null;
	private bool $term_known = false;

	/**
	 * @param bool|null     $polylang Whether Polylang's API is there; null asks.
	 * @param callable|null $services Gives the services to search; null queries the Ukrainian ones.
	 */
	public function __construct( ?bool $polylang = null, ?callable $services = null ) {
		$this->polylang = $polylang ?? function_exists( 'pll_get_post' );
		$this->services = $services ?? function (): array {
			$args = [
				'post_type'        => 'service',
				'post_status'      => [ 'publish', 'draft', 'pending', 'private', 'future' ],
				'numberposts'      => -1,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			];
			if ( $this->polylang ) {
				$args['lang'] = self::SOURCE;
			}
			return get_posts( $args );
		};
	}

	/**
	 * Every price row whose title or description contains the text, any case.
	 *
	 * @return list<array{service: array{id: int, title: string}, row_index: int, title: string, description: string, price: int|float|null, per_city: bool}>
	 */
	public function find( string $text ): array {
		$text  = trim( $text );
		$found = [];
		if ( '' === $text ) {
			return $found;
		}

		foreach ( ( $this->services )() as $service ) {
			$rows = get_field( 'prices', (int) $service->ID );
			if ( ! is_array( $rows ) ) {
				continue;
			}

			foreach ( array_values( $rows ) as $index => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$title       = (string) ( $row['title'] ?? '' );
				$description = (string) ( $row['description'] ?? '' );
				if ( false === mb_stripos( $title, $text ) && false === mb_stripos( $description, $text ) ) {
					continue;
				}

				$found[] = [
					'service'     => [
						'id'    => (int) $service->ID,
						'title' => (string) $service->post_title,
					],
					'row_index'   => $index,
					'title'       => $title,
					'description' => $description,
					'price'       => CityPrice::resolve( $row, $this->term() ),
					'per_city'    => empty( $row['is_price_general'] ),
				];
			}
		}

		return $found;
	}

	/**
	 * Plans a change of one row: the diff the preview shows and the repeaters
	 * to write. Nothing is written here.
	 *
	 * - `title` / `description`: the targeted copy only (text is per language).
	 * - `price`: the row's own column in this city; from the Ukrainian copy it
	 *   follows into the Russian copy's row of the same index when both have
	 *   as many rows — otherwise a warning and the Ukrainian copy only. A price
	 *   change on a Russian copy is refused.
	 *
	 * @param array<string, mixed> $changes `title`, `description`, `price`.
	 * @return array{diff: Diff, writes: array<int, array{key: string, rows: list<array<string, mixed>>}>}|\WP_Error
	 */
	public function update( int $service_id, int $row_index, array $changes ) {
		$post = get_post( $service_id );
		if ( ! $post ) {
			return new \WP_Error( 'dovira_post_not_found', sprintf( 'Запису з id %d немає.', $service_id ) );
		}
		if ( 'service' !== $post->post_type ) {
			return new \WP_Error( 'dovira_not_a_service', sprintf( 'Запис %d — не послуга; ціни є лише в послуг.', $service_id ) );
		}

		$changes = array_intersect_key( $changes, array_flip( [ 'title', 'description', 'price' ] ) );
		if ( [] === $changes ) {
			return new \WP_Error( 'dovira_nothing_to_change', 'Вкажіть title, description або price.' );
		}
		if ( array_key_exists( 'price', $changes ) && ( ! is_numeric( $changes['price'] ) || 0 >= $changes['price'] ) ) {
			return new \WP_Error( 'dovira_price_invalid', 'Ціна — число, більше за 0.' );
		}

		$lang = $this->lang( $service_id );
		if ( array_key_exists( 'price', $changes ) && null !== $lang && self::SOURCE !== $lang ) {
			return new \WP_Error( 'dovira_price_in_source', 'Ціну змінюють в українській копії послуги — у російську вона переноситься сама.' );
		}

		$copy = $this->repeater( $service_id );
		if ( is_wp_error( $copy ) ) {
			return $copy;
		}
		if ( ! isset( $copy['rows'][ $row_index ] ) ) {
			return new \WP_Error( 'dovira_price_row_missing', sprintf( 'У послуги %d немає рядка цін з індексом %d (рядків: %d).', $service_id, $row_index, count( $copy['rows'] ) ) );
		}

		$diff   = new Diff();
		$writes = [];
		$rows   = $copy['rows'];
		$field  = sprintf( 'prices[%d]', $row_index );

		foreach ( [ 'title', 'description' ] as $name ) {
			if ( array_key_exists( $name, $changes ) && isset( $copy['keys'][ $name ] ) ) {
				$key  = $copy['keys'][ $name ];
				$from = (string) ( $rows[ $row_index ][ $key ] ?? '' );
				$to   = (string) $changes[ $name ];
				$diff->add( $service_id, $lang, "{$field}.{$name}", $from, $to );
				$rows[ $row_index ][ $key ] = $to;
			}
		}

		if ( array_key_exists( 'price', $changes ) ) {
			$priced = $this->set_price( $copy, $rows[ $row_index ], $changes['price'] );
			if ( is_wp_error( $priced ) ) {
				return $priced;
			}
			$diff->add( $service_id, $lang, "{$field}.price", $priced['from'], $priced['to'] );
			$rows[ $row_index ] = $priced['row'];

			if ( self::SOURCE === $lang ) {
				$this->mirror( $service_id, $row_index, $changes['price'], count( $copy['rows'] ), $diff, $writes );
			}
		}

		if ( $rows !== $copy['rows'] ) {
			$writes = [
				$service_id => [
					'key'  => $copy['key'],
					'rows' => $rows,
				],
			] + $writes;
		}

		return [
			'diff'   => $diff,
			'writes' => $writes,
		];
	}

	/**
	 * Writes the planned repeaters through ACF.
	 *
	 * @param array<int, array{key: string, rows: list<array<string, mixed>>}> $writes
	 * @return list<int> The posts written.
	 */
	public function apply( array $writes ): array {
		foreach ( $writes as $post_id => $write ) {
			update_field( $write['key'], $write['rows'], $post_id );
		}

		return array_map( 'intval', array_keys( $writes ) );
	}

	/**
	 * The price into the Russian copy's row of the same index.
	 *
	 * @param int|float|string                                                  $price
	 * @param array<int, array{key: string, rows: list<array<string, mixed>>}> $writes
	 */
	private function mirror( int $service_id, int $row_index, $price, int $source_rows, Diff $diff, array &$writes ): void {
		$copy_id = (int) pll_get_post( $service_id, self::COPY );
		if ( $copy_id <= 0 || $copy_id === $service_id ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $copy_id ) ) {
			$diff->warn( sprintf( 'Російську копію (id %d) вам не можна редагувати — ціну змінено лише в українській.', $copy_id ) );
			return;
		}

		$copy = $this->repeater( $copy_id );
		if ( is_wp_error( $copy ) || count( $copy['rows'] ) !== $source_rows ) {
			$diff->warn(
				sprintf(
					'Російська копія (id %d) має %d рядків цін, українська — %d: ціну змінено лише в українській, російську перевірте вручну.',
					$copy_id,
					is_wp_error( $copy ) ? 0 : count( $copy['rows'] ),
					$source_rows
				)
			);
			return;
		}

		$priced = $this->set_price( $copy, $copy['rows'][ $row_index ], $price );
		if ( is_wp_error( $priced ) ) {
			$diff->warn( sprintf( 'Російська копія (id %d): %s Ціну змінено лише в українській.', $copy_id, $priced->get_error_message() ) );
			return;
		}

		$diff->add( $copy_id, self::COPY, sprintf( 'prices[%d].price', $row_index ), $priced['from'], $priced['to'] );

		$rows               = $copy['rows'];
		$rows[ $row_index ] = $priced['row'];
		if ( $rows !== $copy['rows'] ) {
			$writes[ $copy_id ] = [
				'key'  => $copy['key'],
				'rows' => $rows,
			];
		}
	}

	/**
	 * The row with its price in this city set: `price` when the row is
	 * general, otherwise the install's own city column.
	 *
	 * @param array{key: string, keys: array<string, string>, columns: array<string, string>, rows: list<array<string, mixed>>} $copy
	 * @param array<string, mixed>                                                                                                  $row
	 * @param int|float|string                                                                                                      $price
	 * @return array{row: array<string, mixed>, from: int|float|null, to: int|float}|\WP_Error
	 */
	private function set_price( array $copy, array $row, $price ) {
		$keys    = $copy['keys'];
		$general = ! empty( $row[ $keys['is_price_general'] ?? '' ] );
		$term    = $this->term();
		$to      = 0 + $price;
		$value   = (string) $to;

		$by_name = [
			'is_price_general' => $general,
			'price'            => $row[ $keys['price'] ?? '' ] ?? null,
			'city_prices'      => [],
		];

		if ( $general ) {
			if ( ! isset( $keys['price'] ) ) {
				return new \WP_Error( 'dovira_price_field_missing', 'У рядка немає поля price.' );
			}
			$row[ $keys['price'] ] = $value;
		} else {
			$column = null !== $term ? ( $copy['columns'][ 'price_city_' . $term ] ?? null ) : null;
			if ( null === $column || ! isset( $keys['city_prices'] ) ) {
				return new \WP_Error( 'dovira_city_term_missing', 'Рядок має ціни за містами, а місто цього сайту не знайдено серед міст послуг — ціну не змінено.' );
			}
			$group = $row[ $keys['city_prices'] ] ?? [];
			$group = is_array( $group ) ? $group : [];

			$by_name['city_prices'][ 'price_city_' . $term ] = $group[ $column ] ?? null;

			$group[ $column ]             = $value;
			$row[ $keys['city_prices'] ] = $group;
		}

		return [
			'row'  => $row,
			'from' => CityPrice::resolve( $by_name, $term ),
			'to'   => $to,
		];
	}

	/**
	 * The repeater of one service, raw: its field key, the sub-field keys by
	 * name, the city columns by name and the rows keyed by field key.
	 *
	 * @return array{key: string, keys: array<string, string>, columns: array<string, string>, rows: list<array<string, mixed>>}|\WP_Error
	 */
	private function repeater( int $post_id ) {
		$field = get_field_object( 'prices', $post_id, false, false );
		if ( ! is_array( $field ) || empty( $field['key'] ) ) {
			return new \WP_Error( 'dovira_prices_missing', sprintf( 'У послуги %d немає поля prices.', $post_id ) );
		}

		$keys    = [];
		$columns = [];
		foreach ( (array) ( $field['sub_fields'] ?? [] ) as $sub ) {
			$keys[ (string) $sub['name'] ] = (string) $sub['key'];
			if ( 'city_prices' === $sub['name'] ) {
				foreach ( (array) ( $sub['sub_fields'] ?? [] ) as $column ) {
					$columns[ (string) $column['name'] ] = (string) $column['key'];
				}
			}
		}

		$rows = get_field( (string) $field['key'], $post_id, false );

		return [
			'key'     => (string) $field['key'],
			'keys'    => $keys,
			'columns' => $columns,
			'rows'    => is_array( $rows ) ? array_values( array_filter( $rows, 'is_array' ) ) : [],
		];
	}

	private function lang( int $post_id ): ?string {
		if ( ! $this->polylang ) {
			return null;
		}
		$lang = pll_get_post_language( $post_id );

		return is_string( $lang ) && '' !== $lang ? $lang : null;
	}

	private function term(): ?int {
		if ( ! $this->term_known ) {
			$this->term       = CityPrice::term_id( DescribeSite::city( (string) get_site_url() ) );
			$this->term_known = true;
		}

		return $this->term;
	}
}
