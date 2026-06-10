<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

function acf_add_post_type_vacancy_fields(): void {
	$fields = new FieldsBuilder( 'vacancy', array(
		'title' => __( 'Vacancy information', 'dovira' ),
	) );

	$fields->addTrueFalse( 'is_open', array(
		'label'         => __( 'Is Vacancy Open', 'dovira' ),
		'ui'            => 1,
		'ui_on_text'    => __( 'Open', 'dovira' ),
		'ui_off_text'   => __( 'Close', 'dovira' ),
		'default_value' => 1
	) );

	$fields->addWysiwyg( 'description', array(
		'label'    => __( 'Description', 'dovira' ),
		'required' => 1,
	) );

	$fields->addSelect( 'salary_type', array(
		'label'   => __( 'Salary Type', 'dovira' ),
		'choices' => array(
			'static' => __( 'Static', 'dovira' ),
			'from'   => __( 'From', 'dovira' ),
			'range'  => __( 'Range', 'dovira' ),
		),
		'default' => 'static',
	) );

	$fields->addNumber( 'static_salary', array(
		'label'             => __( 'Salary', 'dovira' ),
		'conditional_logic' => array(
			array(
				array(
					'field'    => 'salary_type',
					'operator' => '==',
					'value'    => 'static',
				),
			),
		),
		'wrapper'           => array(
			'width' => '50',
		)
	) );

	$fields->addNumber( 'salary_from', array(
		'label'   => __( 'Salary From', 'dovira' ),
		'wrapper' => array(
			'width' => '50',
		)
	) )->conditional( 'salary_type', '==', 'from' )
	       ->or( 'salary_type', '==', 'range' );

	$fields->addNumber( 'salary_to', array(
		'label'             => __( 'Salary To', 'dovira' ),
		'conditional_logic' => array(
			array(
				array(
					'field'    => 'salary_type',
					'operator' => '==',
					'value'    => 'range',
				),
			),
		),
		'wrapper'           => array(
			'width' => '50',
		)
	) );

	$fields->addRepeater( 'contacts', array(
		'label' => __( 'Contacts', 'dovira' ),
		'button_label' => __( 'Add Phone', 'dovira' ),
	) )->addText( 'phone' );

	$fields->addWysiwyg( 'seo_description', array(
		'label' => __( 'SEO description', 'dovira' ),
	));

	$fields->setLocation( 'post_type', '==', 'vacancy' );

	acf_add_local_field_group( $fields->build() );
}

add_action( 'acf/init', 'acf_add_post_type_vacancy_fields' );
