<?php
/**
 * WP CLI commands.
 *
 * @package ElasticPress_BuddyPress
 */

/**
 * CLI Commands for ElasticPress BuddyPress
 */
class ElasticPress_BuddyPress_CLI_Command extends WP_CLI_Command {

	/**
	 * Index groups and/or members.
	 *
	 * @param array $args Expects one arg, either "groups" or "members".
	 * @param array $assoc_args Not used.
	 */
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

	/**
	 * Index posts from all networks in the current index.
	 *
	 * Since we de-duplicate results already, this allows certain post types to
	 * appear in results regardless of which network facets are selected.
	 *
	 * This is not required to use any other feature of this plugin - it's just for
	 * convenience to make real post types behave like members do, in case that's
	 * something you want.
	 *
	 * Parameters are passed directly to ElasticPress_CLI_Command->index()
	 *
	 * e.g. wp elasticpress-buddypress index_from_all_networks --post-type=topic
	 *
	 * @param array $args Passed to $ep_cli.
	 * @param array $assoc_args Passed to $ep_cli.
	 */
	public function index_from_all_networks( $args, $assoc_args ) {
		$ep_cli = new ElasticPress_CLI_Command;

		// $network_index_name must be defined outside the filter closure to avoid an infinite loop.
		$network_index_name = ep_get_index_name();

		// Ensure index name is constant while we loop through all networks for posts.
		$filter_network_index_name = function() use ( $network_index_name ) {
			return $network_index_name;
		};

		// Index posts on all networks using the filtered index name.
		// This way all posts across all networks get indexed once on every network.
		foreach ( get_networks() as $query_network ) {
			switch_to_blog( get_main_site_for_network( $query_network ) );
			add_filter( 'ep_index_name', $filter_network_index_name );
			$ep_cli->index( $args, $assoc_args );
			remove_filter( 'ep_index_name', $filter_network_index_name );
			restore_current_blog();
		}
	}

}

WP_CLI::add_command( 'elasticpress-buddypress', 'ElasticPress_BuddyPress_CLI_Command' );
