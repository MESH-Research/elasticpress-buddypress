<?php
 if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * CLI Commands for ElasticPress BuddyPress
 *
 */
class ElasticPress_BuddyPress_CLI_Command extends WP_CLI_Command {

	public function index( $args, $assoc_args ) {

		if ( ! isset( $args[0] ) || 'groups' === $args[0] ) {
			WP_CLI::line( 'Indexing groups...' );

			$index_args = apply_filters( 'ep_bp_group_index_args', [] );

			$result = ep_bp_bulk_index_groups( $index_args );

			if ( $result ) {
				WP_CLI::success( 'Done!' );
			} else {
				WP_CLI::error( 'Something went wrong.' );
			}
		}

		if ( ! isset( $args[0] ) || 'members' === $args[0] ) {
			WP_CLI::line( 'Indexing members...' );

			$index_args = apply_filters( 'ep_bp_member_index_args', [] );

			$result = ep_bp_bulk_index_members( $index_args );

			if ( $result ) {
				WP_CLI::success( 'Done!' );
			} else {
				WP_CLI::error( 'Something went wrong.' );
			}
		}

	}

}

WP_CLI::add_command( 'elasticpress-buddypress', 'ElasticPress_BuddyPress_CLI_Command' );
