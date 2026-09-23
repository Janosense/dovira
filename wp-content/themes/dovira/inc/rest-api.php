<?php

use dovira\TelegramController;
use dovira\QuestionaryController;
use dovira\SearchStats\RecordController;

require_once TEMPLATE_DIR . '/inc/rest-api/TelegramController.php';
require_once TEMPLATE_DIR . '/inc/rest-api/QuestionaryController.php';

add_action( 'rest_api_init', function () {
	$telegram = new TelegramController();
	$telegram->register_routes();

	$questionary = new QuestionaryController();
	$questionary->register_routes();

	// Feature search-stats: the class is required by the feature's bootstrap.php.
	$search_stats = new RecordController();
	$search_stats->register_routes();
} );
