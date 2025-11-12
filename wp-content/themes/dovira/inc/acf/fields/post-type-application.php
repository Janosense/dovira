<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

function acf_add_post_type_application_fields(): void {
	$fields = new FieldsBuilder( 'application', array(
		'title' => __( 'Application information', 'dovira' ),
	) );

	$fields->addSelect( 'status', array(
		'label'   => __( 'Status', 'dovira' ),
		'choices' => array(
			'new'                 => __( 'New', 'dovira' ),
			'in_processing'       => __( 'In processing', 'dovira' ),
			'interview_scheduled' => __( 'Interview scheduled', 'dovira' ),
			'rejected'            => __( 'Rejected', 'dovira' ),
			'closed'              => __( 'Closed', 'dovira' ),
		),
	) );

	$fields->addTextarea( 'note', array(
		'label' => __( 'Note', 'dovira' ),
	) );

	$fields->addText( 'first_name', array(
		'label'    => __( 'First name', 'dovira' ),
		'required' => true,
	) );

	$fields->addText( 'last_name', array(
		'label'    => __( 'First name', 'dovira' ),
		'required' => true,
	) );

	$fields->addText( 'phone', array(
		'label'    => __( 'Phone', 'dovira' ),
		'required' => true,
	) );

	$fields->addEmail( 'email', array(
		'label' => __( 'Email', 'dovira' ),
	) );

	$fields->addFile( 'file', array(
		'label' => __( 'File', 'dovira' ),
	) );

	$fields->addTextarea( 'file_error', array(
		'label' => __( 'File Error', 'dovira' ),
	) );

	$fields->addPostObject( 'vacancy', [
		'label'         => __( 'Vacancy', 'dovira' ),
		'post_type'     => [ 'vacancy' ],
		'taxonomy'      => [],
		'allow_null'    => 1,
		'multiple'      => 0,
		'return_format' => 'object',
		'ui'            => 1,
	] );

	$fields->setLocation( 'post_type', '==', 'application' );

	acf_add_local_field_group( $fields->build() );
}

add_action( 'acf/init', 'acf_add_post_type_application_fields' );
