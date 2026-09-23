<?php

namespace dovira\SearchStats;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * POST dovira/v1/search-stats/record — public, JSON body only, the only writer
 * of the search table. Everything is validated before the one insert (root
 * invariant 8), so a refused request leaves no row
 * (DECISIONS "Every search is recorded from the browser through one public REST route").
 */
final class RecordController extends WP_REST_Controller {

	public const LEVEL_SITE = 'site';
	public const LEVEL_SERVICES = 'services';
	public const LEVEL_SERVICE = 'service';
	public const LEVELS = [ self::LEVEL_SITE, self::LEVEL_SERVICES, self::LEVEL_SERVICE ];

	/** The header search may be a single character; the two filters send from three. */
	public const MIN_LENGTH_SITE = 1;
	public const MIN_LENGTH_FILTER = 3;
	/** The width of query_text. */
	public const MAX_LENGTH = 100;

	public function __construct() {
		$this->namespace = 'dovira/v1';
		$this->rest_base = 'search-stats';
	}

	/**
	 * No 'args': WordPress would validate them against the query string and a
	 * form body as well, and this route reads the JSON body only.
	 */
	public function register_routes(): void {
		register_rest_route( $this->namespace, "/$this->rest_base/record", [
			'methods'             => 'POST',
			'callback'            => [ $this, 'record' ],
			'permission_callback' => '__return_true',
		] );
	}

	/**
	 * A malformed JSON body never gets here: WordPress refuses it first with
	 * rest_invalid_json.
	 *
	 * @return WP_REST_Response|WP_Error 204 with no body; 400 before any write; 500 when the row was not written.
	 */
	public function record( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params();

		if ( ! is_array( $params ) ) {
			return self::refusal( 'search_stats_not_json', __( 'The body must be a JSON object.', 'dovira' ) );
		}

		$level = $params['level'] ?? null;

		if ( ! in_array( $level, self::LEVELS, true ) ) {
			return self::refusal( 'search_stats_invalid_level', __( 'The level must be site, services or service.', 'dovira' ) );
		}

		$query      = is_string( $params['query'] ?? null ) ? Normalizer::normalize( $params['query'] ) : '';
		$min_length = self::LEVEL_SITE === $level ? self::MIN_LENGTH_SITE : self::MIN_LENGTH_FILTER;
		$length     = mb_strlen( $query );

		if ( $length < $min_length || $length > self::MAX_LENGTH ) {
			return self::refusal(
				'search_stats_invalid_query',
				/* translators: 1: minimum length, 2: maximum length */
				sprintf( __( 'The query must be a text of %1$d to %2$d characters.', 'dovira' ), $min_length, self::MAX_LENGTH )
			);
		}

		$context_id = 0;
		$results    = null;

		if ( self::LEVEL_SITE === $level ) {
			$results = $params['results'] ?? null;

			if ( ! is_int( $results ) || $results < 0 ) {
				return self::refusal( 'search_stats_invalid_results', __( 'A site search needs results, a whole number of zero or more.', 'dovira' ) );
			}
		} else {
			$context_id = $params['context_id'] ?? null;

			if ( ! self::is_context( $level, $context_id ) ) {
				return self::refusal( 'search_stats_invalid_context', __( 'The context must be a published post, and a service for the service level.', 'dovira' ) );
			}

			if ( array_key_exists( 'results', $params ) ) {
				return self::refusal( 'search_stats_invalid_results', __( 'Only a site search carries results.', 'dovira' ) );
			}
		}

		if ( ! Repository::insert( $level, $query, $context_id, $results ) ) {
			return new WP_Error( 'search_stats_not_recorded', __( 'The search could not be recorded.', 'dovira' ), [ 'status' => 500 ] );
		}

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * @param mixed $context_id As the JSON body carries it; only an integer passes.
	 */
	private static function is_context( string $level, $context_id ): bool {
		// Checked before get_post_status(): get_post( 0 ) falls back to the global post.
		if ( ! is_int( $context_id ) || $context_id <= 0 ) {
			return false;
		}

		if ( 'publish' !== get_post_status( $context_id ) ) {
			return false;
		}

		return self::LEVEL_SERVICE !== $level || 'service' === get_post_type( $context_id );
	}

	private static function refusal( string $code, string $message ): WP_Error {
		return new WP_Error( $code, $message, [ 'status' => 400 ] );
	}
}
