<?php
/**
 * Polylang integration: translatable post types, language-aware ACF options,
 * and string translations for shared taxonomy term names.
 */

/**
 * Make custom post types translatable.
 *
 * service-city taxonomy is intentionally NOT translatable: the service price
 * field group generates field names from its term IDs (see
 * inc/acf/fields/post-type-service.php), so terms must stay shared across
 * languages. City names are translated as Polylang strings instead.
 */
add_filter( 'pll_get_post_types', function ( array $post_types, bool $is_settings ) {
	$post_types['service'] = 'service';
	$post_types['vacancy'] = 'vacancy';

	return $post_types;
}, 10, 2 );

/**
 * Store ACF options per language.
 *
 * ACF natively appends "_{current_language}" to the "options" post id when
 * its current_language setting differs from default_language (see
 * acf_get_valid_post_id()). Only the WPML integration wires these settings,
 * so we wire them to Polylang: the default language keeps the plain
 * "options_*" rows, other languages read/write "options_{lang}_*".
 */
add_filter( 'acf/settings/current_language', function ( $value ) {
	if ( function_exists( 'pll_current_language' ) ) {
		return pll_current_language() ?: '';
	}

	return $value;
} );

add_filter( 'acf/settings/default_language', function ( $value ) {
	if ( function_exists( 'pll_default_language' ) ) {
		return pll_default_language() ?: '';
	}

	return $value;
} );

/**
 * Register service-city term names as Polylang strings, so they can be
 * translated (admin UI: Languages → Translations; CLI: wp dovira translate-options).
 */
add_action( 'init', function () {
	if ( ! function_exists( 'pll_register_string' ) ) {
		return;
	}

	$terms = get_terms( [
		'taxonomy'   => 'service-city',
		'hide_empty' => false,
	] );

	if ( is_wp_error( $terms ) ) {
		return;
	}

	foreach ( $terms as $term ) {
		pll_register_string( 'service-city-' . $term->term_id, $term->name, 'Dovira: Cities' );
	}
}, 11 );

/**
 * Translate a registered string for the current language.
 *
 * @param string $string
 *
 * @return string
 */
function dovira_translate_string( string $string ): string {
	return function_exists( 'pll__' ) ? pll__( $string ) : $string;
}
