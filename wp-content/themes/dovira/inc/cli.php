<?php

use dovira\CLI\TranslateCommand;
use dovira\CLI\TranslateOptionsCommand;

require_once TEMPLATE_DIR . '/inc/cli/Heuristics.php';
require_once TEMPLATE_DIR . '/inc/cli/ContentExtractor.php';
require_once TEMPLATE_DIR . '/inc/cli/MetaExtractor.php';
require_once TEMPLATE_DIR . '/inc/cli/AnthropicTranslator.php';
require_once TEMPLATE_DIR . '/inc/cli/PostCopier.php';
require_once TEMPLATE_DIR . '/inc/cli/TranslateCommand.php';
require_once TEMPLATE_DIR . '/inc/cli/TranslateOptionsCommand.php';

WP_CLI::add_command( 'dovira translate', TranslateCommand::class );
WP_CLI::add_command( 'dovira translate-options', TranslateOptionsCommand::class );
