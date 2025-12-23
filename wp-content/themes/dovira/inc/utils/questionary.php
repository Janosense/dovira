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
					'cat-ab'        => __( 'AB', 'dovira' ),
					'dog-dea-1-plus'   => __( 'DEA 1+', 'dovira' ),
					'dog-dea-1-minus'   => __( 'DEA 1-', 'dovira' ),
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

/**
 * Adds a filter dropdown for animal type to the questionaries list in the admin panel.
 *
 * @param string $post_type The current post type being displayed in the admin list.
 *
 * @return void
 */
function dovira_add_questionaries_list_filter_by_animal( string $post_type ): void {
	if ( $post_type === 'questionary' ) {
		$animals = array(
			'dog' => __( 'Dog', 'dovira' ),
			'cat' => __( 'Cat', 'dovira' ),
		);

		$selected_animal = 'all';

		if ( isset( $_GET['animal'] ) && ! empty( $_GET['animal'] ) ) {
			$selected_animal = $_GET['animal'];
		}

		$select   = "<select name='animal'>";
		$selected = selected( $selected_animal, 'all', false );
		$select   .= "<option {$selected} value='all'>" . __( 'All animals', 'dovira' ) . "</option>";

		if ( ! empty( $animals ) ) {
			foreach ( $animals as $key => $animal ) {
				$selected = selected( $selected_animal, $key, false );
				$select   .= "<option {$selected} value='" . $key . "'>" . $animal . "</option>";
			}
		}

		$select .= "</select>";

		echo $select;
	}
}

add_action( 'restrict_manage_posts', 'dovira_add_questionaries_list_filter_by_animal' );

/**
 * Adds a filter dropdown for pet sex to the questionaries list in the admin panel.
 *
 * @param string $post_type The current post type being displayed in the admin list.
 *
 * @return void
 */
function dovira_add_questionaries_list_filter_by_pet_sex( string $post_type ): void {
	if ( $post_type === 'questionary' ) {
		$pet_sexes = array(
			'female' => __( 'Female', 'dovira' ),
			'male'   => __( 'Male', 'dovira' ),
		);

		$selected_pet_sex = 'all';

		if ( isset( $_GET['pet_sex'] ) && ! empty( $_GET['pet_sex'] ) ) {
			$selected_pet_sex = $_GET['pet_sex'];
		}

		$select   = "<select name='pet_sex'>";
		$selected = selected( $selected_pet_sex, 'all', false );
		$select   .= "<option {$selected} value='all'>" . __( 'All sexes', 'dovira' ) . "</option>";

		if ( ! empty( $pet_sexes ) ) {
			foreach ( $pet_sexes as $key => $pet_sex ) {
				$selected = selected( $selected_pet_sex, $key, false );
				$select   .= "<option {$selected} value='" . $key . "'>" . $pet_sex . "</option>";
			}
		}

		$select .= "</select>";

		echo $select;
	}
}

add_action( 'restrict_manage_posts', 'dovira_add_questionaries_list_filter_by_pet_sex' );

/**
 * Adds a filter dropdown for blood group to the questionaries list in the admin panel.
 *
 * @param string $post_type The current post type being displayed in the admin list.
 *
 * @return void
 */
function dovira_add_questionaries_list_filter_by_blood_group( string $post_type ): void {
	if ( $post_type === 'questionary' ) {
		$blood_groups = array(
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

		$selected_blood_group = 'all';

		if ( isset( $_GET['blood_group'] ) && ! empty( $_GET['blood_group'] ) ) {
			$selected_blood_group = $_GET['blood_group'];
		}

		$select   = "<select name='blood_group'>";
		$selected = selected( $selected_blood_group, 'all', false );
		$select   .= "<option {$selected} value='all'>" . __( 'All blood groups', 'dovira' ) . "</option>";

		if ( ! empty( $blood_groups ) ) {
			foreach ( $blood_groups as $key => $blood_group ) {
				$selected = selected( $selected_blood_group, $key, false );
				$select   .= "<option {$selected} value='" . $key . "'>" . $blood_group . "</option>";
			}
		}

		$select .= "</select>";

		echo $select;
	}
}

add_action( 'restrict_manage_posts', 'dovira_add_questionaries_list_filter_by_blood_group' );

/**
 * Handles filtering questionaries list based on selected filters.
 *
 * @param WP_Query $query The WordPress query object.
 *
 * @return void
 */
function dovira_add_questionaries_list_filter_handler( WP_Query $query ): void {
	$current_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! is_admin() || empty( $current_screen ) || $current_screen->post_type !== 'questionary' || $current_screen->id !== 'edit-questionary' ) {
		return;
	}

	if ( $query->is_main_query() ) {
		$filter_fields = [];

		if ( isset( $_GET['animal'] ) && ! empty( $_GET['animal'] ) && $_GET['animal'] !== 'all' ) {
			$filter_fields['animal'] = [
				'value'   => $_GET['animal'],
				'compare' => '=',
			];
		}

		if ( isset( $_GET['pet_sex'] ) && ! empty( $_GET['pet_sex'] ) && $_GET['pet_sex'] !== 'all' ) {
			$filter_fields['pet_sex'] = [
				'value'   => $_GET['pet_sex'],
				'compare' => '=',
			];
		}

		if ( isset( $_GET['blood_group'] ) && ! empty( $_GET['blood_group'] ) && $_GET['blood_group'] !== 'all' ) {
			$filter_fields['blood_group'] = [
				'value'   => $_GET['blood_group'],
				'compare' => '=',
			];
		}

		if ( ! empty( $filter_fields ) ) {
			$meta_query    = [];
			$meta_keys_map = [
				'animal'      => 'animal',
				'pet_sex'     => 'pet_sex',
				'blood_group' => 'blood_group',
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

add_action( 'parse_query', 'dovira_add_questionaries_list_filter_handler' );
