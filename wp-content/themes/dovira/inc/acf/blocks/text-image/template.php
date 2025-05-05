<?php
/**
 * Text/Image Block Template.
 *
 * @var array $block The block settings and attributes.
 * @var string $content The block inner HTML (empty).
 * @var bool $is_preview True during backend preview render.
 * @var int $post_id The post ID the block is rendering content against.
 *          This is either the post ID currently being displayed inside a query loop,
 *          or the post ID of the post hosting this block.
 * @var array $context The context provided to the block by the post or it's parent block.
 */
$block_id            = ( isset( $block['anchor'] ) && ! empty( $block['anchor'] ) ) ? $block['anchor'] : $block['id'];
$is_visible          = dovira_get_acf_field( 'is_visible' );
$heading             = dovira_get_acf_field( 'heading' );
$heading_color       = dovira_get_acf_field( 'heading_color' );
$heading_level       = dovira_get_acf_field( 'heading_level' );
$heading_style       = dovira_get_acf_field( 'heading_style' );
$caption             = dovira_get_acf_field( 'caption' );
$caption_color       = dovira_get_acf_field( 'caption_color' );
$text                = dovira_get_acf_field( 'text' );
$text_color          = dovira_get_acf_field( 'text_color' );
$cta                 = dovira_get_acf_field( 'cta' );
$cta_style           = dovira_get_acf_field( 'cta_style' );
$cta_color           = dovira_get_acf_field( 'cta_color' );
$image               = dovira_get_acf_field( 'image' );
$horizontal_align    = dovira_get_acf_field( 'horizontal_align' );
$text_align          = dovira_get_acf_field( 'text_align' );
$order               = dovira_get_acf_field( 'order' );
$width_ratio         = dovira_get_acf_field( 'width_ratio' );
$show_gradient_layer = dovira_get_acf_field( 'show_gradient_layer' );
$gradient_tone       = dovira_get_acf_field( 'gradient_tone' );
$gradient_direction  = dovira_get_acf_field( 'gradient_direction' );
$background_color    = dovira_get_acf_field( 'background_color' );
$margin_bottom         = dovira_get_acf_field( 'margin_bottom' );

?>
<?php if ( $is_visible || $is_preview ) : ?>
	<!-- TEXT/IMAGE start -->
	<section
		class="section section--mb-<?= $margin_bottom; ?> <?php if ( ! empty( $background_color ) ) : ?>section--with-bg<?php endif; ?> text-image <?php if ( ! $is_visible ) : ?>is-not-visible<?php endif; ?>"
		<?php if ( ! empty( $background_color ) ) : ?>style="background-color: <?= $background_color; ?>;"<?php endif; ?>>
		<?php if ( $show_gradient_layer ): ?>
			<div class="gradient-layer"
				 style="background: linear-gradient(to <?= str_replace( '_', ' ', $gradient_direction ); ?>, <?= $gradient_tone; ?>, rgba(0,0,0,0) )"
				 aria-hidden="true"></div>
		<?php endif; ?>
		<div
			class="wrapper text-image__wrapper text-image__wrapper--<?= $width_ratio; ?> text-image__wrapper--<?= $horizontal_align; ?> text-image__wrapper--<?= $order; ?>">
			<div class="text-image__text-holder text-image__text-holder--<?= $text_align; ?>">
				<?php if ( ! empty( $heading ) ) : ?>
					<?= '<' . $heading_level . ' class="heading heading--' . $heading_style . '" style="color: ' . $heading_color . ';">' . $heading . '</' . $heading_level . '>'; ?>
				<?php endif; ?>
				<?php if ( ! empty( $caption ) ) : ?>
					<p class="caption" style="color: <?= $caption_color; ?>;">
						<?= $caption; ?>
					</p>
				<?php endif; ?>
				<?php if ( ! empty( $text ) ) : ?>
					<div class="text" style="color: <?= $text_color; ?>;">
						<?= $text; ?>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $cta ) ) : ?>
					<a href="<?= $cta['url']; ?>"
					   target="<?= $cta['target']; ?>"
					   class="text-image__cta button button--<?= $cta_style; ?>"
					   <?php if ( $cta_style === 'simple' ): ?>style="color: <?= $cta_color; ?>;"<?php endif; ?>>
						<?= $cta['title']; ?>
						<?php if ( $cta_style === 'simple' ): ?>
							<svg width="24px" height="24px" viewBox="0 0 24 24" fill="none"
								 xmlns="http://www.w3.org/2000/svg">
								<path fill-rule="evenodd" clip-rule="evenodd"
									  d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22ZM12.4697 8.46967C12.7626 8.17678 13.2374 8.17678 13.5303 8.46967L16.5303 11.4697C16.8232 11.7626 16.8232 12.2374 16.5303 12.5303L13.5303 15.5303C13.2374 15.8232 12.7626 15.8232 12.4697 15.5303C12.1768 15.2374 12.1768 14.7626 12.4697 14.4697L14.1893 12.75H8C7.58579 12.75 7.25 12.4142 7.25 12C7.25 11.5858 7.58579 11.25 8 11.25H14.1893L12.4697 9.53033C12.1768 9.23744 12.1768 8.76256 12.4697 8.46967Z"
									  fill="<?= $cta_color; ?>"/>
							</svg>
						<?php endif; ?>
					</a>
				<?php endif; ?>
			</div>
			<div class="text-image__image-holder">
				<?= wp_get_attachment_image( $image, 'full', false, array(
					'class' => 'text-image__image',
				) ); ?>
			</div>
		</div>
	</section>
	<!-- TEXT/IMAGE end -->
<?php endif; ?>
