<?php get_header(); ?>
<main>
	<div class="not-found">
		<div class="wrapper not-found__wrapper">
			<img src="/wp-content/themes/dovira/assets/images/404.svg" alt="404" class="not-found__img">
			<div class="not-found__content">
				<h4>Ой! Хвостик заніс нас не туди</h4>
				<p>Схоже, ця сторінка втекла гуляти. <br>Але ми поруч і допоможемо знайти потрібний шлях.</p>
				<a href="<?= home_url(); ?>" class="button button--black">Повернутись на головну</a>
			</div>
		</div>
	</div>

</main>
<?php get_footer(); ?>
