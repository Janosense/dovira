<?php
/**
 * Entity Links Block Template.
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
$header_text_align   = dovira_get_acf_field( 'header_text_align' );
$show_gradient_layer = dovira_get_acf_field( 'show_gradient_layer' );
$gradient_tone       = dovira_get_acf_field( 'gradient_tone' );
$gradient_direction  = dovira_get_acf_field( 'gradient_direction' );
$background_color    = dovira_get_acf_field( 'background_color' );
$background_image    = dovira_get_acf_field( 'background_image' );
$margin_bottom       = dovira_get_acf_field( 'margin_bottom' );
$cta                 = dovira_get_acf_field( 'cta' );
$cta_align           = dovira_get_acf_field( 'cta_align' );
$cta_style           = dovira_get_acf_field( 'cta_style' );
$entity              = dovira_get_acf_field( 'entity' );
$links               = [];

switch ( $entity ) :
	case 'custom':
		$custom_links = dovira_get_acf_field( 'custom_links' );
		if ( ! empty( $custom_links ) ) :
			foreach ( $custom_links as $link ) :
				$links[] = [
					'url'    => $link['url'],
					'title'  => $link['title'],
					'target' => $link['target'],
				];
			endforeach;
		endif;
		break;
	case 'posts':
		$posts = dovira_get_acf_field( 'posts' );
		if ( ! empty( $posts ) ) :
			foreach ( $posts as $post_object ) :
				$links[] = [
					'url'    => get_permalink( $post_object->ID ),
					'title'  => $post_object->post_title,
					'target' => '_self',
				];
			endforeach;
		endif;
		break;
endswitch;

?>
<?php if ( $is_visible || $is_preview ) : ?>
	<!-- ENTITY LINKS start -->
	<section
		class="section section--mb-<?= $margin_bottom; ?> <?php if ( ! empty( $background_color ) ) : ?>section--with-bg<?php endif; ?> entity-links <?php if ( ! $is_visible ) : ?>is-not-visible<?php endif; ?>"
		<?php if ( ! empty( $background_color ) ) : ?>style="background-color: <?= $background_color; ?>;"<?php endif; ?>>
		<?php if ( ! empty( $background_image ) ) :
			echo wp_get_attachment_image( $background_image['id'], 'full', false, array(
				'class' => 'section__background-image',
			) );
		endif; ?>
		<?php if ( $show_gradient_layer ): ?>
			<div class="gradient-layer"
				 style="background: linear-gradient(to <?= str_replace( '_', ' ', $gradient_direction ); ?>, <?= $gradient_tone; ?>, rgba(0,0,0,0) )"
				 aria-hidden="true"></div>
		<?php endif; ?>
		<div class="wrapper entity-links__wrapper">
			<?php if ( ! empty( $heading ) || ! empty( $caption ) ) : ?>
				<header class="section__header section__header--align-<?= $header_text_align; ?>">
					<?php if ( ! empty( $heading ) ) : ?>
						<?= '<' . $heading_level . ' class="heading heading--' . $heading_style . '" style="color: ' . $heading_color . ';">' . $heading . '</' . $heading_level . '>'; ?>
					<?php endif; ?>
					<?php if ( ! empty( $caption ) ) : ?>
						<p class="caption" style="color: <?= $caption_color; ?>;">
							<?= $caption; ?>
						</p>
					<?php endif; ?>
				</header>
			<?php endif; ?>
			<?php if ( ! empty( $links ) ) : ?>
			<ul class="entity-links__list">
				<?php foreach ($links as $link) : ?>
				<li class="entity-links__item">
					<a href="<?= $link['url']; ?>"
					   class="entity-links__link"
					   target="<?= $link['target']; ?>">
						<?= $link['title']; ?>
					</a>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
			<?php if ( ! empty( $cta ) ) : ?>
				<div class="section__cta-holder section__cta-holder--align-<?= $cta_align; ?>">
					<a href="<?= $cta['url']; ?>" target="<?= $cta['target']; ?>"
					   class="button button--<?= $cta_style; ?> section__cta">
						<?= $cta['title'];; ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<!-- ENTITY LINKS end -->
<?php endif; ?>
