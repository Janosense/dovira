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

/**
 * Handles specific actions for the "application" custom post type upon saving.
 *
 * This method updates metadata for posts based on certain Advanced Custom Fields (ACF) values.
 * It sets or resets the processing date based on the status of the application or conversation.
 *
 * @param int $post_id The ID of the post being saved.
 * @param WP_Post $post The post object being saved.
 * @param bool $update Whether this is an update to an existing post.
 *
 * @return void
 */
function dovira_save_post_application_action( $post_id, $post, $update ):void {
	if ( $post->post_status === 'publish' && ! wp_is_post_revision( $post_id ) && ! empty( $_POST['acf'] ) ) {
		if ( isset( $_POST['acf']['field_application_status'] ) && $_POST['acf']['field_application_status'] !== 'new' ) {
			update_post_meta( $post_id, 'processing_date', time() + 10800 );
		} else if ( isset( $_POST['acf']['field_conversation_is_processed'] ) && $_POST['acf']['field_conversation_is_processed'] === 'new' ) {
			update_post_meta( $post_id, 'processing_date', 0 );
		}
	}
}

add_action( 'save_post_application', 'dovira_save_post_application_action', 10, 3 );


/**
 * Adds a filter dropdown for vacancies to the applications list in the admin panel.
 *
 * This function generates and displays a select dropdown of available vacancies
 * for filtering the applications list based on the selected vacancy. The dropdown
 * retrieves all published vacancies with the 'is_open' meta key set to true.
 *
 * @param string $post_type The current post type being displayed in the admin list.
 *                          The functionality applies only if the post type is 'application'.
 *
 * @return void
 */
function dovira_add_applications_list_filter_by_vacancy( string $post_type ):void {
	if ( $post_type === 'application' ) {

		$vacancies = get_posts( [
			'post_type'   => 'vacancy',
			'numberposts' => - 1,
			'post_status' => 'publish',
			'orderby'     => 'title',
			'meta_query'  => [
				[
					'key'   => 'is_open',
					'value' => 1,
				]
			]
		] );


		$selected_vacancy = 'all';

		if ( isset( $_GET['vacancy_id'] ) && ! empty( $_GET['vacancy_id'] ) ) {
			$selected_vacancy = $_GET['vacancy_id'];
		}

		$select   = "<select name='vacancy_id'>";
		$selected = selected( $selected_vacancy, 'all', false );
		$select   .= "<option {$selected} value='all'>" . __( 'All vacancies', 'dovira' ) . "</option>";

		if ( ! empty( $vacancies ) ) {
			foreach ( $vacancies as $vacancy ) {
				$selected = selected( $selected_vacancy, $vacancy->ID, false );
				$select   .= "<option {$selected} value='" . $vacancy->ID . "'>" . $vacancy->post_title . "</option>";
			}
		}

		$select .= "</select>";

		echo $select;
	}
}

add_action( 'restrict_manage_posts', 'dovira_add_applications_list_filter_by_vacancy' );


/**
 * Adds a filter dropdown for application statuses on the application list page.
 *
 * This function generates a dropdown menu for filtering applications by their statuses
 * and displays it on the application list page if the current post type is `application`.
 *
 * @param string $post_type The current post type being displayed in the admin list table.
 *
 * @return void
 */
function dovira_add_applications_list_filter_by_status( string $post_type ):void {
	if ( $post_type === 'application' ) {

		$statuses = [
			'new'                 => __( 'New', 'dovira' ),
			'in_processing'       => __( 'In processing', 'dovira' ),
			'interview_scheduled' => __( 'Interview scheduled', 'dovira' ),
			'rejected'            => __( 'Rejected', 'dovira' ),
			'closed'              => __( 'Closed', 'dovira' ),
		];


		$selected_status = 'all';

		if ( isset( $_GET['status'] ) && ! empty( $_GET['status'] ) ) {
			$selected_status = $_GET['status'];
		}

		$select   = "<select name='status'>";
		$selected = selected( $selected_status, 'all', false );
		$select   .= "<option {$selected} value='all'>" . __( 'All statuses', 'dovira' ) . "</option>";

		if ( ! empty( $statuses ) ) {
			foreach ( $statuses as $key => $status ) {
				$selected = selected( $selected_status, $key, false );
				$select   .= "<option {$selected} value='" . $key . "'>" . $status . "</option>";
			}
		}

		$select .= "</select>";

		echo $select;
	}
}

add_action( 'restrict_manage_posts', 'dovira_add_applications_list_filter_by_status' );

/**
 * @param WP_Query $query
 */
function dovira_add_applications_list_filter_handler( WP_Query $query ) : void {

	$current_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! is_admin() || empty( $current_screen ) || $current_screen->post_type !== 'application' || $current_screen->id !== 'edit-application' ) {
		return;
	}

	if ( $query->is_main_query() ) {

		$filter_fields = [];

		if ( isset( $_GET['status'] ) && ! empty( $_GET['status'] ) && $_GET['status'] !== 'all' ) {
			$filter_fields['status'] = [
				'value'   => $_GET['status'],
				'compare' => '=',
			];
		}

		if ( isset( $_GET['vacancy_id'] ) && ! empty( $_GET['vacancy_id'] ) && $_GET['vacancy_id'] !== 'all' ) {
			$filter_fields['vacancy_id'] = [
				'value'   => (int) $_GET['vacancy_id'],
				'compare' => '=',
			];
		}

		if ( ! empty( $filter_fields ) ) {
			$meta_query    = [];
			$meta_keys_map = [
				'status'      => 'status',
				'vacancy_id'      => 'vacancy',
			];

			if ( count( $filter_fields ) > 1 ) {
				$meta_query['relation'] = 'AND';
			}

			foreach ( $filter_fields as $field => $field_data ) {

				$meta_query[] = [
					'key'     => $meta_keys_map[ $field ],
					'value'   => $field_data['value'],
					'compare' => $field_data['compare'],
				];
			}

			$query->set( 'meta_query', $meta_query );
		}
	}
}

add_action( 'parse_query', 'dovira_add_applications_list_filter_handler' );
