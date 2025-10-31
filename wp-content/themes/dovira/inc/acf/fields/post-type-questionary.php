<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

function acf_add_post_type_questionary_fields(): void {
	$fields = new FieldsBuilder( 'questionary', array(
		'title' => __( 'Questionary information', 'dovira' ),
	) );

	// Owner information
	$fields->addText( 'name', array(
		'label'    => __( 'Name', 'dovira' ),
		'required' => true,
	) );

	$fields->addText( 'phone', array(
		'label'    => __( 'Phone', 'dovira' ),
		'required' => true,
	) );

	$fields->addEmail( 'email', array(
		'label' => __( 'Email', 'dovira' ),
	) );

	// Pet information
	$fields->addRadio( 'animal', array(
		'label'    => __( 'Animal type', 'dovira' ),
		'choices'  => array(
			'dog' => __( 'Dog', 'dovira' ),
			'cat' => __( 'Cat', 'dovira' ),
		),
		'required' => true,
	) );

	$fields->addRadio( 'pet_sex', array(
		'label'    => __( 'Pet sex', 'dovira' ),
		'choices'  => array(
			'female' => __( 'Female', 'dovira' ),
			'male'   => __( 'Male', 'dovira' ),
		),
		'required' => true,
	) );

	$fields->addText( 'pet_type', array(
		'label' => __( 'Pet breed', 'dovira' ),
	) );

	$fields->addText( 'pet_old', array(
		'label'    => __( 'Pet age', 'dovira' ),
		'required' => true,
	) );

	$fields->addText( 'pet_weight', array(
		'label'    => __( 'Pet weight', 'dovira' ),
		'required' => true,
	) );

	$fields->addText( 'vaccination_date', array(
		'label'    => __( 'Last vaccination date', 'dovira' ),
		'required' => true,
	) );

	// Yes/No questions
	$fields->addTrueFalse( 'street', array(
		'label'         => __( 'Goes outside', 'dovira' ),
		'ui'            => 1,
		'ui_on_text'    => __( 'Yes', 'dovira' ),
		'ui_off_text'   => __( 'No', 'dovira' ),
		'default_value' => 0,
		'required'      => true,
	) );

	$fields->addTrueFalse( 'cat_contact', array(
		'label'         => __( 'Contact with outdoor cats', 'dovira' ),
		'ui'            => 1,
		'ui_on_text'    => __( 'Yes', 'dovira' ),
		'ui_off_text'   => __( 'No', 'dovira' ),
		'default_value' => 0,
	) );

	$fields->addTrueFalse( 'castration', array(
		'label'         => __( 'Castrated/Spayed', 'dovira' ),
		'ui'            => 1,
		'ui_on_text'    => __( 'Yes', 'dovira' ),
		'ui_off_text'   => __( 'No', 'dovira' ),
		'default_value' => 0,
		'required'      => true,
	) );

	$fields->addTrueFalse( 'donor_before', array(
		'label'         => __( 'Was donor before', 'dovira' ),
		'ui'            => 1,
		'ui_on_text'    => __( 'Yes', 'dovira' ),
		'ui_off_text'   => __( 'No', 'dovira' ),
		'default_value' => 0,
		'required'      => true,
	) );

	$fields->addTrueFalse( 'blood_take', array(
		'label'         => __( 'Received blood transfusion', 'dovira' ),
		'ui'            => 1,
		'ui_on_text'    => __( 'Yes', 'dovira' ),
		'ui_off_text'   => __( 'No', 'dovira' ),
		'default_value' => 0,
		'required'      => true,
	) );

	$fields->addTrueFalse( 'chronic_diseases', array(
		'label'         => __( 'Has chronic diseases', 'dovira' ),
		'ui'            => 1,
		'ui_on_text'    => __( 'Yes', 'dovira' ),
		'ui_off_text'   => __( 'No', 'dovira' ),
		'default_value' => 0,
		'required'      => true,
	) );

	$fields->addTrueFalse( 'pills', array(
		'label'         => __( 'Takes medication', 'dovira' ),
		'ui'            => 1,
		'ui_on_text'    => __( 'Yes', 'dovira' ),
		'ui_off_text'   => __( 'No', 'dovira' ),
		'default_value' => 0,
		'required'      => true,
	) );

	$fields->addText( 'pet_name', array(
		'label' => __( 'Pet name', 'dovira' ),
	) );

	$fields->setLocation( 'post_type', '==', 'questionary' );

	acf_add_local_field_group( $fields->build() );
}

add_action( 'acf/init', 'acf_add_post_type_questionary_fields' );
