<?php
/**
 * Template Name: Sub-Service
 * Template Post Type: service
 */

get_header();
global $post; ?>
<!-- BREADCRUMBS start -->
<div class="breadcrumbs">
	<div class="wrapper">
		<ul class="breadcrumbs__list">
			<li class="breadcrumbs__item">
				<a href="<?= home_url(); ?>" class="breadcrumbs__link">Головна</a>
			</li>
			<li class="breadcrumbs__item">
				<a href="/services/" class="breadcrumbs__link">Послуги</a>
			</li>
			<?php if ( ! empty( $post->post_parent ) ) : ?>
				<li class="breadcrumbs__item">
					<a href="<?= get_permalink( $post->post_parent ); ?>"
					   class="breadcrumbs__link"><?= get_the_title( $post->post_parent ); ?></a>
				</li>
			<?php endif; ?>
			<li class="breadcrumbs__item">
				<span class="breadcrumbs__current"><?php the_title(); ?></span>
			</li>
		</ul>
	</div>
</div>
<!-- BREADCRUMBS end -->
<?php while ( have_posts() ) :
	the_post(); ?>
	<section class="section section--mb-small">
		<div class="wrapper">
			<header class="section__header">
				<h1 class="heading heading--h1"><?php the_title(); ?></h1>
			</header>
		</div>
	</section>
	<?php the_content(); ?>
<?php endwhile; ?>

<?php get_footer(); ?>
