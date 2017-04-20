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
function ep_bp_filter_ep_search_request_path( $path ) {
	return str_replace( '/post/', '/post,group,member/', $path );
}
add_filter( 'ep_search_request_path', 'ep_bp_filter_ep_search_request_path' );

/**
 * Filter search request post_filter post_type to search groups & members as well as posts.
 * These aren't real post types in WP, but they are in EP because of the way EP_BP_API indexes.
 */
function ep_bp_filter_ep_searchable_post_types( $post_types ) {
	return array_unique( array_merge( $post_types, [ 'group', 'member' ] ) );
}
// TODO this does not work. when post_type is in the filter, no results are returned regardless of what types we pass.
// disable post_type filter instead for now.
//add_filter( 'ep_searchable_post_types', 'ep_bp_filter_ep_searchable_post_types' );

/**
 * Remove post_type filter.
 * This can be removed when ep_bp_filter_ep_searchable_post_types() is fixed.
 */
function ep_bp_filter_ep_formatted_args( $formatted_args ) {
	foreach ( $formatted_args['post_filter']['bool']['must'] as $i => $must ) {
		if ( isset( $must['terms']['post_type.raw'] ) ) {
			unset( $formatted_args['post_filter']['bool']['must'][ $i ] );
		}
	}
}
add_filter( 'ep_formatted_args', 'ep_bp_filter_ep_formatted_args' );
