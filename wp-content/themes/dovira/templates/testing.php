<?php get_header();
$user = wp_get_current_user();
if ( $user->exists() ) :
	dovira_update_user_study_state( $user );
	$user_study_state = dovira_get_user_study_state( $user );
	$chapters         = dovira_get_hierarchical_chapters(); ?>
	<section id="sign-in"
			 class="section section--mb-none hero hero--small hero--middle"
			 style="background-color: #fff;">
		<div class="hero__wrapper hero__wrapper--center">
			<h1 class="hero__heading heading heading--h2"
				style="color: #1995ad;">Тестування</h1>
			<p class="caption" style="color: #000;">Вітаємо, <?= $user->first_name; ?>  <?= $user->last_name; ?>!<br>
				Оберіть розділ:</p>
		</div>
	</section>
	<section class="section section--mb-standard">
		<div class="wrapper">
			<?php if ( ! empty( $chapters ) ) : ?>
				<div class="chapters">
					<?php foreach ( $chapters as $chapter ) : ?>
						<div class="chapters__item">
							<h2 class="chapters__heading heading heading--h4"> <?= $chapter->name; ?></h2>
							<?php if ( ! empty( $chapter->children ) ) : ?>
								<div class="chapters__options">
									<?php foreach ( $chapter->children as $option ) :
										$option_status = 'new';
										if ( $user_study_state[ $option->term_id ]['count_passed'] > 0 ) :
											if ( $user_study_state[ $option->term_id ]['count_questions'] === $user_study_state[ $option->term_id ]['best_result'] ) :
												$option_status = 'success';
											elseif ( $user_study_state[ $option->term_id ]['best_result'] >= floor( $user_study_state[ $option->term_id ]['count_questions'] / 2 ) ) :
												$option_status = 'in-progress';
											else :
												$option_status = 'fault';
											endif;
										endif; ?>
										<a href="<?= get_term_link( $option->term_id ); ?>"
										   class="chapters__option chapters__option--<?= $option_status; ?>">
											<span class="chapters__option-title"><?= $option->name; ?></span>
											<span>Питань: <strong><?= $user_study_state[ $option->term_id ]['count_questions']; ?></strong></span>
											<span class="chapters__option-passed">Кращий результат: <strong><?= $user_study_state[ $option->term_id ]['best_result']; ?>/<?= $user_study_state[ $option->term_id ]['count_questions']; ?></strong></span>
											<span>Спроб: <strong><?= $user_study_state[ $option->term_id ]['count_passed']; ?></strong></span>
											<?php get_template_part( 'template-parts/option-status', '', array( 'status' => $option_status ) ); ?>
										</a>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
					<div class="chapters__actions">
						<p class="chapters__note">Натиснувши кнопку нижче, Ви можете скинути свій прогрес. <br>Увага! Цю
							дію немрожливо відмінити.</p>
						<button class="button button--red-border" id="reset-progress"
								data-nonce="<?= wp_create_nonce( 'wp_rest' ); ?>">Скинути прогрес
						</button>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<div class="loader loader--light">
		<span class="loader__component"></span>
	</div>
<?php else : ?>
<section id="sign-in"
			 class="section section--mb-none hero hero--small hero--middle"
			 style="background-color: #fff;">
		<div class="hero__wrapper hero__wrapper--center">
			<h1 class="hero__heading heading heading--h2"
				style="color: #1995ad;">Тестування</h1>
			<p class="caption" style="color: #000;">Для проходження тестування вам необхідно авторизуватись!</p>
			<div class="hero__ctas">
				<a href="/sign-in/" class="button button--cyan-border hero__cta">Увійти</a>
				<a href="/sign-up/" class="button button--cyan-border hero__cta">Зареєструватись</a>
			</div>
		</div>
	</section>
<?php endif; ?>
<?php get_footer(); ?>
