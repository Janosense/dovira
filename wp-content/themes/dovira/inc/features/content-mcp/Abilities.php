<?php

namespace dovira\ContentMcp;

/**
 * Registers the `dovira` ability category and every ability of the catalogue
 * with the core Abilities API.
 */
final class Abilities {

	/**
	 * On `wp_abilities_api_categories_init`: core refuses an ability whose
	 * category is not registered first.
	 */
	public static function register_category(): void {
		wp_register_ability_category(
			Catalogue::CATEGORY,
			[
				'label'       => 'Dovira',
				'description' => 'Контент сайту клініки Dovira: послуги й ціни, лікарі, вакансії, сторінки, блог.',
			]
		);
	}

	/**
	 * On `wp_abilities_api_init`: one ability per name in `Catalogue::TOOLS`.
	 */
	public static function register(): void {
		$definitions = self::definitions();

		foreach ( Catalogue::TOOLS as $name ) {
			wp_register_ability( $name, $definitions[ $name ] );
		}
	}

	/**
	 * The registration arguments of every catalogued ability, by name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function definitions(): array {
		return [
			DescribeSite::NAME   => DescribeSite::args(),
			ListPosts::NAME      => ListPosts::args(),
			GetPost::NAME        => GetPost::args(),
			FindPriceRows::NAME  => FindPriceRows::args(),
			ListTerms::NAME      => ListTerms::args(),
			ListMedia::NAME      => ListMedia::args(),
			UpdatePriceRow::NAME => UpdatePriceRow::args(),
			UpdatePost::NAME     => UpdatePost::args(),
		];
	}
}
