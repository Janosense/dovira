<?php

namespace dovira\CityPopup;

/**
 * The screen «City question» (docs/features/city-popup/FEATURE.md → UI): one
 * native <dialog>, printed in the footer of a blog page only, closed. The
 * browser module opens it on a click and reads what the question needs from
 * its data attributes — the current city, both origins and the target URLs —
 * so the JS knows no URL and no slug (DECISIONS "The question is a native
 * `<dialog>` printed only on blog pages; the click handler reads everything
 * from its data attributes").
 *
 * The cookie domain and the days are printed on every page instead, in a
 * hidden config element: the header switcher saves the chosen city on every
 * page (DECISIONS "The header switcher saves the city it switches to").
 *
 * The markup and its attributes are internal to the feature. Every visible
 * string is a Polylang string registered in inc/utils/polylang-string-translations.php.
 */
final class Dialog {

	/** How long a chosen city is remembered; changed through the filter dovira_city_popup_days. */
	public const DEFAULT_DAYS = 90;

	private const TITLE = 'Яке місто вас цікавить?';
	private const CLOSE = 'Закрити';

	/** The buttons, in this order. */
	private const CITIES = [
		Sites::KHARKIV => 'Харків',
		Sites::KYIV    => 'Київ',
	];

	public function __construct( private Sites $sites, private Pages $pages ) {
	}

	/**
	 * The wp_footer callback for the question.
	 */
	public static function print_on_blog(): void {
		( new self( new Sites( home_url() ), new Pages() ) )->render();
	}

	/**
	 * The wp_footer callback for the cookie settings, on every page.
	 */
	public static function print_config(): void {
		( new self( new Sites( home_url() ), new Pages() ) )->render_config();
	}

	/**
	 * The filtered number of days; a value below 1 falls back to the default.
	 */
	public function days(): int {
		$days = (int) apply_filters( 'dovira_city_popup_days', self::DEFAULT_DAYS );

		return $days < 1 ? self::DEFAULT_DAYS : $days;
	}

	/**
	 * Prints the dialog on a blog page, and nothing anywhere else.
	 */
	public function render(): void {
		if ( ! $this->pages->is_blog() ) {
			return;
		}

		$targets = (string) wp_json_encode( $this->pages->targets(), JSON_UNESCAPED_SLASHES );
		$first = true;
		?>
<dialog class="city-popup" aria-labelledby="city-popup-title" data-city-popup
	data-current-city="<?= esc_attr( $this->sites->city() ); ?>"
	data-kharkiv-origin="<?= esc_attr( $this->sites->kharkiv_origin() ); ?>"
	data-kyiv-origin="<?= esc_attr( $this->sites->kyiv_origin() ); ?>"
	data-targets="<?= esc_attr( $targets ); ?>">
	<h2 class="city-popup__title" id="city-popup-title"><?= esc_html( dovira_translate_string( self::TITLE ) ); ?></h2>
	<div class="city-popup__cities">
		<?php foreach ( self::CITIES as $city => $name ) : ?>
			<button type="button" class="button button--black city-popup__city" data-city-popup-choice="<?= esc_attr( $city ); ?>"<?= $first ? ' autofocus' : ''; ?>><?= esc_html( dovira_translate_string( $name ) ); ?></button>
			<?php $first = false; ?>
		<?php endforeach; ?>
	</div>
	<button type="button" class="city-popup__close" data-city-popup-close aria-label="<?= esc_attr( dovira_translate_string( self::CLOSE ) ); ?>"><span aria-hidden="true">×</span></button>
</dialog>
		<?php
	}

	/**
	 * Prints, on any page, what saving a city needs: the cookie domain and the days.
	 */
	public function render_config(): void {
		?>
<div hidden data-city-popup-config data-cookie-domain="<?= esc_attr( $this->sites->cookie_domain() ); ?>" data-days="<?= esc_attr( (string) $this->days() ); ?>"></div>
		<?php
	}
}
