<?php

namespace dovira\ContentMcp;

/**
 * The Russian copy of a Ukrainian post that has none (`create-translation`):
 * the source's values, overlaid by the translated ones the payload carries,
 * planned and then inserted, set to `ru` and linked to the source in
 * Polylang — the calls `inc/cli/PostCopier` makes.
 *
 * What the payload does not translate is carried over from the source: ACF
 * values raw by field key (prices with them — identical by rule), the
 * content verbatim, terms mapped to their Russian terms, the parent to its
 * translation, the page template, the featured image and the date.
 */
final class Translator {

	/** The language of every copy; Ukrainian is the source (root invariant 7). */
	public const LANG = 'ru';

	/** ACF field types whose carried value is text a person reads. */
	private const TEXT_TYPES = [ 'text', 'textarea', 'wysiwyg', 'repeater', 'group', 'link', 'flexible_content' ];

	private bool $polylang;
	private PostWriter $writer;
	private PostReader $reader;

	/**
	 * @param bool|null       $polylang Whether Polylang's API is there; null asks.
	 * @param PostWriter|null $writer   Plans the copy's values.
	 * @param PostReader|null $reader   Gives the source's values in the reader's shape.
	 */
	public function __construct( ?bool $polylang = null, ?PostWriter $writer = null, ?PostReader $reader = null ) {
		$this->polylang = $polylang ?? function_exists( 'pll_save_post_translations' );
		$this->reader   = $reader ?? new PostReader( null, $this->polylang );
		$this->writer   = $writer ?? new PostWriter( null, $this->polylang, $this->reader );
	}

	/**
	 * @param array<string, mixed> $input `id` (the source), `title`, `status`, and the translated `FIELDS`.
	 * @return array{diff: Diff, apply: callable(): (int|\WP_Error)}|\WP_Error
	 */
	public function plan( array $input ) {
		if ( ! $this->polylang ) {
			return new \WP_Error( 'dovira_polylang_missing', 'Polylang не активний — перекладів на цьому сайті немає.' );
		}

		$id     = (int) ( $input['id'] ?? 0 );
		$source = UpdatePost::content_post( $id );
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		$type = (string) $source->post_type;

		if ( 'trash' === $source->post_status ) {
			return new \WP_Error( 'dovira_post_in_trash', sprintf( 'Запис %d у кошику; спершу відновіть його у wp-admin.', $id ) );
		}

		$lang = pll_get_post_language( $id );
		if ( PostWriter::SOURCE_LANG !== $lang ) {
			$uk = (int) pll_get_post( $id, PostWriter::SOURCE_LANG );
			return new \WP_Error(
				'dovira_not_source',
				$uk > 0
					? sprintf( 'Запис %d — не українська версія; перекладають українську (id %d).', $id, $uk )
					: sprintf( 'Запис %d — не українська версія, а перекладають лише українську.', $id )
			);
		}

		$existing = (int) pll_get_post( $id, self::LANG );
		if ( $existing > 0 ) {
			return new \WP_Error( 'dovira_translation_exists', sprintf( 'Російська копія вже є (id %d) — змінюйте її через dovira-update-post.', $existing ) );
		}

		if ( ! isset( $input['title'] ) || '' === trim( (string) $input['title'] ) ) {
			return new \WP_Error( 'dovira_title_required', 'Потрібен російський заголовок (title).' );
		}
		if ( ! isset( $input['status'] ) || '' === (string) $input['status'] ) {
			return new \WP_Error( 'dovira_status_required', 'Потрібен статус (status): ' . implode( ', ', PostWriter::STATUSES ) . ' — без значення за замовчуванням, як попросила людина.' );
		}

		$read = $this->reader->read( $source );
		$diff = new Diff();
		$diff->add( null, self::LANG, 'post_type', null, $type );

		$changes = [
			'title'  => (string) $input['title'],
			'status' => (string) $input['status'],
			'slug'   => isset( $input['slug'] ) && '' !== (string) $input['slug'] ? (string) $input['slug'] : self::slug( (string) $input['title'] ),
		];

		if ( array_key_exists( 'menu_order', $input ) ) {
			$changes['menu_order'] = (int) $input['menu_order'];
		} elseif ( 0 !== (int) $source->menu_order ) {
			$changes['menu_order'] = (int) $source->menu_order;
		}

		$parent = $this->parent( $source, $input, $diff );
		if ( is_wp_error( $parent ) ) {
			return $parent;
		}
		if ( null !== $parent ) {
			$changes['parent'] = $parent;
		}

		$terms = $this->terms( (array) $read['terms'], (array) ( $input['terms'] ?? [] ), $diff );
		if ( [] !== $terms ) {
			$changes['terms'] = $terms;
		}

		// A service's price rows are the source's, with only their texts
		// translated (`prices()`); any other field goes through the writer.
		$acf      = (array) ( $input['acf'] ?? [] );
		$has_rows = 'service' === $type && array_key_exists( 'prices', $acf );
		$rows     = $has_rows ? (array) $acf['prices'] : [];
		if ( 'service' === $type ) {
			unset( $acf['prices'] );
		}
		if ( [] !== $acf ) {
			$changes['acf'] = $acf;
		}
		if ( array_key_exists( 'blocks', $input ) ) {
			$changes['blocks'] = $input['blocks'];
		}

		$stub = (object) [
			'ID'           => 0,
			'post_type'    => $type,
			'post_status'  => 'auto-draft',
			'post_title'   => '',
			'post_name'    => '',
			'post_parent'  => 0,
			'menu_order'   => 0,
			'post_content' => (string) $source->post_content,
		];

		$planned = $this->writer->plan_new( $stub, $changes, self::LANG, $diff, true );
		if ( is_wp_error( $planned ) ) {
			return $planned;
		}

		$postarr = [ 'post_type' => $type ] + $planned['postarr'] + $this->carried_post( $source, $diff );

		$carried = $this->carried_fields( $source, (array) $read['acf'], $acf, $rows, $has_rows, $diff );
		if ( is_wp_error( $carried ) ) {
			return $carried;
		}

		$untranslated = $carried['untranslated'];
		if ( ! array_key_exists( 'blocks', $input ) && '' !== trim( (string) $source->post_content ) ) {
			$untranslated[] = 'blocks (увесь вміст)';
		}
		if ( [] !== $untranslated ) {
			$diff->warn( sprintf( 'Перенесено з української без перекладу: %s. Якщо це має бути російською — передайте переклад і покажіть новий перегляд.', implode( ', ', $untranslated ) ) );
		}

		$fields       = $planned['fields'] + $carried['fields'];
		$terms        = $planned['terms'];
		$translations = array_map( 'intval', (array) pll_get_post_translations( $id ) );

		return [
			'diff'  => $diff,
			'apply' => static function () use ( $postarr, $fields, $terms, $translations, $id ) {
				$copy = wp_insert_post( wp_slash( $postarr ), true );
				if ( is_wp_error( $copy ) ) {
					return $copy;
				}
				$copy = (int) $copy;

				pll_set_post_language( $copy, self::LANG );
				pll_save_post_translations( [ PostWriter::SOURCE_LANG => $id ] + $translations + [ self::LANG => $copy ] );

				foreach ( $fields as $key => $value ) {
					update_field( (string) $key, $value, $copy );
				}
				foreach ( $terms as $taxonomy => $ids ) {
					$set = wp_set_object_terms( $copy, $ids, (string) $taxonomy, false );
					if ( is_wp_error( $set ) ) {
						return new \WP_Error( $set->get_error_code(), sprintf( 'Копію %d створено, але рубрики не додано: %s', $copy, $set->get_error_message() ) );
					}
				}

				return $copy;
			},
		];
	}

	/**
	 * The copy's parent: the payload's (a Russian post), or the translation of
	 * the source's parent; null when the type has none or there is none.
	 *
	 * @param array<string, mixed> $input
	 * @return int|null|\WP_Error
	 */
	private function parent( object $source, array $input, Diff $diff ) {
		if ( array_key_exists( 'parent', $input ) ) {
			$parent = (int) $input['parent'];
			if ( $parent > 0 && self::LANG !== pll_get_post_language( $parent ) ) {
				return new \WP_Error( 'dovira_parent_invalid', sprintf( 'Батьківський запис %d — не російський; у копії батьківський запис — російський.', $parent ) );
			}
			return $parent;
		}

		$parent = (int) $source->post_parent;
		if ( 0 === $parent || ! is_post_type_hierarchical( (string) $source->post_type ) ) {
			return null;
		}

		$translated = (int) pll_get_post( $parent, self::LANG );
		if ( $translated > 0 ) {
			return $translated;
		}

		$diff->warn( sprintf( 'Батьківський запис %d не має російської копії — копія буде без батьківського запису.', $parent ) );

		return null;
	}

	/**
	 * The copy's terms: the source's, those of a translated taxonomy mapped to
	 * their Russian terms (a term without one is left out with a warning);
	 * a taxonomy the payload names takes the payload's set.
	 *
	 * @param array<string, mixed> $source By taxonomy, `[{id, name}]`.
	 * @param array<string, mixed> $wanted By taxonomy, ids or Russian names.
	 * @return array<string, list<int|string>>
	 */
	private function terms( array $source, array $wanted, Diff $diff ): array {
		$terms = [];

		foreach ( $source as $taxonomy => $items ) {
			$taxonomy = (string) $taxonomy;
			if ( array_key_exists( $taxonomy, $wanted ) ) {
				continue;
			}

			$ids = [];
			foreach ( (array) $items as $item ) {
				$item = (array) $item;
				$term = (int) ( $item['id'] ?? 0 );
				if ( pll_is_translated_taxonomy( $taxonomy ) ) {
					$translated = (int) pll_get_term( $term, self::LANG );
					if ( 0 === $translated ) {
						$diff->warn( sprintf( 'Термін «%s» (%s) не має російського перекладу — у копії його не буде; переклад терміна додають у wp-admin.', (string) ( $item['name'] ?? $term ), $taxonomy ) );
						continue;
					}
					$term = $translated;
				}
				$ids[] = $term;
			}

			if ( [] !== $ids ) {
				$terms[ $taxonomy ] = $ids;
			}
		}

		foreach ( $wanted as $taxonomy => $items ) {
			$terms[ (string) $taxonomy ] = array_values( (array) $items );
		}

		return $terms;
	}

	/**
	 * The page template, the featured image and the date, as `PostCopier`
	 * sets them, through `wp_insert_post` arguments.
	 *
	 * @return array<string, mixed>
	 */
	private function carried_post( object $source, Diff $diff ): array {
		$postarr = [];

		$template = (string) get_page_template_slug( $source );
		if ( '' !== $template ) {
			$postarr['page_template'] = $template;
			$diff->add( null, self::LANG, 'template', null, $template );
		}

		// Only a type with featured images has one (`page`, `post`); WordPress
		// ignores `_thumbnail_id` on the others.
		$thumbnail = post_type_supports( (string) $source->post_type, 'thumbnail' ) ? (int) get_post_thumbnail_id( $source ) : 0;
		if ( $thumbnail > 0 ) {
			$postarr['_thumbnail_id'] = $thumbnail;
			$diff->add( null, self::LANG, 'featured_image', null, $thumbnail );
		}

		// A blog archive orders by date: the copy keeps its source's.
		$gmt = (string) ( $source->post_date_gmt ?? '' );
		if ( '' !== $gmt && '0000-00-00 00:00:00' !== $gmt ) {
			$postarr['post_date']     = (string) $source->post_date;
			$postarr['post_date_gmt'] = $gmt;
			$diff->add( null, self::LANG, 'date', null, (string) $source->post_date );
		}

		return $postarr;
	}

	/**
	 * The source's ACF values the payload does not name, raw by field key, and
	 * the price rows: the source's raw rows with only the texts the payload
	 * translates (prices are the same in every language).
	 *
	 * @param array<string, mixed> $shown      The source's values, the reader's shape.
	 * @param array<string, mixed> $translated The payload's fields, without `prices`.
	 * @param array<int, mixed>    $rows       The payload's price rows.
	 * @return array{fields: array<string, mixed>, untranslated: list<string>}|\WP_Error
	 */
	private function carried_fields( object $source, array $shown, array $translated, array $rows, bool $has_rows, Diff $diff ) {
		$fields       = [];
		$untranslated = [];
		$priced       = false;

		foreach ( (array) ( get_field_objects( (int) $source->ID, false ) ?: [] ) as $name => $object ) {
			$object = (array) $object;
			$name   = (string) ( $object['name'] ?? $name );
			$key    = (string) ( $object['key'] ?? '' );
			$value  = $object['value'] ?? null;

			if ( '' === $key || array_key_exists( $name, $translated ) || in_array( $value, [ null, '', [], false ], true ) ) {
				continue;
			}

			$display = $shown[ $name ] ?? FieldValues::value( $object, $value );

			if ( 'prices' === $name && 'service' === $source->post_type ) {
				$prices = self::prices( $object, (array) $value, (array) $display, $rows, $has_rows );
				if ( is_wp_error( $prices ) ) {
					return $prices;
				}
				[ $value, $display ] = $prices;
				$priced              = true;
				if ( ! $has_rows ) {
					$untranslated[] = 'acf.prices';
				}
			} elseif ( in_array( $object['type'] ?? '', self::TEXT_TYPES, true ) ) {
				$untranslated[] = "acf.{$name}";
			}

			$fields[ $key ] = $value;
			$diff->add( null, self::LANG, "acf.{$name}", null, $display );
		}

		if ( $has_rows && ! $priced ) {
			return new \WP_Error( 'dovira_prices_invalid', 'У джерела немає рядків цін, тож перекладати нічого.' );
		}

		return [
			'fields'       => $fields,
			'untranslated' => $untranslated,
		];
	}

	/**
	 * The source's raw price rows with the translated title / description of
	 * each; a price, when given, must be the source's in this city.
	 *
	 * @param array<string, mixed> $field   The `prices` field object, raw.
	 * @param array<int, mixed>    $raw     Its rows keyed by sub-field key.
	 * @param array<int, mixed>    $display Its rows in the reader's shape (this city's price).
	 * @param array<int, mixed>    $rows    The payload's rows.
	 * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}|\WP_Error
	 */
	private static function prices( array $field, array $raw, array $display, array $rows, bool $has_rows ) {
		$raw     = array_values( array_filter( $raw, 'is_array' ) );
		$display = array_values( $display );
		if ( ! $has_rows ) {
			return [ $raw, $display ];
		}

		if ( ! array_is_list( $rows ) || count( $rows ) !== count( $raw ) ) {
			return new \WP_Error( 'dovira_prices_invalid', sprintf( 'prices у перекладі — рівно %d рядків {title, description} у порядку української копії.', count( $raw ) ) );
		}

		$keys = [];
		foreach ( (array) ( $field['sub_fields'] ?? [] ) as $sub ) {
			$keys[ (string) $sub['name'] ] = (string) $sub['key'];
		}

		foreach ( $rows as $i => $row ) {
			$row = (array) $row;
			if ( array_key_exists( 'price', $row ) && null !== $row['price'] && '' !== $row['price'] ) {
				$price = $display[ $i ]['price'] ?? null;
				if ( null === $price || ! is_numeric( $row['price'] ) || (float) $row['price'] !== (float) $price ) {
					return new \WP_Error( 'dovira_price_in_source', sprintf( 'Рядок %d: ціна копії — та сама, що в українській (%s); змінюють її в українській копії через update-price-row.', $i, null === $price ? 'без ціни' : (string) $price ) );
				}
			}
			foreach ( [ 'title', 'description' ] as $text ) {
				if ( array_key_exists( $text, $row ) && isset( $keys[ $text ] ) ) {
					$raw[ $i ][ $keys[ $text ] ] = (string) $row[ $text ];
					$display[ $i ][ $text ]      = (string) $row[ $text ];
				}
			}
		}

		return [ $raw, $display ];
	}

	/**
	 * The slug of the Russian title, transliterated by Cyr2lat with the
	 * Russian table (the site locale's lacks letters such as «ы»), as
	 * `PostCopier::get_slug()` does.
	 */
	private static function slug( string $title ): string {
		$language = function_exists( 'PLL' ) ? PLL()->model->get_language( self::LANG ) : false;
		$filter   = $language ? static fn() => $language->locale : null;

		if ( $filter ) {
			add_filter( 'ctl_locale', $filter );
		}
		$slug = sanitize_title( $title );
		if ( $filter ) {
			remove_filter( 'ctl_locale', $filter );
		}

		return $slug;
	}
}
