<?php
$seo_description = get_field( 'seo_description' );
?>

<?php if ( ! empty( $seo_description ) ) : ?>
	<div class="section seo-description">
		<div class="wrapper seo-description__wrapper">
			<div class="seo-description__content">
				<?= $seo_description; ?>
			</div>
		</div>
	</div>
<?php endif; ?>
