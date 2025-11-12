<?php
/**
 * Handles operations prior to sending the Contact Form 7 email.
 *
 * This function processes submitted form data to create a custom post type entry
 * and optionally uploads and associates a file attachment with the post.
 *
 * @param WPCF7_ContactForm $form The instance of the submitted contact form.
 * @param bool              &$abort Reference to abort email sending if necessary.
 * @param WPCF7_Submission $submission The instance of the current form submission.
 *
 * @return void
 */
function dovira_wpcf7_before_send_mail( $form, &$abort, $submission ): void {
	if ( ! empty( $form ) ) {
		switch ( $form->id() ) {
			case 1280:
				$post_data = array(
					'post_title'  => sanitize_text_field( $_POST['your-first-name'] ) . ' ' . sanitize_text_field( $_POST['your-last-name'] ),
					'post_status' => 'publish',
					'post_author' => 1,
					'post_type'   => 'application',
				);

				$post_id = wp_insert_post( $post_data );

				if ( ! is_wp_error( $post_id ) ) {
					update_field( 'status', 'new', $post_id );
					update_field( 'first_name', sanitize_text_field( $_POST['your-first-name'] ), $post_id );
					update_field( 'last_name', sanitize_text_field( $_POST['your-last-name'] ), $post_id );
					update_field( 'phone', sanitize_text_field( $_POST['your-phone'] ), $post_id );
					update_field( 'email', sanitize_text_field( $_POST['your-email'] ), $post_id );
					$vacancy_id = sanitize_text_field( $_POST['vacancy'] );
					$vacancy    = get_post( $vacancy_id );
					update_field( 'vacancy', $vacancy_id, $post_id );

					if ( isset( $_FILES['cv'] ) && ! empty( $_FILES['cv'] ) ) {
						require_once ABSPATH . 'wp-admin/includes/image.php';
						require_once ABSPATH . 'wp-admin/includes/file.php';
						require_once ABSPATH . 'wp-admin/includes/media.php';

						$uploaded_files = $submission->uploaded_files();

						$file_array = [
							'name'     => $_FILES['cv']['name'],
							'type'     => $_FILES['cv']['type'],
							'tmp_name' => $uploaded_files['cv'][0],
							'error'    => $_FILES['cv']['error'],
							'size'     => $_FILES['cv']['size'],
						];

						$attachment_id = media_handle_sideload( $file_array, $post_id );

						if ( ! is_wp_error( $attachment_id ) ) {
							update_field( 'file', $attachment_id, $post_id );
						} else {
							update_field( 'file_error', $attachment_id->get_error_message(), $post_id );
						}
					}

					if ( ! empty( $connected_chats ) ) {
						$url     = 'https://api.telegram.org/bot7768117564:AAFS45iz5R_-VKnFGj5WzaIAHUtiXfdbiTs/sendMessage';
						$message = 'Надійшла нова Заявка на вакансію "' . $vacancy->post_title . '". <a href="https://dev.dovira.vet/wp-admin/edit.php?post_type=application">Всі Заявки тут</a>';
						$args    = [
							'timeout'     => 45,
							'redirection' => 5,
							'body'        => [
								'text'                 => $message,
								'parse_mode'           => 'html',
								'link_preview_options' => json_encode( [
									'is_disabled' => true,
								], JSON_THROW_ON_ERROR ),
							],
						];

						foreach ( $connected_chats as $chat_id ) {
							$args['body']['chat_id'] = $chat_id;
							wp_remote_get( $url, $args );
						}
					}
				}
				break;
		}
	}
}

add_action( 'wpcf7_before_send_mail', 'dovira_wpcf7_before_send_mail', 10, 3 );
