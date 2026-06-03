<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

function acf_add_post_type_service_fields(): void {
	$fields = new FieldsBuilder( 'service', array(
		'title' => __( 'Service information', 'dovira' ),
	) );

	$fields->addImage( 'image', array(
		'label'    => __( 'Image', 'dovira' ),
		'required' => true,
	) );

	$fields->addWysiwyg( 'description', array(
		'label' => __( 'Description', 'dovira' ),
	) );

	$cities_choices = array();
	$cities         = get_terms( array(
		'taxonomy'   => 'service-city',
		'hide_empty' => false,
	) );

	if ( ! is_wp_error( $cities ) && ! empty( $cities ) ) {
		foreach ( $cities as $city ) {
			$cities_choices[ 'city_' . $city->term_id ] = $city->name;
		}
	}

	$repeater = $fields->addRepeater( 'prices', array(
		'label'  => __( 'Prices', 'dovira' ),
		'layout' => 'table',
	) );

	$repeater->addTextarea( 'title', array(
		'label' => __( 'Title', 'dovira' ),
		'rows'  => '5'
	) );
	$repeater->addTextarea( 'description', array(
		'label' => __( 'Description', 'dovira' ),
		'rows'  => '5'
	) );

	$price_group = $repeater->addGroup( 'city_prices', array(
		'label'             => __( 'Prices by city:', 'dovira' ),
		'conditional_logic' => array(
			array(
				array(
					'field'    => 'is_price_general',
					'operator' => '==',
					'value'    => 0
				),
			),
		),
	) );

	if ( ! is_wp_error( $cities ) && ! empty( $cities ) ) {
		foreach ( $cities as $city ) {
			$price_group->addNumber( 'price_city_' . $city->term_id, array(
				'label' => __( 'Price in ' . $city->name, 'dovira' ),
			) );
		}
	}

	$repeater->addNumber( 'price', array(
		'label'             => __( 'Price', 'dovira' ),
		'conditional_logic' => array(
			array(
				array(
					'field'    => 'is_price_general',
					'operator' => '==',
					'value'    => 1
				),
			),
		),
	) );

	$repeater->addLink( 'linked_page', array(
		'label' => __( 'Linked page', 'dovira' ),
		'wrapper'       => array(
			'width' => '11',
		)
	));

	$repeater->addCheckbox( 'cities', array(
		'label'         => __( 'Cities', 'dovira' ),
		'choices'       => $cities_choices,
		'return_format' => 'value',
		'wrapper'       => array(
			'width' => '9',
		)
	) );

	$repeater->addTrueFalse( 'is_price_general', array(
		'label'         => __( 'Is price general?', 'dovira' ),
		'ui'            => 1,
		'ui_on_text'    => __( 'Yes', 'dovira' ),
		'ui_off_text'   => __( 'No', 'dovira' ),
		'default_value' => 1,
		'wrapper'       => array(
			'width' => '9',
		)

	) );

	$fields->addTextarea( 'key_words', array(
		'label' => __( 'Keywords', 'dovira' ),
	) );

	$fields->setLocation( 'post_type', '==', 'service' )
	       ->and('page_template', '!=', 'templates/sub-service.php');

	acf_add_local_field_group( $fields->build() );
}

add_action( 'acf/init', 'acf_add_post_type_service_fields' );
