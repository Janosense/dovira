<?php
/**
 * CTA Block Template.
 *
 * @var array $block The block settings and attributes.
 * @var string $content The block inner HTML (empty).
 * @var bool $is_preview True during backend preview render.
 * @var int $post_id The post ID the block is rendering content against.
 *          This is either the post ID currently being displayed inside a query loop,
 *          or the post ID of the post hosting this block.
 * @var array $context The context provided to the block by the post or it's parent block.
 */
$block_id                 = ( isset( $block['anchor'] ) && ! empty( $block['anchor'] ) ) ? $block['anchor'] : $block['id'];
$is_visible               = dovira_get_acf_field( 'is_visible' );
$heading                  = dovira_get_acf_field( 'heading' );
$heading_color            = dovira_get_acf_field( 'heading_color' );
$heading_level            = dovira_get_acf_field( 'heading_level' );
$heading_style            = dovira_get_acf_field( 'heading_style' );
$caption                  = dovira_get_acf_field( 'caption' );
$caption_color            = dovira_get_acf_field( 'caption_color' );
$header_text_align        = dovira_get_acf_field( 'header_text_align' );
$show_gradient_layer      = dovira_get_acf_field( 'show_gradient_layer' );
$gradient_tone            = dovira_get_acf_field( 'gradient_tone' );
$gradient_direction       = dovira_get_acf_field( 'gradient_direction' );
$background_color_block   = dovira_get_acf_field( 'background_color_block' );
$background_color_content = dovira_get_acf_field( 'background_color_content' );
$background_image         = dovira_get_acf_field( 'background_image' );
$margin_bottom            = dovira_get_acf_field( 'margin_bottom' );
$ctas                     = dovira_get_acf_field( 'ctas' );
$ctas_align               = dovira_get_acf_field( 'ctas_align' );
$container_max_width      = dovira_get_acf_field( 'container_max_width' );

$wrapper_styles = '';
if ( ! empty( $container_max_width ) ) :
	$wrapper_styles .= 'max-width: ' . esc_attr( $container_max_width ) . 'px;';
endif;

if ( ! empty( $background_color_content ) ):
	$wrapper_styles .= 'background-color: ' . esc_attr( $background_color_content ) . ';';
endif;

?>
<?php if ( $is_visible || $is_preview ) : ?>
	<!-- CTA start -->
	<section id="<?= $block_id; ?>"
			 class="section section--mb-<?= $margin_bottom; ?> <?php if ( ! empty( $background_color_block ) ) : ?>section--with-bg<?php endif; ?> cta <?php if ( ! $is_visible ) : ?>is-not-visible<?php endif; ?>"
	         <?php if ( ! empty( $background_color_block ) ) : ?>style="background-color: <?= $background_color_block; ?>;"<?php endif; ?>>
		<?php if ( ! empty( $background_image ) ) :
			echo wp_get_attachment_image( $background_image['id'], 'full', false, array(
				'class' => 'section__background-image',
				'alt'   => $heading,
			) );
		endif; ?>
		<?php if ( $show_gradient_layer ): ?>
			<div class="gradient-layer"
				 style="background: linear-gradient(to <?= str_replace( '_', ' ', $gradient_direction ); ?>, <?= $gradient_tone; ?>, rgba(0,0,0,0) )"
				 aria-hidden="true"></div>
		<?php endif; ?>
		<div class="wrapper cta__wrapper <?php if (! empty( $wrapper_styles )) : ?>cta__wrapper--with-bg<?php endif; ?>"
		     <?php if ( ! empty( $wrapper_styles ) ) : ?>style="<?= $wrapper_styles; ?>"<?php endif; ?>>
			<?php if ( ! empty( $heading ) || ! empty( $caption ) ) : ?>
				<header class="section__header section__header--align-<?= $header_text_align; ?>">
					<?php if ( ! empty( $heading ) ) : ?>
						<?= '<' . $heading_level . ' class="heading heading--' . $heading_style . '" style="color: ' . $heading_color . ';">' . $heading . '</' . $heading_level . '>'; ?>
					<?php endif; ?>
					<?php if ( ! empty( $caption ) ) : ?>
						<p class="cta__caption caption" style="color: <?= $caption_color; ?>;">
							<?= $caption; ?>
						</p>
					<?php endif; ?>
				</header>
			<?php endif; ?>
			<?php if ( ! empty( $ctas ) ) : ?>
				<div
					class="section__cta-holder section__cta-holder--align-<?= $ctas_align; ?> section__cta-holder--grid-<?= count( $ctas ); ?>">
					<?php foreach ( $ctas as $cta ) : ?>
						<a href="<?= $cta['cta']['url']; ?>" target="<?= $cta['cta']['target']; ?>"
						   class="button button--<?= $cta['cta_style']; ?> section__cta">
							<?= $cta['cta']['title'];; ?>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<!-- CTA end -->
<?php endif; ?>
