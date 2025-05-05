<?php
/**
 * Create Contacts option page
 *
 * @return void
 */
function acf_create_settings_options_page(): void {
	acf_add_options_page( array(
		'page_title'  => __( 'Company Settings', 'syndicode2' ),
		'menu_title'  => __( 'Settings', 'syndicode2' ),
		'parent_slug' => 'themes.php',
	) );
}

add_action( 'acf/init', 'acf_create_settings_options_page' );

