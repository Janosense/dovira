<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

function acf_add_post_type_questionary_fields(): void {
	$fields = new FieldsBuilder( 'post-type-questionary', array(
		'title' => __( 'Questionary information', 'dovira' ),
	) );

	$fields->addTextarea( 'admin_note', array(
		'label' => __( 'Note', 'dovira' ),
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

	$fields->addText( 'city', array(
		'label'    => __( 'City', 'dovira' ),
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

	$fields->addSelect( 'blood_group', array(
		'label'    => __( 'Blood group', 'dovira' ),
		'choices'  => array(
			'all-dont-know' => __( 'Don\'t know', 'dovira' ),
			'cat-a'         => __( 'Cat - A', 'dovira' ),
			'cat-b'         => __( 'Cat - B', 'dovira' ),
			'cat-AB'        => __( 'Cat - AB', 'dovira' ),
			'dog-dea-1.1'   => __( 'Dog - DEA 1.1', 'dovira' ),
			'dog-dea-1.2'   => __( 'Dog - DEA 1.2', 'dovira' ),
			'dog-dea-1.3'   => __( 'Dog - DEA 1.3', 'dovira' ),
			'dog-dea-2'     => __( 'Dog - DEA 2', 'dovira' ),
			'dog-dea-3'     => __( 'Dog - DEA 3', 'dovira' ),
			'dog-dea-4'     => __( 'Dog - DEA 4', 'dovira' ),
			'dog-dea-5'     => __( 'Dog - DEA 5', 'dovira' ),
			'dog-dea-7'     => __( 'Dog - DEA 7', 'dovira' ),
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
	) );

	$fields->addTrueFalse( 'donor_before', array(
		'label'         => __( 'Was donor before', 'dovira' ),
		'ui'            => 1,
		'ui_on_text'    => __( 'Yes', 'dovira' ),
		'ui_off_text'   => __( 'No', 'dovira' ),
		'default_value' => 0,
	) );

	$fields->addTrueFalse( 'blood_take', array(
		'label'         => __( 'Received blood transfusion', 'dovira' ),
		'ui'            => 1,
		'ui_on_text'    => __( 'Yes', 'dovira' ),
		'ui_off_text'   => __( 'No', 'dovira' ),
		'default_value' => 0,
	) );

	$fields->addTrueFalse( 'chronic_diseases', array(
		'label'         => __( 'Has chronic diseases', 'dovira' ),
		'ui'            => 1,
		'ui_on_text'    => __( 'Yes', 'dovira' ),
		'ui_off_text'   => __( 'No', 'dovira' ),
		'default_value' => 0,
	) );

	$fields->addTrueFalse( 'pills', array(
		'label'         => __( 'Takes medication', 'dovira' ),
		'ui'            => 1,
		'ui_on_text'    => __( 'Yes', 'dovira' ),
		'ui_off_text'   => __( 'No', 'dovira' ),
		'default_value' => 0,
	) );

	$fields->addText( 'pet_name', array(
		'label' => __( 'Pet name', 'dovira' ),
	) );

	$fields->setLocation( 'post_type', '==', 'questionary' );

	acf_add_local_field_group( $fields->build() );
}

add_action( 'acf/init', 'acf_add_post_type_questionary_fields' );
