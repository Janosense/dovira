<?php
/**
 * Text Image Block Fields.
 *
 * @package dovira
 */

use StoutLogic\AcfBuilder\FieldsBuilder;

$fields = new FieldsBuilder( 'contacts' );

$fields->addMessage( 'block-name', '<span style="font-weight: bold; font-size: 32px">CONTACTS</span>', array(
	'label' => __( 'Block name', 'dovira' ),
) );

$fields->addTab( 'general', array(
	'label' => __( 'General', 'dovira' ),
) );

$fields->addTrueFalse( 'is_visible', array(
	'label'         => __( 'Is Visible?', 'dovira' ),
	'ui'            => 1,
	'ui_on_text'    => __( 'Yes', 'dovira' ),
	'ui_off_text'   => __( 'No', 'dovira' ),
	'default_value' => 1
) );

$fields->addTab( 'contacts_tab', array(
	'label' => __( 'Contacts', 'dovira' ),
) );

$fields->addRepeater( 'contacts', array(
	'label' => __( 'Contacts', 'dovira' ),
	'layout'       => 'block',
) )
       ->addText( 'city', array(
	       'label' => __( 'City', 'dovira' ),
       ) )
       ->addColorPicker( 'heading_color', array(
	       'label'         => __( 'Heading Color', 'dovira' ),
	       'default_value' => '#000000',
       ) )
       ->addSelect( 'heading_level', array(
	       'label'         => __( 'Heading Level (SEO)', 'dovira' ),
	       'choices'       => array(
		       'h1' => __( 'H1', 'dovira' ),
		       'h2' => __( 'H2', 'dovira' ),
		       'h3' => __( 'H3', 'dovira' ),
		       'h4' => __( 'H4', 'dovira' ),
		       'h5' => __( 'H5', 'dovira' ),
		       'h6' => __( 'H6', 'dovira' ),
	       ),
	       'return_format' => 'value',
	       'default_value' => 'h2',
       ) )
       ->addSelect( 'heading_style', array(
	       'label'         => __( 'Heading Level (Style)', 'dovira' ),
	       'choices'       => array(
		       'h1' => __( 'H1', 'dovira' ),
		       'h2' => __( 'H2', 'dovira' ),
		       'h3' => __( 'H3', 'dovira' ),
		       'h4' => __( 'H4', 'dovira' ),
		       'h5' => __( 'H5', 'dovira' ),
		       'h6' => __( 'H6', 'dovira' ),
	       ),
	       'return_format' => 'value',
	       'default_value' => 'h2',
       ) )
       ->addTextarea( 'map_iframe', array(
	       'label' => __( 'Map iframe', 'dovira' ),
	       'rows'  => 3,
       ) )
       ->addTextarea( 'address', array(
	       'label' => __( 'Address', 'dovira' ),
	       'rows'  => 3,
       ) )
       ->addTextarea( 'phones', array(
	       'label' => __( 'Phones', 'dovira' ),
	       'rows'  => 3,
       ) )
       ->addTextarea( 'emails', array(
	       'label' => __( 'Emails', 'dovira' ),
	       'rows'  => 3,
       ) )
       ->addTextarea( 'information', array(
	       'label' => __( 'Additional information', 'dovira' ),
	       'rows'  => 3,
       ) );

$fields->addTab( 'align', array(
	'label' => __( 'Align', 'dovira' ),
) );

$fields->addTrueFalse( 'view_is_row', array(
	'label'         => __( 'View: Row/Column', 'dovira' ),
	'ui'            => 1,
	'ui_on_text'    => __( 'Row', 'dovira' ),
	'ui_off_text'   => __( 'Column', 'dovira' ),
	'default_value' => 1
) );

$fields->addText( 'column_max_width', array(
	'label'             => __( 'Column max width', 'dovira' ),
	'conditional_logic' => array(
		array(
			array(
				'field'    => 'view_is_row',
				'operator' => '==',
				'value'    => 0
			),
		),
	),
	'default_value'     => 80
) );

$fields->addSelect( 'order', array(
	'label'         => __( 'Text/Map order', 'dovira' ),
	'choices'       => array(
		'text-map' => __( 'Text/Map', 'dovira' ),
		'map-text' => __( 'Map/Text', 'dovira' ),
	),
	'default_value' => 'text-image',
) );

$fields->addSelect( 'width_ratio', array(
	'label'             => __( 'Text/Image Width Ratio', 'dovira' ),
	'choices'           => array(
		'1-1' => __( '1:1', 'dovira' ),
		'2-3' => __( '2:3', 'dovira' ),
		'3-2' => __( '3:2', 'dovira' ),
	),
	'conditional_logic' => array(
		array(
			array(
				'field'    => 'view_is_row',
				'operator' => '==',
				'value'    => 1
			),
		),
	),
	'default_value'     => '2-3',
) );

$fields->addSelect( 'text_align', array(
	'label'         => __( 'Text alignment', 'dovira' ),
	'choices'       => array(
		'left'   => __( 'Left', 'dovira' ),
		'center' => __( 'Center', 'dovira' ),
		'right'  => __( 'Right', 'dovira' ),
	),
	'default_value' => 'center',
) );

$fields->addSelect( 'horizontal_align', array(
	'label'         => __( 'Vertical alignment', 'dovira' ),
	'choices'       => array(
		'top'    => __( 'Top', 'dovira' ),
		'middle' => __( 'Middle', 'dovira' ),
		'bottom' => __( 'Bottom', 'dovira' ),
	),
	'default_value' => 'middle',
) );

$fields->addTab( 'background', array(
	'label' => __( 'BG', 'dovira' ),
) );

$fields->addColorPicker( 'background_color', array(
	'label' => __( 'Background color', 'dovira' ),
) );

$fields->addImage( 'background_image', array(
	'label' => __( 'Background image', 'dovira' ),
) );

$fields->addTrueFalse( 'show_gradient_layer', array(
	'label'         => __( 'Show Gradient layer?', 'dovira' ),
	'ui'            => 1,
	'ui_on_text'    => __( 'Yes', 'dovira' ),
	'ui_off_text'   => __( 'No', 'dovira' ),
	'default_value' => 0
) );

$fields->addColorPicker( 'gradient_tone', array(
	'label'             => __( 'Gradient tone', 'dovira' ),
	'conditional_logic' => array(
		array(
			array(
				'field'    => 'show_gradient_layer',
				'operator' => '==',
				'value'    => 1
			),
		),
	),
	'enable_opacity'    => 1,
	'default_value'     => '#000000',
) );

$fields->addSelect( 'gradient_direction', array(
	'label'             => __( 'Height', 'dovira' ),
	'choices'           => array(
		'top'          => __( 'To Top', 'dovira' ),
		'bottom'       => __( 'To Bottom', 'dovira' ),
		'left'         => __( 'To Left', 'dovira' ),
		'bottom_left'  => __( 'To Bottom Left', 'dovira' ),
		'top_left'     => __( 'To Top Left', 'dovira' ),
		'right'        => __( 'To Right', 'dovira' ),
		'bottom_right' => __( 'To Bottom Right', 'dovira' ),
		'top_right'    => __( 'To Top Right', 'dovira' ),
	),
	'conditional_logic' => array(
		array(
			array(
				'field'    => 'show_gradient_layer',
				'operator' => '==',
				'value'    => 1
			),
		),
	),
	'default_value'     => 'left',
) );

$fields->addSelect( 'margin_bottom', array(
	'label'         => __( 'Bottom Indent', 'dovira' ),
	'choices'       => array(
		'none'     => __( 'None', 'dovira' ),
		'small'    => __( 'Small', 'dovira' ),
		'standard' => __( 'Standard', 'dovira' ),
	),
	'default_value' => 'standard',
) );

$fields->setLocation( 'block', '==', 'acf/contacts' );

return $fields;
