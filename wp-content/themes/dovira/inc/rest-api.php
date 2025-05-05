<?php

use dovira\TelegramController;

require_once TEMPLATE_DIR . '/inc/rest-api/TelegramController.php';

add_action( 'rest_api_init', function () {
	$telegram = new TelegramController();
	$telegram->register_routes();
} );
