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
 * Sync BP content after EP has synced posts
 * TODO dashboard and other sync contexts besides cli?
 */
function ep_bp_bulk_index() {
	WP_CLI::line( 'Indexing groups...' );
	ep_bp_bulk_index_groups();
	WP_CLI::line( 'Indexing members...' );
	ep_bp_bulk_index_members();
}
add_action( 'ep_cli_post_bulk_index', 'ep_bp_bulk_index' );

/**
 * Initialize custom feature etc.
 */
function ep_bp_init() {
	ep_bp_register_feature();
}
add_action( 'bp_init', 'ep_bp_init' );

/**
 * styles to clean up search results
 */
function ep_bp_enqueue_style() {
	wp_register_style( 'elasticpress-buddypress', plugins_url( '/elasticpress-buddypress/css/elasticpress-buddypress.css' ) );
	wp_enqueue_style( 'elasticpress-buddypress' );
}
add_action( 'wp_enqueue_scripts', 'ep_bp_enqueue_style' );

/**
 * Filter search request path to search groups & members as well as posts.
 */
function ep_bp_filter_ep_search_request_path( $path ) {
	return str_replace( '/post/', '/post,group,member/', $path );
}
add_filter( 'ep_search_request_path', 'ep_bp_filter_ep_search_request_path' );

/**
 * Filter index name to include all sub-blogs when on a root blog.
 * This is optional and only affects multinetwork installs.
 * TODO this is slow and creates a giant query url. change indexing so we can search in a single index for root blogs instead.
 * TODO results in a 400 error from elasticsearch when too many shards ( > 1000 ) are searched.
 */
function ep_bp_filter_ep_index_name( $index_name, $blog_id ) {
	// depends on the number of shards being sufficiently low. see ep_bp_filter_ep_default_index_number_of_shards
	return '_all'; // much faster shortcut, but results in 400/413 error if > 1000 shards being searched

	// since we call ep_get_index_name() which uses this filter,
	// we need to disable the filter while this function runs.
	remove_filter( 'ep_index_name', 'ep_bp_filter_ep_index_name', 10, 2 );

	$index_names = [ $index_name ];

	if ( bp_is_root_blog() ) {
		$querystring =  bp_ajax_querystring( 'blogs' ) . '&' . http_build_query( [
			'type' => 'active',
			'search_terms' => false, // do not limit results based on current search query
			'per_page' => 50, // TODO setting this too high results in a query url which is too long (400, 413 errors)
		] );

		if ( bp_has_blogs( $querystring ) ) {
			while ( bp_blogs() ) {
				bp_the_blog();
				switch_to_blog( bp_get_blog_id() );
				$index_names[] = ep_get_index_name();
				restore_current_blog();
			}
		}

	}

	// restore filter now that we're done abusing ep_get_index_name()
	add_filter( 'ep_index_name', 'ep_bp_filter_ep_index_name', 10, 2 );

	return implode( ',', $index_names );
}
add_filter( 'ep_index_name', 'ep_bp_filter_ep_index_name', 10, 2 );

/**
 * this is an attempt at limiting the total number of shards to make searching lots of sites in multinetwork feasible
 */
function ep_bp_filter_ep_default_index_number_of_shards( $number_of_shards ) {
	$number_of_shards = 1;
	return $number_of_shards;
}
add_filter( 'ep_default_index_number_of_shards', 'ep_bp_filter_ep_default_index_number_of_shards' );

/**
 * Filter search request post_filter post_type to search groups & members as well as posts.
 * These aren't real post types in WP, but they are in EP because of the way EP_BP_API indexes.
 * TODO doesn't work. when post_type is in the filter, no results are returned regardless of what types we pass.
 * disable post_type filter instead for now.
 */
function ep_bp_filter_ep_searchable_post_types( $post_types ) {
	return array_unique( array_merge( $post_types, [ 'group', 'member' ] ) );
}
//add_filter( 'ep_searchable_post_types', 'ep_bp_filter_ep_searchable_post_types' );

/**
 * Remove post_type filter for search queries.
 * This is a workaround until ep_bp_filter_ep_searchable_post_types() is fixed.
 */
function ep_bp_filter_ep_formatted_args( $formatted_args ) {
	foreach ( $formatted_args['post_filter']['bool']['must'] as $i => $must ) {
		if ( isset( $must['terms']['post_type.raw'] ) ) {
			unset( $formatted_args['post_filter']['bool']['must'][ $i ] );
			// re-index 'must' array keys using array_values (non-sequential keys pose problems for elasticpress)
			$formatted_args['post_filter']['bool']['must'] = array_values( $formatted_args['post_filter']['bool']['must'] );
		}
	}
	return $formatted_args;
}
add_filter( 'ep_formatted_args', 'ep_bp_filter_ep_formatted_args' );

/**
 * Filter the search results loop to fix non-post (groups, members) permalinks.
 */
function ep_bp_filter_the_permalink( $permalink ) {
	global $wp_query, $post;

	if ( $wp_query->is_search && in_array( $post->post_type,  [ 'group', 'member' ] ) ) {
		$permalink = $post->permalink;
	}

	return $permalink;
}
add_filter( 'the_permalink', 'ep_bp_filter_the_permalink' );
