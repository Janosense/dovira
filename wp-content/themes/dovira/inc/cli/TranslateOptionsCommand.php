<?php

namespace dovira\CLI;

use PLL_MO;
use Throwable;
use WP_CLI;

/**
 * Translates the ACF options page into a language-suffixed option set
 * ("options_{lang}_*"), and service-city term names into Polylang strings.
 *
 * Reading the language-specific set on the front-end is wired in
 * inc/polylang.php (acf/settings/current_language).
 */
class TranslateOptionsCommand {

	/**
	 * Translates ACF options and shared taxonomy term names.
	 *
	 * Copies every "options_*" / "_options_*" row of the default-language
	 * set to "options_{lang}_*" / "_options_{lang}_*", translating the
	 * human-readable values via the Anthropic API. Also translates
	 * service-city term names and stores them as Polylang string
	 * translations (used by dovira_translate_string()).
	 *
	 * ## OPTIONS
	 *
	 * [--lang-from=<slug>]
	 * : Source language slug.
	 * ---
	 * default: uk
	 * ---
	 *
	 * [--lang-to=<slug>]
	 * : Target language slug.
	 * ---
	 * default: ru
	 * ---
	 *
	 * [--dry-run]
	 * : Translate and print the result without writing anything.
	 *
	 * [--overwrite]
	 * : Update target rows and string translations that already exist.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dovira translate-options --dry-run
	 *     wp dovira translate-options
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$from      = $assoc_args['lang-from'] ?? 'uk';
		$to        = $assoc_args['lang-to'] ?? 'ru';
		$dry_run   = isset( $assoc_args['dry-run'] );
		$overwrite = isset( $assoc_args['overwrite'] );

		$this->check_requirements( $from, $to );

		$option_names = $this->get_source_option_names();
		$terms        = get_terms( [
			'taxonomy'   => 'service-city',
			'hide_empty' => false,
		] );
		$terms        = is_wp_error( $terms ) ? [] : $terms;

		if ( empty( $option_names ) ) {
			WP_CLI::error( 'No ACF option rows found.' );
		}

		WP_CLI::log( sprintf( 'Found %d option row(s) and %d term(s).', count( $option_names ), count( $terms ) ) );

		// Translatable subset: option values passing the heuristics + term
		// names. Term names and option city values are translated in the
		// same payload so identical source strings translate identically
		// (single-service.php matches option city values against term names).
		$map = [];

		foreach ( $option_names as $name ) {
			if ( str_starts_with( $name, '_' ) ) {
				continue; // Field key reference rows are copied verbatim.
			}

			$key   = substr( $name, strlen( 'options_' ) );
			$value = get_option( $name );

			if ( is_string( $value ) && Heuristics::is_translatable( $key, $value ) ) {
				$map[ "opt:$key" ] = $value;
			}
		}

		foreach ( $terms as $term ) {
			$map[ "term:{$term->term_id}" ] = $term->name;
		}

		if ( empty( $map ) ) {
			WP_CLI::error( 'No translatable strings found.' );
		}

		$translator = new AnthropicTranslator(
			ANTHROPIC_API_KEY,
			TranslateCommand::LANGUAGE_NAMES[ $from ] ?? $from,
			TranslateCommand::LANGUAGE_NAMES[ $to ] ?? $to
		);

		try {
			$translations = $translator->translate_map( $map );
		} catch ( Throwable $e ) {
			WP_CLI::error( "Translation failed: {$e->getMessage()}" );
		}

		if ( $dry_run ) {
			foreach ( $map as $path => $source ) {
				WP_CLI::log( "  $path" );
				WP_CLI::log( "    $from: $source" );
				WP_CLI::log( '    →  ' . ( $translations[ $path ] ?? '(missing)' ) );
			}
			WP_CLI::success( 'Dry run finished, nothing written.' );

			return;
		}

		$written = $this->write_options( $option_names, $translations, $to, $overwrite );
		$strings = $this->write_string_translations( $terms, $translations, $to );

		WP_CLI::success( "Wrote $written option row(s) and $strings string translation(s) for \"$to\"." );
	}

	/**
	 * Fatal-checks the environment before doing any work.
	 *
	 * @param string $from
	 * @param string $to
	 */
	private function check_requirements( string $from, string $to ): void {
		if ( ! defined( 'ANTHROPIC_API_KEY' ) || ANTHROPIC_API_KEY === '' ) {
			WP_CLI::error( 'ANTHROPIC_API_KEY constant is not defined in wp-config.php.' );
		}

		if ( ! function_exists( 'pll_languages_list' ) ) {
			WP_CLI::error( 'Polylang is not active.' );
		}

		$languages = pll_languages_list();

		foreach ( [ $from, $to ] as $slug ) {
			if ( ! in_array( $slug, $languages, true ) ) {
				WP_CLI::error( "Language \"$slug\" is not registered in Polylang. Available: " . implode( ', ', $languages ) );
			}
		}
	}

	/**
	 * Collects the default-language ACF option row names: "options_*" and
	 * "_options_*", excluding rows already suffixed with a registered
	 * language slug ("options_ru_*"), which makes re-runs idempotent.
	 *
	 * @return string[]
	 */
	private function get_source_option_names(): array {
		global $wpdb;

		$names = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options}
			 WHERE option_name LIKE 'options\\_%' OR option_name LIKE '\\_options\\_%'"
		);

		$language_pattern = '/^_?options_(' . implode( '|', array_map( 'preg_quote', pll_languages_list() ) ) . ')_/';

		return array_values( array_filter( $names, fn( $name ) => ! preg_match( $language_pattern, $name ) ) );
	}

	/**
	 * Copies every source option row to the language-suffixed name, using
	 * the translated value where one exists.
	 *
	 * @param string[]              $option_names
	 * @param array<string, string> $translations
	 * @param string                $to
	 * @param bool                  $overwrite
	 *
	 * @return int Number of rows written.
	 */
	private function write_options( array $option_names, array $translations, string $to, bool $overwrite ): int {
		global $wpdb;

		$written = 0;

		foreach ( $option_names as $name ) {
			$is_reference = str_starts_with( $name, '_' );
			$key          = substr( $name, strlen( $is_reference ? '_options_' : 'options_' ) );
			$target       = ( $is_reference ? "_options_{$to}_" : "options_{$to}_" ) . $key;

			if ( ! $overwrite ) {
				$exists = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s",
					$target
				) );

				if ( $exists ) {
					continue;
				}
			}

			$value = $is_reference ? get_option( $name ) : ( $translations[ "opt:$key" ] ?? get_option( $name ) );

			update_option( $target, $value, false );
			$written ++;
		}

		return $written;
	}

	/**
	 * Stores translated term names as Polylang string translations, so
	 * pll__() / dovira_translate_string() resolves them on the front-end.
	 *
	 * @param \WP_Term[]            $terms
	 * @param array<string, string> $translations
	 * @param string                $to
	 *
	 * @return int Number of string translations written.
	 */
	private function write_string_translations( array $terms, array $translations, string $to ): int {
		$language = PLL()->model->get_language( $to );

		if ( empty( $language ) ) {
			WP_CLI::warning( "Could not load Polylang language object for \"$to\" — string translations skipped." );

			return 0;
		}

		$mo = new PLL_MO();
		$mo->import_from_db( $language );

		$written = 0;

		foreach ( $terms as $term ) {
			$translated = $translations[ "term:{$term->term_id}" ] ?? null;

			if ( $translated !== null && $translated !== $term->name ) {
				$mo->add_entry( $mo->make_entry( $term->name, $translated ) );
				$written ++;
			}
		}

		if ( $written ) {
			$mo->export_to_db( $language );
		}

		return $written;
	}
}
