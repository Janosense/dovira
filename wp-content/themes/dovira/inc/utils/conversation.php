<?php
/**
 * Function for `wpcf7_submit` action-hook.
 *
 * @param  $that
 * @param  $result
 *
 * @return void
 */
function dovira_wpcf7_submit_action( $that, $result ) {
	if ( $result['status'] === 'mail_sent' || getenv( 'IS_DDEV_PROJECT' ) == 'true' ) {
		switch ( $result['contact_form_id'] ) {
			case 6: // Contact Form
				$post_data = array(
					'post_title'  => sanitize_text_field( $_POST['your-name'] ) . ' | ' . sanitize_text_field( $_POST['your-phone'] ),
					'post_status' => 'publish',
					'post_author' => 1,
					'post_type'   => 'conversation',
				);

				$post_id = wp_insert_post( $post_data );

				if ( ! is_wp_error( $post_id ) ) {
					update_field( 'name', sanitize_text_field( $_POST['your-name'] ), $post_id );
					update_field( 'pet_name', sanitize_text_field( $_POST['pet-name'] ), $post_id );
					update_field( 'phone', sanitize_text_field( $_POST['your-phone'] ), $post_id );
					update_field( 'email', sanitize_text_field( $_POST['your-email'] ), $post_id );
					update_field( 'message', sanitize_text_field( $_POST['your-message'] ), $post_id );

					$connected_chats = get_option( 'telegram_bot_chats', [] );
					if ( ! empty( $connected_chats ) ) {
						$url     = 'https://api.telegram.org/bot7768117564:AAFS45iz5R_-VKnFGj5WzaIAHUtiXfdbiTs/sendMessage';
						$message = 'Надійшло нове звернення з контактної форми. <a href="https://dovira.vet/wp-admin/edit.php?post_type=conversation">Всі звернення тут</a>';
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

add_action( 'wpcf7_submit', 'dovira_wpcf7_submit_action', 10, 2 );

/**
 * @param array $columns
 *
 * @return array
 */
function dovira_add_custom_conversation_columns( array $columns ): array {
	unset( $columns['date'] );

	$columns['is_processed']        = __( 'Is processed?', 'dovira' );
	$columns['responsible_persons'] = __( 'Responsible persons', 'dovira' );
	$columns['custom_date']         = __( 'Date', 'dovira' );
	$columns['processing_date']     = __( 'Processing date', 'dovira' );

	return $columns;
}

add_filter( 'manage_conversation_posts_columns', 'dovira_add_custom_conversation_columns' );

/**
 * @param string $column_name
 * @param int $post_id
 *
 * @return void
 */
function dovira_fill_custom_conversation_columns( string $column_name, int $post_id ): void {
	switch ( $column_name ) {
		case 'is_processed':
			$is_processed = dovira_get_acf_field( 'is_processed', $post_id );
			echo ! $is_processed ? "<span class='entity-status entity-status--new'>Нове</span>" : "<span class='entity-status entity-status--processed'>Опрацьовано</span>";
			break;
		case 'custom_date':
			echo get_the_date( 'd.m.Y H:i', $post_id );
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

add_action( 'manage_conversation_posts_custom_column', 'dovira_fill_custom_conversation_columns', 10, 2 );

function dovira_save_post_conversation_action( $post_id, $post, $update ) {
	if ( $post->post_status === 'publish' && ! wp_is_post_revision( $post_id ) && ! empty( $_POST['acf'] ) ) {
		if ( isset( $_POST['acf']['field_conversation_is_processed'] ) && $_POST['acf']['field_conversation_is_processed'] === '1' ) {
			update_post_meta( $post_id, 'processing_date', time() + 10800 );
		} else if ( isset( $_POST['acf']['field_conversation_is_processed'] ) && $_POST['acf']['field_conversation_is_processed'] === '0' ) {
			update_post_meta( $post_id, 'processing_date', 0 );
		}
	}
}

add_action( 'save_post_conversation', 'dovira_save_post_conversation_action', 10, 3 );
