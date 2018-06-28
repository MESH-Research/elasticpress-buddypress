<?php
/**
 * Plugin Name:     ElasticPress BuddyPress
 * Plugin URI:      https://github.com/mlaa/elasticpress-buddypress.git
 * Description:     ElasticPress custom feature to index BuddyPress group & members.
 * Author:          MLA
 * Author URI:      https://github.com/mlaa
 * Text Domain:     elasticpress-buddypress
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Elasticpress_Buddypress
 */

/**
 * Load required files.
 */
require_once dirname( __FILE__ ) . '/includes/class-ep-bp-api.php';
require_once dirname( __FILE__ ) . '/includes/class-epr-rest-posts-controller.php';
require_once dirname( __FILE__ ) . '/includes/features/buddypress/buddypress.php';
require_once dirname( __FILE__ ) . '/includes/features/buddypress/filters.php';
require_once dirname( __FILE__ ) . '/includes/features/buddypress/facets.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once dirname( __FILE__ ) . '/includes/class-elasticpress-buddypress-cli-command.php';
}

add_action( 'plugins_loaded', 'ep_bp_register_feature' );

add_action(
	'rest_api_init', function () {
		$controller = new EPR_REST_Posts_Controller;
		$controller->register_routes();
	}
);
