<?php get_header(); ?>
	<main>
		<?php while ( have_posts() ) : the_post(); ?>
			<!-- BREADCRUMBS start -->
			<div class="breadcrumbs">
				<div class="wrapper wrapper--tight">
					<ul class="breadcrumbs__list">
						<li class="breadcrumbs__item">
							<a href="<?= home_url(); ?>" class="breadcrumbs__link">Головна</a>
						</li>
						<li class="breadcrumbs__item">
							<a href="/news/" class="breadcrumbs__link">Новини</a>
						</li>
						<li class="breadcrumbs__item">
							<span class="breadcrumbs__current"><?php the_title(); ?></span>
						</li>
					</ul>
				</div>
			</div>
			<!-- BREADCRUMBS end -->
			<article class="article">
				<div class="wrapper wrapper--tight article__wrapper">
					<div class="article__meta"><?php the_date( 'd.m.Y' ) ?></div>
					<h1 class="heading heading--h2"><?php the_title(); ?></h1>
					<?php if ( ! empty( get_the_excerpt() ) ) : ?>
						<div class="article__excerpt">
							<?php the_excerpt(); ?>
						</div>
					<?php endif; ?>
					<?php the_post_thumbnail( 'full', [
						'class' => 'article__thumbnail',
					] ); ?>
					<div class="article__content">
						<?php the_content(); ?>
					</div>
				</div>
			</article>
		<?php endwhile; ?>
		<?php
		$posts = get_posts( array(
			'numberposts'  => 3,
			'post_type'    => 'post',
			'post__not_in' => array( $post->ID ),
			'fields'       => 'ids',
		) );
		if ( ! empty( $posts ) ) :
			acf_render_block( [
				'name' => 'acf/news',
				'id'   => 'article-news-' . $post->ID,
				'data' => [
					'is_visible' => 1,
					'_is_visible' => 'field_news_is_visible',
					'heading' => 'Схожі новини',
					'_heading' => 'field_news_heading',
					'heading_level' => 'h2',
					'_heading_level' => 'field_news_heading_level',
					'is_latest_news' => 0,
					'_is_latest_news' => 'field_news_is_latest_news',
					'news' => $posts,
					'_news' => 'field_news_news',
					'grid_columns' => 3,
					'_grid_columns' => 'field_news_grid_columns',
					'margin_bottom' => 'standard',
					'_margin_bottom' => 'field_news_margin_bottom',
				],
			], '', false, $post->ID, null, [
				'postId'   => $post->ID,
				'postType' => 'post',
			] );
		endif; ?>
	</main>
<?php get_footer();
