<?php
/**
 * Text Image Block Fields.
 *
 * @package dovira
 */

use StoutLogic\AcfBuilder\FieldsBuilder;

$fields = new FieldsBuilder( 'text-image' );

$fields->addMessage( 'block-name', '<span style="font-weight: bold; font-size: 32px">TEXT / IMAGE</span>', array(
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

$fields->addText( 'heading', array(
	'label' => __( 'Heading', 'dovira' ),
) );

$fields->addColorPicker( 'heading_color', array(
	'label'         => __( 'Heading Color', 'dovira' ),
	'default_value' => '#000000',
) );

$fields->addSelect( 'heading_level', array(
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
	'wrapper'       => array(
		'width' => '50',
	)
) );

$fields->addSelect( 'heading_style', array(
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
	'wrapper'       => array(
		'width' => '50',
	)
) );

$fields->addTextarea( 'caption', array(
	'label'     => __( 'Caption', 'dovira' ),
	'rows'      => 3,
	'new_lines' => 'br',
) );

$fields->addColorPicker( 'caption_color', array(
	'label'         => __( 'Caption color', 'dovira' ),
	'default_value' => '#000000',
) );

$fields->addTextarea( 'text', array(
	'label'     => __( 'Text', 'dovira' ),
	'rows'      => 5,
	'new_lines' => 'br',
) );

$fields->addColorPicker( 'text_color', array(
	'label'         => __( 'Text color', 'dovira' ),
	'default_value' => '#000000',
) );

$fields->addLink( 'cta', array(
	'label' => __( 'CTA', 'dovira' ),
) );

$fields->addSelect( 'cta_style', array(
	'label'   => __( 'CTA Style', 'dovira' ),
	'choices' => array(
		'simple'       => __( 'Simple', 'dovira' ),
		'cyan'         => __( 'Cyan', 'dovira' ),
		'white'        => __( 'White', 'dovira' ),
		'cyan-border'  => __( 'Cyan Border', 'dovira' ),
		'white-border' => __( 'White Border', 'dovira' ),
	),
) );

$fields->addColorPicker( 'cta_color', array(
	'label'             => __( 'CTA Color', 'dovira' ),
	'conditional_logic' => array(
		array(
			array(
				'field'    => 'cta_style',
				'operator' => '==',
				'value'    => 'simple'
			),
		),
	),
) );

$fields->addImage( 'image', array(
	'label'         => __( 'Image', 'dovira' ),
	'required'      => true,
	'return_format' => 'id',
) );

$fields->addTab( 'align', array(
	'label' => __( 'Align', 'dovira' ),
) );

$fields->addSelect( 'order', array(
	'label'         => __( 'Text/Image order', 'dovira' ),
	'choices'       => array(
		'text-image' => __( 'Text/Image', 'dovira' ),
		'image-text' => __( 'Image/Text', 'dovira' ),
	),
	'default_value' => 'text-image',
) );

$fields->addSelect( 'width_ratio', array(
	'label'         => __( 'Text/Image Width Ratio', 'dovira' ),
	'choices'       => array(
		'1-1' => __( '1:1', 'dovira' ),
		'2-3' => __( '2:3', 'dovira' ),
		'3-2' => __( '3:2', 'dovira' ),
	),
	'default_value' => '1:1',
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

$fields->setLocation( 'block', '==', 'acf/text-image' );

return $fields;
