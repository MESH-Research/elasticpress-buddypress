<?php

/**
 * Plugin Name: ElasticPress REST
 * Version: alpha
 * Description: ElasticPress custom feature to support live filtering via a custom WordPress REST API endpoint.
 */

class EPR_REST_Posts_Controller extends WP_REST_Posts_Controller {

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

		// overwrite global in order to get all the same filters & actions on results as when searching the usual way
		$wp_query = new WP_Query( array_merge(
			[ 'ep_integrate' => true ],
			$data->get_query_params()
		) );

		// TODO without this, parse_query has already run on the default empty $wp_query,
		// and our filters from ep-bp never get added. find a way to avoid calling this,
		// and/or find a way to avoid actually parsing/populating $wp_query more than once.
		$wp_query->parse_query_vars();

		ob_start();

		if ( have_posts() ) {
			while ( have_posts() ) {
				the_post();
				get_template_part( 'content', get_post_format() );
			}
			// TODO don't rely on theme function for pagination
			buddyboss_pagination();
		} else {
			// TODO ripped from search.php - should pull from custom template file instead
			?>
			<article id="post-0" class="post no-results not-found">
				<header class="entry-header">
					<h1 class="entry-title">Nothing Found</h1>
				</header>
				<div class="entry-content">
					<p>Sorry, but nothing matched your search criteria. Please try again with some different keywords.</p>
				</div><!-- .entry-content -->
			</article>
			<?php
		}

		$results_html = ob_get_contents();

		ob_end_clean();

		$response = new WP_REST_Response( [
			'results_html' => $results_html,
		] );

		ob_end_clean();

		return $response;
	}
}
