<?php
/**
 * Entities Grid Block Template.
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
$entities            = dovira_get_acf_field( 'entities' );
$grid_columns        = dovira_get_acf_field( 'grid_columns' );
$entities_text_align = dovira_get_acf_field( 'entities_text_align' );
$entities_color      = dovira_get_acf_field( 'entities_color' );
$cta                 = dovira_get_acf_field( 'cta' );
$cta_style           = dovira_get_acf_field( 'cta_style' );
$cta_align           = dovira_get_acf_field( 'cta_align' );
$show_gradient_layer = dovira_get_acf_field( 'show_gradient_layer' );
$gradient_tone       = dovira_get_acf_field( 'gradient_tone' );
$gradient_direction  = dovira_get_acf_field( 'gradient_direction' );
$background_color    = dovira_get_acf_field( 'background_color' );
$margin_bottom       = dovira_get_acf_field( 'margin_bottom' );

?>
<?php if ( $is_visible || $is_preview ) : ?>
	<!-- ENTITY GRID start -->
	<section
		class="section section--mb-<?= $margin_bottom; ?> <?php if ( ! empty( $background_color ) ) : ?>section--with-bg<?php endif; ?> entities-grid <?php if ( ! $is_visible ) : ?>is-not-visible<?php endif; ?>"
		<?php if ( ! empty( $background_color ) ) : ?>style="background-color: <?= $background_color; ?>;"<?php endif; ?>>
		<?php if ( $show_gradient_layer ): ?>
			<div class="gradient-layer"
				 style="background: linear-gradient(to <?= str_replace( '_', ' ', $gradient_direction ); ?>, <?= $gradient_tone; ?>, rgba(0,0,0,0) )"
				 aria-hidden="true"></div>
		<?php endif; ?>
		<div class="wrapper entities-grid__wrapper">
			<header class="section__header section__header--align-<?= $header_text_align; ?>">
				<?php if ( ! empty( $heading ) ) : ?>
					<?= '<' . $heading_level . ' class="heading heading--' . $heading_style . '" style="color: ' . $heading_color . ';">' . $heading . '</' . $heading_level . '>'; ?>
				<?php endif; ?>
				<?php if ( ! empty( $caption ) ) : ?>
					<p class="entities-grid__caption caption" style="color: <?= $caption_color; ?>;">
						<?= $caption; ?>
					</p>
				<?php endif; ?>
			</header>
			<?php if ( ! empty( $entities ) ) : ?>
				<ul class="entities-grid__list entities-grid__list--columns-<?= $grid_columns; ?> entities-grid__list--align-<?= $entities_text_align; ?>"
					style="color: <?= $entities_color; ?>;">
					<?php foreach ( $entities as $entity ): ?>
						<li class="entities-grid__item">
							<?php if ( ! empty( $entity['cta'] ) ) : echo '<a href="' . $entity['cta']['url'] . '" target="' . $entity['cta']['target'] . '" class="entities-grid__link">'; endif; ?>
							<div class="entities-grid__image-holder">
								<?php if ( strpos( $entity['image']['mime_type'], 'svg' ) !== false ) : ?>
									<div class="entities-grid__icon"
										 style="width: <?= $entity['image_width']; ?>px; height: <?= $entity['image_width']; ?>px;">
										<img src="<?= $entity['image']['url']; ?>" alt="">
									</div>
								<?php else: ?>
									<?= wp_get_attachment_image( $entity['image']['id'], 'full', false, array(
										'class' => 'entities-grid__image',
									) ); ?>
								<?php endif; ?>
							</div>
							<div class="entities-grid__content-holder">
								<?php if ( ! empty( $entity['title'] ) ) : ?>
									<h3 class="entities-grid__title"><?= $entity['title']; ?></h3>
								<?php endif; ?>
								<?php if ( ! empty( $entity['caption'] ) ) : ?>
									<p class="entities-grid__text text"><?= $entity['caption']; ?></p>
								<?php endif; ?>
								<?php if ( ! empty( $entity['cta'] ) && ! empty( $entity['cta']['title'] ) ) : ?>
									<span class="entities-grid__item-cta">
										<?= $entity['cta']['title']; ?>
										<svg width="20px" height="20px" viewBox="0 0 24 24" fill="none"
											 xmlns="http://www.w3.org/2000/svg">
											<path d="M6 12H18M18 12L13 7M18 12L13 17" stroke="currentColor"
												  stroke-width="2" stroke-linecap="round"
												  stroke-linejoin="round"/>
										</svg>
									</span>
								<?php endif; ?>
							</div>
							<?php if ( ! empty( $entity['cta'] ) ) : echo '</a>'; endif; ?>
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
	<!-- ENTITY GRID end -->
<?php endif; ?>
