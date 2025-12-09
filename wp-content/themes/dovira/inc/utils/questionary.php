<?php
/**
 * Adds custom columns to the Questionary post type admin list.
 *
 * @param array $columns The default columns.
 *
 * @return array Modified columns array.
 */
function dovira_add_custom_questionary_columns( array $columns ): array {
	unset( $columns['date'] );

	$columns['animal']      = __( 'Animal', 'dovira' );
	$columns['pet_sex']     = __( 'Pet sex', 'dovira' );
	$columns['blood_group'] = __( 'Blood group', 'dovira' );
	$columns['pet_old']     = __( 'Age', 'dovira' );
	$columns['city']        = __( 'City', 'dovira' );
	$columns['custom_date'] = __( 'Date', 'dovira' );

	return $columns;
}

add_filter( 'manage_questionary_posts_columns', 'dovira_add_custom_questionary_columns' );

/**
 * Fills the custom columns with data for the Questionary post type.
 *
 * @param string $column_name The name of the column.
 * @param int $post_id The post ID.
 *
 * @return void
 */
function dovira_fill_custom_questionary_columns( string $column_name, int $post_id ): void {
	switch ( $column_name ) {
		case 'animal':
			$animal = get_field( 'animal', $post_id );
			if ( $animal === 'dog' ) {
				echo __( 'Dog', 'dovira' );
			} elseif ( $animal === 'cat' ) {
				echo __( 'Cat', 'dovira' );
			} else {
				echo '-';
			}
			break;
		case 'pet_sex':
			$pet_sex = get_field( 'pet_sex', $post_id );
			if ( $pet_sex === 'female' ) {
				echo __( 'Female', 'dovira' );
			} elseif ( $pet_sex === 'male' ) {
				echo __( 'Male', 'dovira' );
			} else {
				echo '-';
			}
			break;
		case 'blood_group':
			$blood_group = get_field( 'blood_group', $post_id );
			if ( ! empty( $blood_group ) ) {
				// Extract the label from the blood group choices
				$blood_group_labels = array(
					'all-dont-know' => __( 'Don\'t know', 'dovira' ),
					'cat-a'         => __( 'A', 'dovira' ),
					'cat-b'         => __( 'B', 'dovira' ),
					'cat-AB'        => __( 'AB', 'dovira' ),
					'dog-dea-1.1'   => __( 'DEA 1.1', 'dovira' ),
					'dog-dea-1.2'   => __( 'DEA 1.2', 'dovira' ),
					'dog-dea-1.3'   => __( 'DEA 1.3', 'dovira' ),
					'dog-dea-2'     => __( 'DEA 2', 'dovira' ),
					'dog-dea-3'     => __( 'DEA 3', 'dovira' ),
					'dog-dea-4'     => __( 'DEA 4', 'dovira' ),
					'dog-dea-5'     => __( 'DEA 5', 'dovira' ),
					'dog-dea-7'     => __( 'DEA 7', 'dovira' ),
				);
				echo isset( $blood_group_labels[ $blood_group ] ) ? esc_html( $blood_group_labels[ $blood_group ] ) : esc_html( $blood_group );
			} else {
				echo '-';
			}
			break;
		case 'pet_old':
			$pet_old = get_field( 'pet_old', $post_id );
			echo ! empty( $pet_old ) ? esc_html( $pet_old ) : '-';
			break;
		case 'city':
			$city = get_field( 'city', $post_id );
			echo ! empty( $city ) ? esc_html( $city ) : '-';
			break;
		case 'custom_date':
			echo get_the_date( 'd.m.Y H:i', $post_id );
			break;
	}
}

add_action( 'manage_questionary_posts_custom_column', 'dovira_fill_custom_questionary_columns', 10, 2 );
