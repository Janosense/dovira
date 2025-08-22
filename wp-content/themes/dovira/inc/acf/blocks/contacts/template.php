<?php
/**
 * Contacts Block Template.
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
$header_text_align   = dovira_get_acf_field( 'header_text_align' );
$horizontal_align    = dovira_get_acf_field( 'horizontal_align' );
$text_align          = dovira_get_acf_field( 'text_align' );
$order               = dovira_get_acf_field( 'order' );
$view_is_row         = dovira_get_acf_field( 'view_is_row' );
$column_max_width    = dovira_get_acf_field( 'column_max_width' );
$width_ratio         = dovira_get_acf_field( 'width_ratio' );
$show_gradient_layer = dovira_get_acf_field( 'show_gradient_layer' );
$gradient_tone       = dovira_get_acf_field( 'gradient_tone' );
$gradient_direction  = dovira_get_acf_field( 'gradient_direction' );
$background_color    = dovira_get_acf_field( 'background_color' );
$margin_bottom       = dovira_get_acf_field( 'margin_bottom' );
$contacts            = dovira_get_acf_field( 'contacts' );
$contacts_cities     = dovira_get_acf_field( 'contacts_cities', 'option' );
if ( ! empty( $contacts ) ) :
	$contacts = array_merge( $contacts_cities, $contacts );
else :
	$contacts = $contacts_cities;
endif;
?>
<?php if ( $is_visible || $is_preview ) : ?>
	<!-- CONTACTS start -->
	<section
		class="section section--mb-<?= $margin_bottom; ?> <?php if ( ! empty( $background_color ) ) : ?>section--with-bg<?php endif; ?> contacts <?php if ( ! $is_visible ) : ?>is-not-visible<?php endif; ?>"
		<?php if ( ! empty( $background_color ) ) : ?>style="background-color: <?= $background_color; ?>;"<?php endif; ?>>
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
		<?php if ( ! empty( $contacts ) ) : ?>
			<?php foreach ( $contacts as $contact ) : ?>
				<div
					class="wrapper contacts__wrapper contacts__wrapper--<?= $view_is_row ? 'row' : 'column'; ?> <?php if ( $view_is_row ) : ?>contacts__wrapper--<?= $width_ratio; ?> contacts__wrapper--<?= $horizontal_align; ?><?php endif; ?> contacts__wrapper--<?= $order; ?>">
					<div class="contacts__text-holder contacts__text-holder--<?= $text_align; ?>"
						 <?php if ( ! $view_is_row ) : ?>style="max-width: <?= $column_max_width; ?>%;" <?php endif; ?>>
						<?php if ( ! empty( $contact['city'] ) ) :
							if (empty($contact['heading_level'])) $contact['heading_level'] = 'h2';
							if (empty($contact['heading_style'])) $contact['heading_style'] = 'h2';
							if (empty($contact['heading_color'])) $contact['heading_color'] = '#3c3c3c';
							?>
							<?= '<' . $contact['heading_level'] . ' class="heading heading--' . $contact['heading_style'] . '" style="color: ' . $contact['heading_color'] . ';">' . $contact['city'] . '</' . $contact['heading_level'] . '>'; ?>
						<?php endif; ?>
						<?php if ( ! empty( $contact['address'] ) ) : ?>
							<p class="caption contacts__address">
								<?= $contact['address']; ?>
							</p>
							<?php if ( ! empty( $contact['note'] ) ) : ?>
								<p class="contacts__note">
									<?= $contact['note']; ?>
								</p>
							<?php endif; ?>
							<?php if ( ! empty( $contact['schedule'] ) ) : ?>
								<p class="caption contacts__schedule">
									<?= $contact['schedule']; ?>
								</p>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ( ! empty( $contact['phones'] ) ) :
							if ( ! is_array( $contact['phones'] ) ) :
								$phones = explode( "\r\n", $contact['phones'] ); ?>
								<div class="contacts__phones">
									<?php foreach ( $phones as $phone ) : ?>
										<a href="tel:<?= str_replace( [
											' ',
											'-',
											'(',
											')'
										], '', $phone ); ?>"><?= $phone; ?></a>
									<?php endforeach; ?>
								</div>
							<?php else :
								$phones = $contact['phones']; ?>
								<div class="contacts__phones">
									<?php foreach ( $phones as $phone ) : ?>
										<a href="tel:<?= str_replace( [
											' ',
											'-',
											'(',
											')'
										], '', $phone['number'] ); ?>"><?= $phone['number']; ?></a>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ( ! empty( $contact['emails'] ) ) :
							$emails = explode( "\r\n", $contact['emails'] ); ?>
							<div class="contacts__phones">
								<?php foreach ( $emails as $email ) : ?>
									<a href="mailto:<?= $email; ?>"><?= $email; ?></a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
					<div class="contacts__map-holder"
						 <?php if ( ! $view_is_row ) : ?>style="max-width: <?= $column_max_width; ?>%;" <?php endif; ?>>
						<?= $contact['map_iframe']; ?>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</section>
	<!-- CONTACTS end -->
	<script>
		(() => {
			const cta = document.querySelector('form .button');
			const formHolder = document.querySelector('.text-form__form-holder');
			if (cta) {
				cta.className = 'button button--' + formHolder.getAttribute('data-form_cta_style');
			}
		})();
	</script>
<?php endif; ?>
