<?php

namespace dovira;

use JsonException;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class QuestionaryController extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'dovira/v1';
		$this->rest_base = 'questionary';
	}

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route( $this->namespace, "/$this->rest_base/save", [
			'callback'            => [ $this, 'save_questionary' ],
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
		] );
	}

	/**
	 * Save questionary data as custom post type
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_questionary( WP_REST_Request $request ) {
		$params = $request->get_params();

		// Verify nonce
		if ( ! isset( $params['questionary_nonce'] ) || ! wp_verify_nonce( $params['questionary_nonce'], 'questionary_save_action' ) ) {
			return new WP_Error(
				'invalid_nonce',
				__( 'Security verification failed', 'dovira' ),
				[ 'status' => 403 ]
			);
		}

		// Validate required fields
		$required_fields = [ 'name', 'phone', 'animal', 'pet-sex', 'pet-old', 'pet-weight', 'vaccination-date', 'street', 'castration', 'donor-before', 'blood-take', 'chronic-diseases', 'pills' ];
		foreach ( $required_fields as $field ) {
			if ( empty( $params[ $field ] ) && $params[ $field ] !== '0' && $params[ $field ] !== 'no' ) {
				return new WP_Error(
					'missing_required_field',
					sprintf( __( 'Required field "%s" is missing', 'dovira' ), $field ),
					[ 'status' => 400 ]
				);
			}
		}

		// Create post title from owner name and pet name
		$post_title = $params['name'];
		if ( ! empty( $params['pet-name'] ) ) {
			$post_title .= ' - ' . $params['pet-name'];
		}

		// Create the questionary post
		$post_id = wp_insert_post( [
			'post_type'   => 'questionary',
			'post_title'  => sanitize_text_field( $post_title ),
			'post_status' => 'publish',
		] );

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error(
				'post_creation_failed',
				__( 'Failed to create questionary', 'dovira' ),
				[ 'status' => 500 ]
			);
		}

		// Save ACF fields
		// Owner information
		update_field( 'name', sanitize_text_field( $params['name'] ), $post_id );
		update_field( 'phone', sanitize_text_field( $params['phone'] ), $post_id );
		if ( ! empty( $params['email'] ) ) {
			update_field( 'email', sanitize_email( $params['email'] ), $post_id );
		}

		// Pet information
		update_field( 'animal', sanitize_text_field( $params['animal'] ), $post_id );
		update_field( 'pet_sex', sanitize_text_field( $params['pet-sex'] ), $post_id );
		if ( ! empty( $params['pet-type'] ) ) {
			update_field( 'pet_type', sanitize_text_field( $params['pet-type'] ), $post_id );
		}
		update_field( 'pet_old', sanitize_text_field( $params['pet-old'] ), $post_id );
		update_field( 'pet_weight', sanitize_text_field( $params['pet-weight'] ), $post_id );
		update_field( 'vaccination_date', sanitize_text_field( $params['vaccination-date'] ), $post_id );

		// Yes/No fields (convert "yes"/"no" to boolean)
		update_field( 'street', $params['street'] === 'yes' ? 1 : 0, $post_id );
		if ( isset( $params['cat-contact'] ) ) {
			update_field( 'cat_contact', $params['cat-contact'] === 'yes' ? 1 : 0, $post_id );
		}
		update_field( 'castration', $params['castration'] === 'yes' ? 1 : 0, $post_id );
		update_field( 'donor_before', $params['donor-before'] === 'yes' ? 1 : 0, $post_id );
		update_field( 'blood_take', $params['blood-take'] === 'yes' ? 1 : 0, $post_id );
		update_field( 'chronic_diseases', $params['chronic-diseases'] === 'yes' ? 1 : 0, $post_id );
		update_field( 'pills', $params['pills'] === 'yes' ? 1 : 0, $post_id );

		// Pet name
		if ( ! empty( $params['pet-name'] ) ) {
			update_field( 'pet_name', sanitize_text_field( $params['pet-name'] ), $post_id );
		}

		return new WP_REST_Response( [
			'success' => true,
			'message' => __( 'Questionary saved successfully', 'dovira' ),
			'post_id' => $post_id,
		], 200 );
	}
}
