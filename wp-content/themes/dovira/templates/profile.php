<?php
// TODO: Remove redirect after Profile implementation
wp_safe_redirect( '/testing/' );
$user = wp_get_current_user();
if ( $user->exists() ) :
	get_header();
	dovira_update_user_study_state( $user ); ?>
	<section id="sign-in"
			 class="section section--mb-none hero hero--small hero--middle"
			 style="background-color: #fff;">
		<div class="hero__wrapper hero__wrapper--center">
			<h1 class="hero__heading heading heading--h2"
				style="color: #1995ad;">Кабінет</h1>
			<p class="caption" style="color: #000;">Вітаємо, <?= $user->first_name; ?>  <?= $user->last_name; ?>!</p>
		</div>
	</section>
<?php else :
	wp_safe_redirect( home_url() );
endif;
?>
<?php get_footer(); ?>
