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

			$result = ep_bp_bulk_index_groups();

			if ( $result ) {
				WP_CLI::success( 'Done!' );
			} else {
				WP_CLI::error( 'Something went wrong.' );
			}
		}

		if ( ! isset( $args[0] ) || 'members' === $args[0] ) {
			WP_CLI::line( 'Indexing members...' );

			$result = ep_bp_bulk_index_members();

			if ( $result ) {
				WP_CLI::success( 'Done!' );
			} else {
				WP_CLI::error( 'Something went wrong.' );
			}
		}

	}

}

WP_CLI::add_command( 'elasticpress-buddypress', 'ElasticPress_BuddyPress_CLI_Command' );
