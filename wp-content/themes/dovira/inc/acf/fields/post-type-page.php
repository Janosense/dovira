<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

function acf_add_post_type_page_fields(): void {
	$fields = new FieldsBuilder( 'page', array(
		'title' => __( 'Page custom fields', 'dovira' ),
	) );

	$fields->addWysiwyg( 'seo_description', array(
		'label' => __( 'SEO description', 'dovira' ),
	));

	$fields->setLocation( 'post_type', '==', 'page' );

	acf_add_local_field_group( $fields->build() );
}

add_action( 'acf/init', 'acf_add_post_type_page_fields' );
