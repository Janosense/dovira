<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

function acf_add_post_type_conversation_fields(): void {
	$fields = new FieldsBuilder( 'conversation', array(
		'title' => __( 'Conversation information', 'dovira' ),
	) );

	$fields->addTrueFalse('is_processed', array(
		'label'    => __( 'Is processed?', 'dovira' ),
		'ui'            => 1,
		'ui_on_text'    => __( 'Yes', 'dovira' ),
		'ui_off_text'   => __( 'No', 'dovira' ),
		'default_value' => 0
	));

	$fields->addTextarea( 'note', array(
		'label' => __( 'Note', 'dovira' ),
	) );

	$fields->addText( 'name', array(
		'label' => __( 'Name', 'dovira' ),
	) );

	$fields->addText( 'pet_name', array(
		'label' => __( 'Pet name', 'dovira' ),
	) );

	$fields->addText( 'phone', array(
		'label' => __( 'Phone', 'dovira' ),
	) );


	$fields->addEmail( 'email', array(
		'label' => __( 'Email', 'dovira' ),
	) );

	$fields->addTextarea( 'message', array(
		'label' => __( 'Message', 'dovira' ),
	) );

	$fields->setLocation( 'post_type', '==', 'conversation' );

	acf_add_local_field_group( $fields->build() );
}

add_action( 'acf/init', 'acf_add_post_type_conversation_fields' );
