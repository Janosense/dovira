<?php
/**
 * Files Block Fields.
 *
 * @package dovira
 */

use StoutLogic\AcfBuilder\FieldsBuilder;

$fields = new FieldsBuilder( 'files' );

$fields->addMessage( 'block-name', '<span style="font-weight: bold; font-size: 32px">FILES</span>', array(
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

$fields->addSelect( 'header_text_align', array(
	'label'         => __( 'Header Text alignment', 'dovira' ),
	'choices'       => array(
		'left'   => __( 'Left', 'dovira' ),
		'center' => __( 'Center', 'dovira' ),
		'right'  => __( 'Right', 'dovira' ),
	),
	'default_value' => 'center',
) );

$fields->addLink( 'cta', array(
	'label' => __( 'CTA', 'dovira' ),
) );

$fields->addSelect( 'cta_style', array(
	'label'   => __( 'CTA Style', 'dovira' ),
	'choices' => array(
		'cyan'         => __( 'Cyan', 'dovira' ),
		'white'        => __( 'White', 'dovira' ),
		'cyan-border'  => __( 'Cyan Border', 'dovira' ),
		'white-border' => __( 'White Border', 'dovira' ),
	),
) );

$fields->addSelect( 'cta_align', array(
	'label'         => __( 'CTA alignment', 'dovira' ),
	'choices'       => array(
		'left'   => __( 'Left', 'dovira' ),
		'center' => __( 'Center', 'dovira' ),
		'right'  => __( 'Right', 'dovira' ),
	),
	'default_value' => 'center',
) );


$fields->addTab( 'files_tab', array(
	'label' => __( 'Files', 'dovira' ),
) );

$fields->addTrueFalse( 'is_all_files_visible', array(
	'label'         => __( 'Is all Files visible?', 'dovira' ),
	'ui'            => 1,
	'ui_on_text'    => __( 'Yes', 'dovira' ),
	'ui_off_text'   => __( 'No', 'dovira' ),
	'default_value' => 0
) );

$fields->addNumber( 'files_count', array(
	'label'         => __( 'Number of visible Files', 'dovira' ),
	'default_value' => 4,
	'conditional_logic' => array(
		array(
			array(
				'field'    => 'is_all_files_visible',
				'operator' => '==',
				'value'    => 0
			),
		),
	),
));

$fields->addRepeater( 'files', array(
	'label'  => __( 'Files', 'dovira' ),
	'layout' => 'block',
) )
       ->addText( 'title', array(
	       'label' => __( 'Title', 'dovira' ),
       ) )
       ->addTextarea( 'caption', array(
	       'label' => __( 'Caption', 'dovira' ),
       ) )
       ->addFile( 'file', array(
	       'label' => __( 'File', 'dovira' ),
       ) )
       ->addSelect( 'type', array(
	       'label'         => __( 'File type', 'dovira' ),
	       'choices'       => array(
		       'certificate' => __( 'Certificate', 'dovira' ),
		       'diploma'     => __( 'Diploma', 'dovira' ),
		       'document'    => __( 'Document', 'dovira' ),
	       ),
	       'default_value' => 'certificate',
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

$fields->setLocation( 'block', '==', 'acf/files' );

return $fields;
