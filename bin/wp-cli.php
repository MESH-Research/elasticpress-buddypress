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
			ep_bp_bulk_index_groups();
		}

		if ( ! isset( $args[0] ) || 'members' === $args[0] ) {
			WP_CLI::line( 'Indexing members...' );
			ep_bp_bulk_index_members();
		}

	}

}
WP_CLI::add_command( 'elasticpress-buddypress', 'ElasticPress_BuddyPress_CLI_Command' );
