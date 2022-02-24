<?php

/**
 * Plugin Name: ElasticPress REST
 * Version: alpha
 * Description: ElasticPress custom feature to support live filtering via a custom WordPress REST API endpoint.
 */

class EPR_REST_Posts_Controller extends WP_REST_Controller {

	// include debug output in REST response
	const DEBUG = true;

	/**
	 * Constructor.
	 *
	 * @since alpha
	 * @access public
	 */
	public function __construct() {
		$this->namespace = 'epr/v1';
		$this->rest_base = '/query';

		// this is not necessary and can cause bad results from elasticsearch, disable it.
		remove_filter( 'request', 'bbp_request', 10 );
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @since alpha
	 * @access public
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, $this->rest_base, [
			'methods' => 'GET',
			'callback' => [ $this, 'get_items' ],
		] );
	}

	/**
	 * Query ElasticPress using query vars
	 *
	 * @return WP_REST_Response
	 */
	public function get_items( $data ) {
		global $wp_query;

		$response = new WP_REST_Response;

		$debug = [];
		$response_data = [ 'posts' => [] ];

		add_action( 'ep_add_query_log', function( $ep_query ) use ( &$response, &$debug ) {
			$debug['ep_query'] = $ep_query;
			$response->set_status( $ep_query['request']['response']['code'] );
		} );

		$query_params = $data->get_query_params();
		if ( array_key_exists( 'numberposts', $query_params ) ) {
			$numberposts = $query_params['numberposts'];
		} else {
			$numberposts = 10;
		}

		if ( array_key_exists( 'paged', $query_params ) ) {
			$paged = $query_params['paged'];
		} else {
			$paged = 1;
		}

		$today = getdate();
		$starting_params = array_merge (
			$data->get_query_params(),
			[ 
				'ep_integrate'   => true,
				'posts_per_page' => $numberposts,
				'paged'          => $paged,
				'date_query'     => [
					[
						'before' => [
							'year'  => $today['year'],
							'month' => $today['mon'],
							'day'   => $today['mday'],
						],
						'inclusive' => true,
					]
				]
			]
		);

		// Going to try to still get $numberposts results if some results get filtered out.
		$result_count = 0;
		$page_count = 0;
		$new_result_count = -1;
		$tried_future_dates = false;
		do {
			$current_params = $starting_params;
			// If we haven't found any posts with dates in the past, check for ones in the future.
			// This means that embargoed deposits will show in search results, but at the end.
			if ( $new_result_count === 0 ) {
				$tried_future_dates = true;
				$current_params['date_query'] = [
						'after' => [
							'year'  => $today['year'],
							'month' => $today['mon'],
							'day'   => $today['mday'],
						],
						'inclusive' => false,
					];
			}
			$new_result_count = 0;
			$current_params['paged'] = $paged + $page_count;
			$wp_query->query( $current_params );
			while( have_posts() ) {
				the_post();
				if ( $wp_query->post->post_parent ) {
					$parent_post = get_post( $wp_query->post->post_parent );
					// Prevent humcore_deposit posts with parents (ie. attachments) from showing in results
					if ( $wp_query->post->post_type === 'humcore_deposit') {
						continue;
					}
					// Prevent posts in private groups from showing in search results
					if ( $parent_post->post_status != 'publish' ) {
						continue;
					}
				}
				ob_start();
				get_template_part( 'content', get_post_format() );
				$response_data['posts'][] = ob_get_contents();
				ob_end_clean();
				$new_result_count++;
			}
			$result_count += $new_result_count;
			$page_count++;
		} while ( $result_count < $numberposts && ( $new_result_count > 0 || ! $tried_future_dates ) );

		$response_data['pages'] = $page_count;

		$debug['wp_query'] = $wp_query;

		$response->set_data( $response_data );

		return $response;
	}
}
