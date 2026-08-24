<?php
/**
 * Yoast SEO bulk importer for the dovira.vet network (Kyiv / Kharkiv).
 * Multilingual layer: Polylang. Default language: uk. Second language: ru.
 *
 * Scope: fills in Yoast SEO title, meta description and keyphrases only.
 * It never creates, renames or redirects anything — slugs and URLs are read-only here.
 *
 * Page lookup (deliberately just two steps):
 *   uk — exact permalink match on the URL from the spreadsheet, which is authoritative
 *   ru — pll_get_post( $uk_id, 'ru' ), i.e. the Polylang translation link
 * The `url` field of the ru block in the JSON is informational only and is never
 * used for lookup, because ru slugs on the site are transliterated and do not
 * match the spreadsheet.
 *
 * Runs only through WP-CLI, never via a web request.
 *
 *   wp eval-file yoast-import.php resolve dovira-seo-kyiv.json
 *   wp eval-file yoast-import.php dry     dovira-seo-kyiv.json
 *   wp eval-file yoast-import.php apply   dovira-seo-kyiv.json
 *   wp eval-file yoast-import.php verify  dovira-seo-kyiv.json
 *
 * Reports go to wp-content/uploads/yoast-import/.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( "This script must be run through WP-CLI.\n" );
}

// -----------------------------------------------------------------------------
// Config
// -----------------------------------------------------------------------------

// Constants, not variables: `wp eval-file` includes this file inside a function,
// so top-level variables are NOT global and $GLOBALS would be empty.
define( 'DV_META_TITLE',   '_yoast_wpseo_title' );
define( 'DV_META_DESC',    '_yoast_wpseo_metadesc' );
define( 'DV_META_FOCUS',   '_yoast_wpseo_focuskw' );
define( 'DV_META_RELATED', '_yoast_wpseo_focuskeywords' ); // Yoast Premium only

define( 'DV_WRITE_FOCUS',   true ); // false = leave focus keyphrases untouched
define( 'DV_WRITE_RELATED', true ); // auto-skipped when Premium is not active

// Every public post type except attachments. Service pages on this site live in
// a custom post type, so hardcoding array('page','post') silently loses them.
$dv_post_types = array_values( array_diff(
	get_post_types( array( 'public' => true ), 'names' ),
	array( 'attachment' )
) );

// -----------------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------------

function dv_lower( $s ) {
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
}

/** Normalise any URL or path into a comparable key: "/services/radiology/". */
function dv_path( $url ) {
	$url  = trim( (string) $url );
	$path = parse_url( $url, PHP_URL_PATH );
	if ( $path === null || $path === false ) {
		$path = $url;
	}
	$path = rawurldecode( $path );
	$path = '/' . ltrim( $path, '/' );
	if ( substr( $path, -1 ) !== '/' ) {
		$path .= '/';
	}
	return dv_lower( $path );
}

function dv_report_dir() {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'yoast-import';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	return trailingslashit( $dir );
}

function dv_csv_open( $name ) {
	$path = dv_report_dir() . $name;
	$fh   = fopen( $path, 'w' );
	fwrite( $fh, "\xEF\xBB\xBF" ); // BOM so Excel opens UTF-8 correctly
	return array( $fh, $path );
}

function dv_is_premium() {
	return defined( 'WPSEO_PREMIUM_FILE' ) || class_exists( 'WPSEO_Premium' );
}

function dv_post_lang( $id ) {
	return function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $id, 'slug' ) : '';
}

// -----------------------------------------------------------------------------
// Lookup
// -----------------------------------------------------------------------------

/**
 * path -> post ID for every published post of the configured types, all languages.
 * Built from real permalinks, so Polylang's language prefixes are handled for us.
 */
function dv_permalink_map( $post_types ) {
	$ids = get_posts( array(
		'post_type'      => $post_types,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'lang'           => '', // Polylang: all languages
	) );

	$map        = array();
	$collisions = array();
	foreach ( $ids as $id ) {
		$key = dv_path( get_permalink( $id ) );
		if ( isset( $map[ $key ] ) ) {
			$collisions[ $key ][] = (int) $id;
			continue;
		}
		$map[ $key ] = (int) $id;
	}
	return array( $map, $collisions );
}

/** Front page for a given language — Polylang keeps a separate one per language. */
function dv_front_id( $lang ) {
	$front = (int) get_option( 'page_on_front' );
	if ( ! $front ) {
		return 0;
	}
	if ( function_exists( 'pll_get_post' ) ) {
		$translated = (int) pll_get_post( $front, $lang );
		if ( $translated ) {
			return $translated;
		}
	}
	return ( dv_post_lang( $front ) === $lang || ! function_exists( 'pll_get_post_language' ) ) ? $front : 0;
}

/**
 * Resolve both language variants of one page.
 * Returns array( 'uk' => array( id, method ), 'ru' => array( id, method ) ).
 */
function dv_resolve_page( $page, $map ) {
	$uk_id     = 0;
	$uk_method = 'unresolved';
	$ru_id     = 0;
	$ru_method = 'unresolved';

	if ( ! empty( $page['is_front'] ) ) {
		$uk_id = dv_front_id( 'uk' );
		$ru_id = dv_front_id( 'ru' );
		if ( $uk_id ) {
			$uk_method = 'front-page';
		}
		if ( $ru_id ) {
			$ru_method = 'front-page';
		}
		return array(
			'uk' => array( 'id' => $uk_id, 'method' => $uk_method ),
			'ru' => array( 'id' => $ru_id, 'method' => $ru_method ),
		);
	}

	$key = dv_path( $page['translations']['uk']['url'] );
	if ( isset( $map[ $key ] ) ) {
		$uk_id     = $map[ $key ];
		$uk_method = 'uk-url';
	}

	if ( $uk_id && function_exists( 'pll_get_post' ) ) {
		$ru_id = (int) pll_get_post( $uk_id, 'ru' );
		if ( $ru_id ) {
			$ru_method = 'polylang-translation';
		} else {
			$ru_method = 'no-translation-link';
		}
	}

	return array(
		'uk' => array( 'id' => $uk_id, 'method' => $uk_method ),
		'ru' => array( 'id' => $ru_id, 'method' => $ru_method ),
	);
}

// -----------------------------------------------------------------------------
// Yoast indexable
// -----------------------------------------------------------------------------

/**
 * Best-effort in-process rebuild of the Yoast indexable for one post.
 * Yoast keeps a denormalised copy of title/description in wp_yoast_indexable and
 * the front end reads from there, so a stale row would hide our changes.
 * Fails soft: if class names shifted between Yoast versions we fall back to
 * `wp yoast index --reindex`.
 */
function dv_rebuild_indexable( $post_id ) {
	if ( ! function_exists( 'YoastSEO' ) ) {
		return 'no-yoast';
	}
	try {
		$container = YoastSEO()->classes;
		$repo      = $container->get( 'Yoast\WP\SEO\Repositories\Indexable_Repository' );
		$builder   = $container->get( 'Yoast\WP\SEO\Builders\Indexable_Builder' );
		$indexable = $repo->find_by_id_and_type( $post_id, 'post', false );
		$builder->build_for_id_and_type( $post_id, 'post', $indexable );
		return 'ok';
	} catch ( \Throwable $e ) {
		return 'failed: ' . $e->getMessage();
	}
}

function dv_related_payload( $keyphrases ) {
	$out = array();
	foreach ( $keyphrases as $kw ) {
		$out[] = array( 'keyword' => $kw, 'score' => 0 );
	}
	return wp_json_encode( $out, JSON_UNESCAPED_UNICODE );
}

/** Yoast meta this row should end up with. */
function dv_fields_for( $tr ) {
	$fields = array(
		DV_META_TITLE => (string) $tr['title'],
		DV_META_DESC  => (string) $tr['metadesc'],
	);
	if ( DV_WRITE_FOCUS && ! empty( $tr['focus_keyphrase'] ) ) {
		$fields[ DV_META_FOCUS ] = (string) $tr['focus_keyphrase'];
	}
	if ( DV_WRITE_RELATED && dv_is_premium() && ! empty( $tr['related_keyphrases'] ) ) {
		$fields[ DV_META_RELATED ] = dv_related_payload( $tr['related_keyphrases'] );
	}
	return $fields;
}

// -----------------------------------------------------------------------------
// Bootstrap
// -----------------------------------------------------------------------------

$mode = isset( $args[0] ) ? $args[0] : '';
$file = isset( $args[1] ) ? $args[1] : '';

if ( ! in_array( $mode, array( 'diagnose', 'resolve', 'dry', 'apply', 'verify' ), true ) ) {
	WP_CLI::error( 'Usage: wp eval-file yoast-import.php <diagnose|resolve|dry|apply|verify> <data.json>' );
}
if ( ! $file || ! file_exists( $file ) ) {
	WP_CLI::error( 'JSON file not found: ' . $file );
}

$data = json_decode( file_get_contents( $file ), true );
if ( ! is_array( $data ) || empty( $data['pages'] ) ) {
	WP_CLI::error( 'Malformed JSON: expected a "pages" array.' );
}
if ( ! function_exists( 'pll_get_post' ) ) {
	WP_CLI::error( 'Polylang not active — ru pages cannot be resolved. Aborting.' );
}

$pages = $data['pages'];
list( $map, $collisions ) = dv_permalink_map( $dv_post_types );

WP_CLI::log( sprintf( 'Source: %s (%s) — %d pages × 2 languages', $file, $data['label'], count( $pages ) ) );
WP_CLI::log( sprintf( 'Published URLs indexed: %d. Yoast Premium: %s.',
	count( $map ), dv_is_premium() ? 'yes' : 'no' ) );
WP_CLI::log( 'Post types searched: ' . implode( ', ', $dv_post_types ) );
if ( $collisions ) {
	WP_CLI::warning( 'Duplicate permalinks: ' . implode( ', ', array_keys( $collisions ) ) );
}

// -----------------------------------------------------------------------------
// diagnose
// -----------------------------------------------------------------------------

/**
 * For every uk URL in the JSON that the permalink map does not contain, look the
 * slug up across ALL post types and ALL statuses and report what was found.
 * Answers "why did this row not resolve": wrong post type, draft, private,
 * different slug, or genuinely absent.
 */
if ( $mode === 'diagnose' ) {
	global $wpdb;

	list( $fh, $path ) = dv_csv_open( $data['site'] . '-diagnose.csv' );
	fputcsv( $fh, array( 'row', 'uk_url', 'label', 'slug', 'found_id',
		'post_type', 'post_status', 'lang', 'permalink', 'post_title' ) );

	$type_hits   = array();
	$status_hits = array();
	$missing     = 0;

	foreach ( $pages as $page ) {
		if ( ! empty( $page['is_front'] ) ) {
			continue;
		}
		$url = $page['translations']['uk']['url'];
		if ( isset( $map[ dv_path( $url ) ] ) ) {
			continue; // already resolves fine
		}

		$slug = $page['expected_slug']['uk'];
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_type, post_status, post_title FROM {$wpdb->posts}
			 WHERE post_name = %s AND post_type != 'revision' LIMIT 10", $slug ), ARRAY_A );

		if ( ! $rows ) {
			$missing++;
			fputcsv( $fh, array( $page['row'], $url, $page['translations']['uk']['label'],
				$slug, '', 'NOT FOUND', '', '', '', '' ) );
			WP_CLI::log( sprintf( 'NO POST WITH SLUG  %-45s (%s)', $slug, $page['translations']['uk']['label'] ) );
			continue;
		}

		foreach ( $rows as $r ) {
			$type_hits[ $r['post_type'] ]     = isset( $type_hits[ $r['post_type'] ] ) ? $type_hits[ $r['post_type'] ] + 1 : 1;
			$status_hits[ $r['post_status'] ] = isset( $status_hits[ $r['post_status'] ] ) ? $status_hits[ $r['post_status'] ] + 1 : 1;

			fputcsv( $fh, array( $page['row'], $url, $page['translations']['uk']['label'], $slug,
				$r['ID'], $r['post_type'], $r['post_status'], dv_post_lang( $r['ID'] ),
				dv_path( get_permalink( $r['ID'] ) ), $r['post_title'] ) );

			WP_CLI::log( sprintf( '#%-6d %-14s %-9s %-4s %s',
				$r['ID'], $r['post_type'], $r['post_status'], dv_post_lang( $r['ID'] ),
				dv_path( get_permalink( $r['ID'] ) ) ) );
		}
	}
	fclose( $fh );

	WP_CLI::log( '' );
	foreach ( $type_hits as $t => $n ) {
		$obj  = get_post_type_object( $t );
		$rw   = ( $obj && ! empty( $obj->rewrite['slug'] ) ) ? $obj->rewrite['slug'] : '-';
		$pub  = ( $obj && $obj->public ) ? 'public' : 'NOT public';
		WP_CLI::log( sprintf( 'post_type "%s": %d hits, rewrite slug "%s", %s', $t, $n, $rw, $pub ) );
	}
	foreach ( $status_hits as $s => $n ) {
		WP_CLI::log( sprintf( 'post_status "%s": %d hits', $s, $n ) );
	}
	if ( $missing ) {
		WP_CLI::warning( sprintf( '%d slugs have no matching post at all.', $missing ) );
	}
	WP_CLI::log( 'Report: ' . $path );
	WP_CLI::success( 'Diagnosis written. Non-public post types must be added to $dv_post_types by hand.' );
	return;
}

// -----------------------------------------------------------------------------
// resolve
// -----------------------------------------------------------------------------

if ( $mode === 'resolve' ) {
	list( $fh, $path ) = dv_csv_open( $data['site'] . '-resolve.csv' );
	fputcsv( $fh, array( 'row', 'key', 'lang', 'label_in_table', 'method',
		'post_id', 'post_title', 'permalink', 'lang_ok' ) );

	$bad = 0;
	foreach ( $pages as $page ) {
		$res = dv_resolve_page( $page, $map );

		foreach ( array( 'uk', 'ru' ) as $lang ) {
			$id     = $res[ $lang ]['id'];
			$method = $res[ $lang ]['method'];
			$tr     = $page['translations'][ $lang ];

			$lang_ok = $id ? ( dv_post_lang( $id ) === $lang ? 'yes' : 'NO (' . dv_post_lang( $id ) . ')' ) : '';

			fputcsv( $fh, array( $page['row'], $page['key'], $lang, $tr['label'], $method,
				$id ?: '', $id ? get_the_title( $id ) : '', $id ? dv_path( get_permalink( $id ) ) : '', $lang_ok ) );

			if ( ! $id ) {
				$bad++;
				continue;
			}
			if ( $lang_ok !== 'yes' ) {
				$bad++;
				WP_CLI::warning( sprintf( 'LANG MISMATCH #%d expected %s, got %s (%s)',
					$id, $lang, dv_post_lang( $id ), $tr['label'] ) );
			}
		}

		if ( ! $res['uk']['id'] ) {
			WP_CLI::log( sprintf( 'NO UK PAGE   %s  (%s)',
				$page['translations']['uk']['url'], $page['translations']['uk']['label'] ) );
		} elseif ( ! $res['ru']['id'] ) {
			WP_CLI::log( sprintf( 'NO RU LINK   %s  (uk #%d) — no Polylang translation attached',
				$page['translations']['uk']['url'], $res['uk']['id'] ) );
		}
	}
	fclose( $fh );

	WP_CLI::log( 'Report: ' . $path );
	if ( $bad ) {
		WP_CLI::warning( sprintf( '%d of %d rows could not be matched cleanly.', $bad, count( $pages ) * 2 ) );
	} else {
		WP_CLI::success( 'All rows matched. Check post_title against label_in_table, then run dry.' );
	}
	return;
}

// -----------------------------------------------------------------------------
// verify
// -----------------------------------------------------------------------------

if ( $mode === 'verify' ) {
	global $wpdb;
	$bad = 0;
	foreach ( $pages as $page ) {
		$res = dv_resolve_page( $page, $map );
		foreach ( array( 'uk', 'ru' ) as $lang ) {
			$id = $res[ $lang ]['id'];
			$tr = $page['translations'][ $lang ];
			if ( ! $id ) {
				WP_CLI::log( sprintf( 'UNRESOLVED [%s] %s', $lang, $tr['label'] ) );
				$bad++;
				continue;
			}
			$problems = array();
			foreach ( dv_fields_for( $tr ) as $meta_key => $expected ) {
				if ( (string) get_post_meta( $id, $meta_key, true ) !== $expected ) {
					$problems[] = $meta_key;
				}
			}
			$indexed = $wpdb->get_row( $wpdb->prepare(
				"SELECT title, description FROM {$wpdb->prefix}yoast_indexable
				 WHERE object_id = %d AND object_type = 'post' LIMIT 1", $id ), ARRAY_A );
			if ( $indexed && (string) $indexed['title'] !== $tr['title'] ) {
				$problems[] = 'indexable.title';
			}
			if ( $indexed && (string) $indexed['description'] !== $tr['metadesc'] ) {
				$problems[] = 'indexable.description';
			}
			if ( $problems ) {
				WP_CLI::log( sprintf( 'DIFF #%d [%s] %s — %s', $id, $lang, $tr['label'], implode( ', ', $problems ) ) );
				$bad++;
			}
		}
	}
	if ( $bad ) {
		WP_CLI::warning( sprintf( '%d of %d rows need attention.', $bad, count( $pages ) * 2 ) );
	} else {
		WP_CLI::success( 'All rows match.' );
	}
	return;
}

// -----------------------------------------------------------------------------
// dry / apply
// -----------------------------------------------------------------------------

$apply  = ( $mode === 'apply' );
$prefix = $data['site'] . '-' . gmdate( 'Ymd-His' );

list( $rep_fh, $rep_path ) = dv_csv_open( $prefix . '-report.csv' );
fputcsv( $rep_fh, array( 'status', 'lang', 'label', 'post_id', 'method', 'field', 'old', 'new' ) );

$backup = array();
$stats  = array( 'changed' => 0, 'unchanged' => 0, 'unresolved' => 0, 'fields' => 0 );
$seen   = array();

foreach ( $pages as $page ) {
	$res = dv_resolve_page( $page, $map );

	foreach ( array( 'uk', 'ru' ) as $lang ) {
		$id     = $res[ $lang ]['id'];
		$method = $res[ $lang ]['method'];
		$tr     = $page['translations'][ $lang ];

		if ( ! $id ) {
			$stats['unresolved']++;
			fputcsv( $rep_fh, array( 'UNRESOLVED', $lang, $tr['label'], '', $method, '', '', '' ) );
			WP_CLI::log( sprintf( 'UNRESOLVED [%s] %s (%s)', $lang, $tr['label'], $method ) );
			continue;
		}

		if ( isset( $seen[ $id ] ) ) {
			WP_CLI::warning( sprintf( 'Post #%d matched twice (%s and %s) — skipping the second.',
				$id, $seen[ $id ], $tr['label'] ) );
			fputcsv( $rep_fh, array( 'DUPLICATE', $lang, $tr['label'], $id, $method, '', '', '' ) );
			continue;
		}
		$seen[ $id ] = $tr['label'];

		$changes = array();
		foreach ( dv_fields_for( $tr ) as $meta_key => $new ) {
			$old = (string) get_post_meta( $id, $meta_key, true );
			if ( $old === $new ) {
				continue;
			}
			$changes[ $meta_key ] = array( $old, $new );
			$backup[]             = array( $id, $lang, $tr['label'], $meta_key, $old );
		}

		if ( ! $changes ) {
			$stats['unchanged']++;
			fputcsv( $rep_fh, array( 'UNCHANGED', $lang, $tr['label'], $id, $method, '', '', '' ) );
			continue;
		}

		foreach ( $changes as $meta_key => $pair ) {
			$stats['fields']++;
			fputcsv( $rep_fh, array( $apply ? 'UPDATED' : 'WOULD-UPDATE', $lang, $tr['label'],
				$id, $method, $meta_key, $pair[0], $pair[1] ) );
			if ( $apply ) {
				update_post_meta( $id, $meta_key, $pair[1] );
			}
		}

		if ( $apply ) {
			$rebuild = dv_rebuild_indexable( $id );
			if ( $rebuild !== 'ok' ) {
				WP_CLI::warning( sprintf( '#%d indexable rebuild: %s', $id, $rebuild ) );
			}
		}

		$stats['changed']++;
		WP_CLI::log( sprintf( '%s [%s] #%-5d %s (%d fields)',
			$apply ? 'UPDATED' : 'WOULD  ', $lang, $id, $tr['label'], count( $changes ) ) );
	}
}

fclose( $rep_fh );

if ( $apply && $backup ) {
	list( $bk_fh, $bk_path ) = dv_csv_open( $prefix . '-backup.csv' );
	fputcsv( $bk_fh, array( 'post_id', 'lang', 'label', 'meta_key', 'old_value' ) );
	foreach ( $backup as $b ) {
		fputcsv( $bk_fh, $b );
	}
	fclose( $bk_fh );
	WP_CLI::log( 'Backup of previous values: ' . $bk_path );
}

WP_CLI::log( sprintf( 'Rows: %d changed, %d already correct, %d unresolved. Meta fields touched: %d.',
	$stats['changed'], $stats['unchanged'], $stats['unresolved'], $stats['fields'] ) );
WP_CLI::log( 'Report: ' . $rep_path );

if ( $apply ) {
	WP_CLI::success( 'Done. If any snippet still shows old data, run: wp yoast index --reindex' );
} else {
	WP_CLI::success( 'Dry run only — nothing was written.' );
}
