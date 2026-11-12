<?php get_header(); ?>
<main>
	<div class="not-found">
		<div class="wrapper not-found__wrapper">
			<img src="/wp-content/themes/dovira/assets/images/404.svg" alt="404" class="not-found__img">
			<div class="not-found__content">
				<h4><?= dovira_translate_string( 'Oops! The tail took us the wrong way' ); ?></h4>
				<p><?= dovira_translate_string( 'Looks like this page ran off for a walk.' ); ?> <br><?= dovira_translate_string( 'But we are nearby and will help you find the right way.' ); ?></p>
				<a href="<?= home_url(); ?>" class="button button--black"><?= dovira_translate_string( 'Back to the home page' ); ?></a>
			</div>
		</div>
	</div>

</main>
<?php get_footer(); ?>
