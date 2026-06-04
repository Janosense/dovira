<?php

use dovira\CLI\TranslateCommand;

require_once TEMPLATE_DIR . '/inc/cli/ContentExtractor.php';
require_once TEMPLATE_DIR . '/inc/cli/AnthropicTranslator.php';
require_once TEMPLATE_DIR . '/inc/cli/PostCopier.php';
require_once TEMPLATE_DIR . '/inc/cli/TranslateCommand.php';

WP_CLI::add_command( 'dovira translate', TranslateCommand::class );
