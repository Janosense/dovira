<?php
if ( ! is_user_logged_in() ) :
	wp_safe_redirect( home_url() );
endif;

get_header( null, [
	'mode' => 'simple',
] );
global $post;
$vacancy_id = get_field( 'vacancy', $post->ID );
$file       = get_field( 'file', $post->ID );
$email      = get_field( 'email', $post->ID );
$phone      = get_field( 'phone', $post->ID );
$note       = get_field( 'note', $post->ID );
$status     = get_field( 'status', $post->ID );

if ( $vacancy_id ) :
	$vacancy = get_post( $vacancy_id );
endif;
$status_names_map = [
	'new'                 => 'Нова заявка',
	'in_processing'       => 'В обробці',
	'interview_scheduled' => 'Заплановано співбесіду',
	'rejected'            => 'Відхилено',
	'closed'              => 'Закрито',
];
?>
<main>
	<div class="application">
		<div class="wrapper">
			<h1 class="heading heading--h3 application__heading">Заявка на вакансію "<?= $vacancy->post_title; ?>"</h1>
			<div class="application__content">
				<?php if ( ! empty( $status ) ) : ?>
					<div class="application__row">
						<div
							class="application__status application__status--<?= $status; ?>"><?= $status_names_map[ $status ]; ?></div>
					</div>
				<?php endif; ?>
				<div class="application__row">
					<span class="application__row-title">Імʼя, Прізвище:</span>
					<h1 class="application__heading">
						<span><?= $post->post_title; ?></span>
						<button type="button" class="application__copy-button"><img
								src="/wp-content/themes/dovira/assets/images/icon-copy.svg" alt="Copy"></button>
					</h1>
				</div>
				<?php if ( isset( $vacancy ) && ! empty( $vacancy ) ) : ?>
					<div class="application__row">
						<span class="application__row-title">Вакансія:</span>
						<div class="application__row-value application__row-value--small">
							<span><?= $vacancy->post_title; ?></span>
							<button type="button" class="application__copy-button"><img
									src="/wp-content/themes/dovira/assets/images/icon-copy.svg" alt="Copy"></button>
						</div>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $email ) ) : ?>
					<div class="application__row">
						<span class="application__row-title">Email:</span>
						<div class="application__row-value application__row-value--small">
							<span><?= $email; ?></span>
							<button type="button" class="application__copy-button"><img
									src="/wp-content/themes/dovira/assets/images/icon-copy.svg" alt="Copy"></button>
						</div>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $phone ) ) : ?>
					<div class="application__row">
						<span class="application__row-title">Телефон:</span>
						<div class="application__row-value application__row-value--small">
							<span><?= $phone; ?></span>
							<button type="button" class="application__copy-button"><img
									src="/wp-content/themes/dovira/assets/images/icon-copy.svg" alt="Copy"></button>
						</div>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $note ) ) : ?>
					<div class="application__row">
						<span class="application__row-title">Примітки адміністратора:</span>
						<div class="application__row-value application__row-value--text">
							<span><?= $note; ?></span>
							<button type="button" class="application__copy-button"><img
									src="/wp-content/themes/dovira/assets/images/icon-copy.svg" alt="Copy"></button>
						</div>
					</div>
				<?php endif; ?>
				<div class="application__row">
					<?php if ( ! empty( $file ) && isset( $file['url'] ) ) : ?>
						<a href="<?= $file['url']; ?>" target="_blank" class="button button--black application__cv">Переглянути
							Резюме</a>
					<?php else : ?>
						<span class="application__row-title">Резюме відсутнє</span>
					<?php endif; ?>
				</div>
			</div>
			<div class="application__actions">
				<button class="button button--violet" type="button" onclick="print()">Друк</button>
			</div>
		</div>
	</div>
	<span class="application__copy-message">
		Текст скопійовано!
	</span>
</main>

<script>
	const copy = (evt) => {
		let copyText = evt.currentTarget.previousElementSibling;
		navigator.clipboard.writeText(copyText.textContent).then(() => {
			document.querySelector(".application__copy-message").classList.add("application__copy-message--active");
			setTimeout(() => {
				document.querySelector(".application__copy-message").classList.remove("application__copy-message--active");
			}, 1000);
		})
	}
	const copyButton = document.querySelectorAll(".application__copy-button");
	copyButton.forEach((button) => {
		button.addEventListener("click", copy);
	});

</script>

<?php $applications = get_posts([
	'post_type' => 'application',
	'numberposts' => -1,
	'meta_query' => [
		[
			'key' => 'status',
			'value' => 'new'
		]
	]
]);
$test = $applications;

?>
