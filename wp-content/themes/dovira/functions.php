<?php
define( 'TEMPLATE_DIR', get_template_directory() );
define( 'TEMPLATE_DIR_URI', get_template_directory_uri() );

/**
 *  Enable Autoload
 */
require_once TEMPLATE_DIR . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable( TEMPLATE_DIR );
$dotenv->safeLoad();

define( 'VITE_SERVER', isset( $_ENV['VITE_SERVER_PORT'] ) && ! empty( $_ENV['VITE_SERVER_PORT'] ) ? 'http://localhost:' . $_ENV['VITE_SERVER_PORT'] : 'http://localhost:3000' );
define( 'VITE_ENTRY_POINT', isset( $_ENV['VITE_ENTRY_POINT'] ) && ! empty( $_ENV['VITE_ENTRY_POINT'] ) ? $_ENV['VITE_ENTRY_POINT'] : '/source/main.js' );
define( 'WP_ENVIRONMENT_TYPE', isset( $_ENV['WP_ENVIRONMENT_TYPE'] ) && ! empty( $_ENV['WP_ENVIRONMENT_TYPE'] ) ? $_ENV['WP_ENVIRONMENT_TYPE'] : 'production' );
define( 'ASSETS_DIR_URI', wp_get_environment_type() === 'development' ? TEMPLATE_DIR_URI . '/source' : TEMPLATE_DIR_URI . '/assets' );

/**
 * Template function.
 */
require TEMPLATE_DIR . '/inc/template-functions.php';

/**
 * Register new custom taxonomies
 */
require_once TEMPLATE_DIR . '/inc/register-taxonomies.php';

/**
 * Register new custom post types
 */
require_once TEMPLATE_DIR . '/inc/register-post-types.php';

/**
 * Advanced Custom Fields
 */
require_once TEMPLATE_DIR . '/inc/acf.php';

/**
 * Utils
 */
require_once TEMPLATE_DIR . '/inc/utils.php';

/**
 * REST API extensions
 */
require TEMPLATE_DIR . '/inc/rest-api.php';
