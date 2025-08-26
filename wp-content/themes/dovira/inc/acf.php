<?php

// Option pages
require_once TEMPLATE_DIR . '/inc/acf/option-pages/settings.php';

// Fields
require_once TEMPLATE_DIR . '/inc/acf/fields/option-page-settings.php';
require_once TEMPLATE_DIR . '/inc/acf/fields/post-type-service.php';
require_once TEMPLATE_DIR . '/inc/acf/fields/post-type-employee.php';
require_once TEMPLATE_DIR . '/inc/acf/fields/post-type-conversation.php';


add_action( 'acf/init', function() {
	// Init Blocks and block fields
	$blocks_dir = get_template_directory() . '/inc/acf/blocks/';
	if ( function_exists( 'acf_add_local_field_group' ) && $handle = opendir( $blocks_dir ) ) {
		while ( false !== ( $entry = readdir( $handle ) ) ) {
			$block_path = $blocks_dir . $entry . '/block.json';
			if ( file_exists( $block_path ) ) {
				register_block_type( $block_path );

				$fields = require $blocks_dir . $entry . '/fields.php';
				acf_add_local_field_group( $fields->build() );
			}
		}

		closedir( $handle );
	}
} );

/**
 * Wrapper function to prevent errors if the ACF plugin is deactivated
 *
 * @param string $selector
 * @param mixed $post_id
 * @param bool $format_value
 * @param bool $escape_html
 *
 * @return mixed
 */
function dovira_get_acf_field(
	string $selector,
	mixed $post_id = false,
	bool $format_value = true,
	bool $escape_html = false
): mixed {
	if ( function_exists( 'get_field' ) ) {
		return get_field( $selector, $post_id, $format_value, $escape_html );
	}

	return null;
}
