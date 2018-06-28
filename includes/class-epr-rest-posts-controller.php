<?php
/**
 * REST endpoint for search results.
 *
 * @package ElasticPress_BuddyPress
 */

/**
 * REST Controller.
 */
class EPR_REST_Posts_Controller extends WP_REST_Controller {

	// Include debug output in REST response.
	const DEBUG = false;

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
	 * @see register_rest_route()
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace, $this->rest_base, [
				'methods'  => 'GET',
				'callback' => [ $this, 'get_items' ],
			]
		);
	}

	/**
	 * Query ElasticPress using query vars.
	 *
	 * @param WP_REST_Request $data Request data.
	 * @return WP_REST_Response
	 */
	public function get_items( $data ) {
		global $wp_query;

		$response = new WP_REST_Response;

		$debug         = [];
		$response_data = [ 'posts' => [] ];

		add_action(
			'ep_add_query_log', function( $ep_query ) use ( &$response, &$debug ) {
				$debug['ep_query'] = $ep_query;

				$response->set_status( $ep_query['request']['response']['code'] );
			}
		);

		$wp_query->query(
			array_merge(
				[ 'ep_integrate' => true ],
				$data->get_query_params()
			)
		);

		$debug['wp_query'] = $wp_query;

		if ( have_posts() ) {
			while ( have_posts() ) {
				ob_start();
				the_post();
				get_template_part( 'content', get_post_format() );
				$response_data['posts'][] = ob_get_contents();
				ob_end_clean();
			}
		}

		if ( self::DEBUG ) {
			$response_data['debug'] = $debug;
		}

		$response->set_data( $response_data );

		return $response;
	}
}
