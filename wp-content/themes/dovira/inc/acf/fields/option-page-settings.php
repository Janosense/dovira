<?php
/**
 * Option page Fonts Fields.
 *
 * @package cargill
 */

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * @return void
 * @throws \StoutLogic\AcfBuilder\FieldNameCollisionException
 */
function acf_add_options_page_fonts_fields(): void {

	$fields = new FieldsBuilder( 'settings' );

	$fields->addTab( 'contacts_tab', [
		'label' => __( 'Contacts', 'dovira' ),
	] );

	$fields->addText( 'contacts_main_phone', array(
		'label' => __( 'Main Phone', 'dovira' ),
	) );
//
//	$fields->addEmail( 'contacts_email', array(
//		'label' => __( 'Main Email', 'dovira' ),
//	) );

	$fields->addText( 'contacts_telegram', array(
		'label' => __( 'Telegram', 'dovira' ),
	) );

	$fields->addText( 'contacts_viber', array(
		'label' => __( 'Viber', 'dovira' ),
	) );

	$fields->addRepeater( 'contacts_cities', array(
		'label'  => __( 'Cities', 'dovira' ),
		'layout' => 'block',
	) )
	       ->addText( 'city', array(
		       'label' => __( 'City', 'dovira' ),
	       ) )
	       ->addRepeater( 'phones', array(
		       'label' => __( 'Phones', 'dovira' ),
	       ) )
	       ->addText( 'number', array(
		       'label' => __( 'Phone Number', 'dovira' ),
	       ) )
	       ->endRepeater()
	       ->addTextarea( 'address', array(
		       'label'     => __( 'Address', 'dovira' ),
		       'rows'      => 4,
		       'new_lines' => 'br',
	       ) )
	       ->addTextarea( 'note', array(
		       'label'     => __( 'Note', 'dovira' ),
		       'rows'      => 3,
		       'new_lines' => 'br',
	       ) )
	       ->addText( 'schedule', array(
		       'label' => __( 'Schedule', 'dovira' ),
	       ) )
	       ->addTextarea( 'map_iframe', array(
		       'label' => __( 'Map iframe', 'dovira' ),
		       'rows'  => 3,
	       ) )
	       ->addEmail( 'email', array(
		       'label' => __( 'Email', 'dovira' ),
	       ) )
	       ->addUrl( 'instagram_link', array(
		       'label' => __( 'Instagram link', 'dovira' ),
	       ) );

	$fields->addTab( 'social_links_tab', [
		'label' => __( 'Social links', 'dovira' ),
	] );

	$fields->addRepeater( 'social_links', array(
		'label' => __( 'Social links', 'dovira' ),
	) )
	       ->addURL( 'url', array(
		       'label' => __( 'Social network URL', 'dovira' ),
	       ) )
	       ->addTrueFalse( 'is_icon_image_file', array(
		       'label'         => __( 'Image or SVG tag?', 'dovira' ),
		       'ui'            => 1,
		       'ui_on_text'    => __( 'Image', 'dovira' ),
		       'ui_off_text'   => __( 'SVG tag', 'dovira' ),
		       'default_value' => 1
	       ) )
	       ->addImage( 'icon_image', array(
		       'label'             => __( 'Social network Icon', 'dovira' ),
		       'required'          => 1,
		       'conditional_logic' => array(
			       array(
				       array(
					       'field'    => 'is_icon_image_file',
					       'operator' => '==',
					       'value'    => 1,
				       ),
			       ),
		       )
	       ) )
	       ->addTextarea( 'icon_svg_tag', array(
		       'label'             => __( 'Social network Icon', 'dovira' ),
		       'required'          => 1,
		       'conditional_logic' => array(
			       array(
				       array(
					       'field'    => 'is_icon_image_file',
					       'operator' => '==',
					       'value'    => 0
				       ),
			       ),
		       ),
	       ) );

	$fields->setLocation( 'options_page', '==', 'acf-options-settings' );
	acf_add_local_field_group( $fields->build() );

}

add_action( 'acf/init', 'acf_add_options_page_fonts_fields' );
