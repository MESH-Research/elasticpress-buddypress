<?php

/**
 * Translate args to ElasticPress compat format.
 *
 * @param WP_Query $query
 */
function ep_bp_translate_args( $query ) {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}

	if ( apply_filters( 'ep_skip_query_integration', false, $query ) ) {
		return;
	}

	$query->set( 'post_type', array_unique( array_merge(
		(array) $query->get( 'post_type' ),
		ep_bp_post_types()
	) ) );
}

/**
 * Index BP-related post types
 *
 * @param  array $post_types Existing post types.
 * @return array
 */
function ep_bp_post_types( $post_types = [] ) {
	return array_unique( array_merge( $post_types, [
		'bp_doc',
		'bp_docs_folder',
		'forum',
		'reply',
		'topic',
	] ) );
}

/**
 * Index BP taxonomies
 *
 * @param   array $taxonomies Index taxonomies array.
 * @param   array $post Post properties array.
 * @return  array
 */
function ep_bp_whitelist_taxonomies( $taxonomies, $post ) {
	return array_merge( $taxonomies, [
		get_taxonomy( bp_get_member_type_tax_name() )
	] );
}

/**
 * Setup all feature filters
 */
function ep_bp_setup() {
	add_filter( 'ep_indexable_post_types', 'ep_bp_post_types' );
	add_filter( 'ep_sync_taxonomies', 'ep_bp_whitelist_taxonomies' );
	add_action( 'pre_get_posts', 'ep_bp_translate_args' );
}

/**
 * Determine BP feature reqs status
 *
 * @param  EP_Feature_Requirements_Status $status
 * @return EP_Feature_Requirements_Status
 */
function ep_bp_requirements_status( $status ) {
	if ( ! class_exists( 'BuddyPress' ) ) {
		$status->code = 2;
		$status->message = __( 'BuddyPress is not active.', 'elasticpress' );
	}
	return $status;
}

/**
 * Output feature box summary
 */
function ep_bp_feature_box_summary() {
	echo esc_html_e( 'Index BuddyPress content like groups and members.', 'elasticpress-buddypress' );
}

/**
 * Register the feature
 */
function ep_bp_register_feature() {
	ep_register_feature( 'buddypress', [
		'title' => 'BuddyPress',
		'setup_cb' => 'ep_bp_setup',
		'requirements_status_cb' => 'ep_bp_requirements_status',
		'feature_box_summary_cb' => 'ep_bp_feature_box_summary',
		'requires_install_reindex' => true,
	] );
}
