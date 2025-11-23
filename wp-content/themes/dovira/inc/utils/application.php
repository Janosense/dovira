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

/**
 * Adds custom columns to the Application post type admin list.
 *
 * @param array $columns The default columns.
 *
 * @return array Modified columns array.
 */
function dovira_add_custom_application_columns( array $columns ): array {
	unset( $columns['date'] );

	$columns['status']              = __( 'Status', 'dovira' );
	$columns['vacancy']             = __( 'Vacancy', 'dovira' );
	$columns['responsible_persons'] = __( 'Responsible persons', 'dovira' );
	$columns['custom_date']         = __( 'Date', 'dovira' );
	$columns['processing_date']     = __( 'Processing Date', 'dovira' );

	return $columns;
}

add_filter( 'manage_application_posts_columns', 'dovira_add_custom_application_columns' );

/**
 * Fills the custom columns with data for the Application post type.
 *
 * @param string $column_name The name of the column.
 * @param int $post_id The post ID.
 *
 * @return void
 */
function dovira_fill_custom_application_columns( string $column_name, int $post_id ): void {
	switch ( $column_name ) {
		case 'status':
			$status = get_field( 'status', $post_id );
			switch ( $status ) {
				case 'new':
					echo "<span class='entity-status entity-status--" . $status . "'>" . __( 'New Application', 'dovira' ) . "</span>";
					break;
				case 'in_processing':
					echo "<span class='entity-status entity-status--" . $status . "'>" . __( 'In processing', 'dovira' ) . "</span>";
					break;
				case 'interview_scheduled':
					echo "<span class='entity-status entity-status--" . $status . "'>" . __( 'Interview scheduled', 'dovira' ) . "</span>";
					break;
				case 'rejected':
					echo "<span class='entity-status entity-status--" . $status . "'>" . __( 'Rejected', 'dovira' ) . "</span>";
					break;
				case 'closed':
					echo "<span class='entity-status entity-status--" . $status . "'>" . __( 'Closed', 'dovira' ) . "</span>";
					break;
				default:
					echo "<span class='entity-status entity-status--new'>" . __( 'New Application', 'dovira' ) . "</span>";
					break;
			}
			break;
		case 'vacancy':
			$vacancy_id = get_post_meta( $post_id, 'vacancy', true );
			if ( ! empty( $vacancy_id ) ) {
				$vacancy = get_post( $vacancy_id );
				if ( $vacancy ) {
					echo esc_html( $vacancy->post_title );
				} else {
					echo '-';
				}
			} else {
				echo '-';
			}
			break;
		case 'responsible_persons':
			$responsible_persons = get_post_meta( $post_id, 'responsible_persons', true );
			if ( ! empty( $responsible_persons ) ) {
				$responsible_persons_str = '';
				foreach ( $responsible_persons as $responsible_person ) {
					$responsible_persons_str .= $responsible_person['name'] . '<br>';
				}
				echo $responsible_persons_str;
			} else {
				echo '-';
			}
			break;
		case 'custom_date':
			echo get_the_date( 'd.m.Y H:i', $post_id );
			break;
		case 'processing_date':
			$processing_date = get_post_meta( $post_id, 'processing_date', true );
			if ( $processing_date ) {
				echo date( 'd.m.Y H:i', $processing_date );
			} else {
				echo '-';
			}
			break;
	}
}

add_action( 'manage_application_posts_custom_column', 'dovira_fill_custom_application_columns', 10, 2 );

function dovira_save_post_application_action( $post_id, $post, $update ) {
	if ( $post->post_status === 'publish' && ! wp_is_post_revision( $post_id ) && ! empty( $_POST['acf'] ) ) {
		if ( isset( $_POST['acf']['field_application_status'] ) && $_POST['acf']['field_application_status'] !== 'new' ) {
			update_post_meta( $post_id, 'processing_date', time() + 10800 );
		} else if ( isset( $_POST['acf']['field_conversation_is_processed'] ) && $_POST['acf']['field_conversation_is_processed'] === 'new' ) {
			update_post_meta( $post_id, 'processing_date', 0 );
		}
	}
}

add_action( 'save_post_application', 'dovira_save_post_application_action', 10, 3 );
