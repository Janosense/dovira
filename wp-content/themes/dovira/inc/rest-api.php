<?php

use dovira\TelegramController;
use dovira\QuestionaryController;

require_once TEMPLATE_DIR . '/inc/rest-api/TelegramController.php';
require_once TEMPLATE_DIR . '/inc/rest-api/QuestionaryController.php';

add_action( 'rest_api_init', function () {
	$telegram = new TelegramController();
	$telegram->register_routes();

	$questionary = new QuestionaryController();
	$questionary->register_routes();
} );
