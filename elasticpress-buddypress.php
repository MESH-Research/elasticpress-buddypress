<?php
/**
 * Plugin Name: ElasticPress BuddyPress
 * Version: alpha
 * Description: ElasticPress custom feature to support BuddyPress content.
 * Text Domain: elasticpress-buddypress
 */

require_once dirname( __FILE__ ) . '/features/buddypress/buddypress.php';
require_once dirname( __FILE__ ) . '/classes/class-ep-bp-api.php';

/**
 * Register ElasticPress custom feature
 */
add_action( 'bp_init', 'ep_bp_register_feature' );

/**
 * Sync BP content after EP has synced posts
 */
add_action( 'ep_cli_post_bulk_index', 'ep_bp_bulk_index_groups' );
//add_action( 'ep_cli_post_bulk_index', 'ep_bp_bulk_index_members' );

/**
 * Filter search request path to search groups & members as well as posts.
 */
function ep_bp_filter_search_request_path( $path ) {
	return str_replace( '/post/', '/post,group,member/', $path );
}
add_filter( 'ep_search_request_path', 'ep_bp_filter_search_request_path' );
