<?php get_header(); ?>
	<main>
		<?php while ( have_posts() ) : the_post(); ?>
			<?php if ( ! is_front_page() ) : ?>
				<!-- BREADCRUMBS start -->
				<div class="breadcrumbs">
					<div class="wrapper">
						<ul class="breadcrumbs__list">
							<li class="breadcrumbs__item">
								<a href="<?= home_url(); ?>" class="breadcrumbs__link">Головна</a>
							</li>
							<li class="breadcrumbs__item">
								<span class="breadcrumbs__current"><?php the_title(); ?></span>
							</li>
						</ul>
					</div>
				</div>
				<!-- BREADCRUMBS end -->
			<?php endif; ?>
			<?php the_content(); ?>
		<?php endwhile; ?>
	</main>
<?php get_footer();
