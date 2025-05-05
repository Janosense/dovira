<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

function acf_add_post_type_employee_fields(): void {
	$fields = new FieldsBuilder( 'employee', array(
		'title' => __( 'Employee information', 'dovira' ),
	) );

	$fields->addImage( 'photo', array(
		'label'    => __( 'Photo', 'dovira' ),
		'required' => true,
	) );

	$fields->addText( 'position', array(
		'label'    => __( 'Position', 'dovira' ),
	) );

	$fields->addTextarea( 'quote', array(
		'label' => __( 'Quote', 'dovira' ),
		'rows'  => 3
	) );

	$fields->addGroup( 'social_links', array(
		'label' => __( 'Social links', 'dovira' ),
	) )
	       ->addUrl( 'facebook', array(
		       'label' => __( 'Facebook', 'dovira' ),
	       ) )
	       ->addUrl( 'instagram', array(
		       'label' => __( 'Instagram', 'dovira' ),
	       ) )
	       ->addUrl( 'linkedin', array(
		       'label' => __( 'Linkedin', 'dovira' ),
	       ) );

	$fields->addTrueFalse( 'is_contact_cta_enabled', array(
		'label'    => __( 'Is contact CTA enabled', 'dovira' ),
		'ui'            => 1,
		'ui_on_text'    => __( 'Yes', 'dovira' ),
		'ui_off_text'   => __( 'No', 'dovira' ),
		'default_value' => 0
	) );

	$fields->addText( 'title', array(
		'label' => __( 'Title', 'dovira' ),
		'rows'  => 3
	) );

	$fields->addTextarea( 'description', array(
		'label' => __( 'Description', 'dovira' ),
		'rows'  => 4,
	) );

	$fields->addRepeater( 'facts', array(
		'label'  => __( 'Facts', 'dovira' ),
		'layout' => 'block',
	) )
	       ->addText( 'title', array(
		       'label' => __( 'Title', 'dovira' ),
	       ) )
	       ->addTextarea( 'fact', array(
		       'label' => __( 'Fact', 'dovira' ),
		       'rows'  => 3,
	       ) );


	$fields->setLocation( 'post_type', '==', 'employee' );

	acf_add_local_field_group( $fields->build() );
}

add_action( 'acf/init', 'acf_add_post_type_employee_fields' );
