<?php

/**
 * Plugin Name: ElasticPress REST
 * Version: alpha
 * Description: ElasticPress custom feature to support live filtering via a custom WordPress REST API endpoint.
 */

class EPR_REST_Posts_Controller extends WP_REST_Posts_Controller {

	// include debug output in REST response
	const DEBUG = true;

	/**
	 * Constructor.
	 *
	 * @since alpha
	 * @access public
	 */
	public function __construct() {
		// TODO $wp_query->is_search is false when REST api happens even with 's' param,
		// so we need some additional action to hook the same filters.
		// until i find something better this is it
		do_action( 'epr_init' );

		$this->namespace = 'epr/v1';
		$this->rest_base = '/query';

		$this->meta = new WP_REST_Post_Meta_Fields( $this->post_type );

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
		$posts = [];

		add_action( 'ep_add_query_log', function( $ep_query ) use ( &$response ) {
			$debug['ep_query'] = $ep_query;

			$response->set_status( $ep_query['request']['response']['code'] );
		} );

		$wp_query->query( array_merge(
			[ 'ep_integrate' => true ],
			$data->get_query_params()
		) );

		$debug['wp_query'] = $wp_query;

		if ( have_posts() ) {
			while ( have_posts() ) {
				ob_start();
				the_post();
				get_template_part( 'content', get_post_format() );
				$posts[] = ob_get_contents();
				ob_end_clean();
			}
		}

		$response->set_data( [
			'posts' => $posts,
			'debug' => ( self::DEBUG ) ? $debug : null,
		] );

		return $response;
	}
}
