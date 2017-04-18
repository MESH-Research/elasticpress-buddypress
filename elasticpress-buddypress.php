<?php
/**
 * Plugin Name: ElasticPress BuddyPress
 * Version: alpha
 * Description: ElasticPress custom feature to support BuddyPress content.
 * Text Domain: elasticpress-buddypress
 */

function ep_bp_translate_args( $query ) {
	//var_dump( func_get_args() ); die;
	$supported_post_types = ep_bp_post_types();

	$post_type = $query->get( 'post_type' );

	if ( empty( $post_type ) ) {
		$post_type = 'post';
	}

	// TODO get bp_post_types into query
	var_dump( $post_type );
	if ( is_array( $post_type ) ) {
		foreach ( $post_type as $pt ) {
			if ( empty( $supported_post_types[$pt] ) ) {
				return;
			}
		}

		$query->set( 'ep_integrate', true );
	} else {
		if ( ! empty( $supported_post_types[$post_type] ) ) {
			$query->set( 'ep_integrate', true );
		}
	}
	var_dump( $query );die;
}

function ep_bp_post_types( $post_types = [] ) {
	return array_unique( array_merge( $post_types, [
		'attachment',
		'bp_doc',
		'bp_docs_folder', // TODO necessary?
		'event',
		'features', // TODO necessary?
		'forum',
		//'humcore_deposit', // TODO in a separate plugin.
		'reply',
		'topic',
	] ) );
}

function ep_bp_setup() {
	add_filter( 'ep_indexable_post_types', 'ep_bp_post_types' );
	add_action( 'pre_get_posts', 'ep_bp_translate_args', 11, 1 );
}

function ep_bp_requirements_status( $status ) {
	if ( ! class_exists( 'BuddyPress' ) ) {
		$status->code = 2;
		$status->message = __( 'BuddyPress is not active.', 'elasticpress' );
	}
	return $status;
}

function ep_bp_feature_box_summary() {
	echo esc_html_e( 'Index BuddyPress content like groups and members.', 'elasticpress-buddypress' );
}

function ep_bp_register_feature() {
	ep_register_feature( 'buddypress', [
		'title' => 'BuddyPress',
		'setup_cb' => 'ep_bp_setup',
		'requirements_status_cb' => 'ep_bp_requirements_status',
		'feature_box_summary_cb' => 'ep_bp_feature_box_summary',
		'requires_install_reindex' => true,
	] );
}

add_action( 'plugins_loaded', 'ep_bp_register_feature' );
