<?php
/**
 * Feature city-popup: asks a blog reader which city they mean before
 * Services and Contacts. See docs/features/city-popup/FEATURE.md.
 */

use dovira\CityPopup\Dialog;
use dovira\CityPopup\Fallback;

require_once __DIR__ . '/Sites.php';
require_once __DIR__ . '/Pages.php';
require_once __DIR__ . '/Dialog.php';
require_once __DIR__ . '/Fallback.php';

// The closed <dialog>, on blog pages only; the browser module opens it.
add_action( 'wp_footer', [ Dialog::class, 'print_on_blog' ] );
// The cookie domain and the days, on every page: the header switcher saves the city everywhere.
add_action( 'wp_footer', [ Dialog::class, 'print_config' ] );
// A marked 404 under the service base → the services list. The default priority
// runs it after core's wp_old_slug_redirect and redirect_canonical.
add_action( 'template_redirect', [ Fallback::class, 'redirect' ] );
