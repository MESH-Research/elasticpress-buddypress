<?php
/**
 * Feature for ElasticPress to enable BuddyPress content.
 */

/**
 * styles to clean up search results
 */
function ep_bp_enqueue_style() {
	wp_register_style( 'elasticpress-buddypress', plugins_url( '/elasticpress-buddypress/css/elasticpress-buddypress.css' ) );
	wp_enqueue_style( 'elasticpress-buddypress' );
}

/**
 * Filter search request path to search groups & members as well as posts.
 */
function ep_bp_filter_ep_search_request_path( $path ) {
	return str_replace( '/post/', '/post,' . EP_BP_API::GROUP_TYPE_NAME . ',' . EP_BP_API::MEMBER_TYPE_NAME . '/', $path );
}

/**
 * Filter index name to include all sub-blogs when on a root blog.
 * This is optional and only affects multinetwork installs.
 */
function ep_bp_filter_ep_index_name( $index_name, $blog_id ) {
	// since we call ep_get_index_name() which uses this filter, we need to disable the filter while this function runs.
	remove_filter( 'ep_index_name', 'ep_bp_filter_ep_index_name', 10, 2 );

	$index_names = [ $index_name ];

	// checking is_search() prevents changing index name while indexing
	// only one of the below methods should be active. the others are left here for reference.
	if ( is_search() ) {
		/**
		 * METHOD 1: all indices
		 * only works if the number of shards being sufficiently low
		 * results in 400/413 error if > 1000 shards being searched
		 * see ep_bp_filter_ep_default_index_number_of_shards()
		 */
		//$index_names = [ '_all' ];

		/**
		 * METHOD 2: all main sites for all networks
		 * most practical if there are lots of sites (enough to worry about exceeded the shard query limit of 1000)
		 */
		foreach ( get_networks() as $network ) {
			$network_main_site_id = get_main_site_for_network( $network );
			$index_names[] = ep_get_index_name( $network_main_site_id );
		}

		/**
		 * METHOD 3: some blogs, e.g. 50 most recently active
		 * compromise if one of the prior two methods doesn't work for some reason.
		 */
		//if ( bp_is_root_blog() ) {
		//	$querystring =  bp_ajax_querystring( 'blogs' ) . '&' . http_build_query( [
		//		'type' => 'active',
		//		'search_terms' => false, // do not limit results based on current search query
		//		'per_page' => 50, // TODO setting this too high results in a query url which is too long (400, 413 errors)
		//	] );

		//	if ( bp_has_blogs( $querystring ) ) {
		//		while ( bp_blogs() ) {
		//			bp_the_blog();
		//			$index_names[] = ep_get_index_name( bp_get_blog_id() );
		//		}
		//	}
		//}

		// handle facets
		if ( isset( $_REQUEST['index'] ) ) {
			$index_names = $_REQUEST['index'];
		}
	}

	// restore filter now that we're done abusing ep_get_index_name()
	add_filter( 'ep_index_name', 'ep_bp_filter_ep_index_name', 10, 2 );

	return implode( ',', array_unique( $index_names ) );
}

/**
 * this is an attempt at limiting the total number of shards to make searching lots of sites in multinetwork feasible
 * not necessary unless querying lots of sites at once.
 * doesn't seem to hurt to leave it enabled in any case though.
 */
function ep_bp_filter_ep_default_index_number_of_shards( $number_of_shards ) {
	$number_of_shards = 1;
	return $number_of_shards;
}

/**
 * Filter search request post_filter post_type to search groups & members as well as posts.
 * These aren't real post types in WP, but they are in EP because of the way EP_BP_API indexes.
 * TODO doesn't work. when post_type is in the filter, no results are returned regardless of what types we pass.
 * disable post_type filter instead for now.
 */
function ep_bp_filter_ep_searchable_post_types( $post_types ) {
	return array_unique( array_merge( $post_types, [ EP_BP_API::GROUP_TYPE_NAME, EP_BP_API::MEMBER_TYPE_NAME ] ) );
}

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

/**
 * Filter the search results loop to fix non-post (groups, members) permalinks.
 */
function ep_bp_filter_the_permalink( $permalink ) {
	global $wp_query, $post;

	if ( $wp_query->is_search && in_array( $post->post_type,  [ EP_BP_API::GROUP_TYPE_NAME, EP_BP_API::MEMBER_TYPE_NAME ] ) ) {
		$permalink = $post->permalink;
	}

	return $permalink;
}

/**
 * Add search facets to sidebar.
 * TODO widgetize?
 * TODO belongs in bp-custom?
 */
function ep_bp_get_sidebar() {
	// short-circuit our own index name filter to build the list
	remove_filter( 'ep_index_name', 'ep_bp_filter_ep_index_name', 10, 2 );

	?>
		<aside id="ep-bp-facets" role="complementary">
			<h3>Search Facets</h3>
			<form class="ep-bp-search-facets">
				<h5>Query</h5>
				<input type="text" name="s" value="<?php echo get_search_query(); ?>">
				<h5>Filter by type</h5>
				<?php /* TODO dynamic and filterable */ ?>
				<select multiple name="post_types[]">
					<option value="<?php echo EP_BP_API::GROUP_TYPE_NAME; ?>">Group</option>
					<option value="<?php echo EP_BP_API::MEMBER_TYPE_NAME; ?>">Member</option>
					<option value="bp_doc">Document</option>
					<option value="bp_docs_folder">Document Folder</option>
					<option value="forum">Forum</option>
					<option value="reply">Reply</option>
					<option value="topic">Topic</option>
				</select>
				<h5>Filter by network</h5>
				<select multiple name="index[]">
					<?php foreach ( get_networks() as $network ) {
						switch_to_blog( get_main_site_for_network( $network ) );
						echo '<option value="' . ep_get_index_name() . '">' . get_bloginfo() . '</option>';
						restore_current_blog();
					} ?>
				</select>
				<!--
				<h5>Sort by</h5>
				<select>
					<option name="relevance">Relevance</option>
					<option name="date">Date</option>
				</select>
				-->
				<br><br>
				<input type="submit">
			</form>
		</aside>
	<?php

	// only once. TODO
	remove_action( 'is_active_sidebar', '__return_true' );
	remove_action( 'dynamic_sidebar_before', 'ep_bp_get_sidebar' );

	// restore index name filter
	add_filter( 'ep_index_name', 'ep_bp_filter_ep_index_name', 10, 2 );
}

/**
 * Translate args to ElasticPress compat format.
 *
 * @param WP_Query $query
 */
function ep_bp_translate_args( $query ) {
	if (
		is_search() &&
		! ( defined( 'WP_CLI' ) && WP_CLI ) &&
		! apply_filters( 'ep_skip_query_integration', false, $query )
	) {

		if ( isset( $_REQUEST['post_type'] ) ) {
			$post_type = $_REQUEST['post_type'];
		} else {
			// add bp "post" types
			$post_type = array_unique( array_merge(
				(array) $query->get( 'post_type' ),
				ep_bp_post_types()
			) );
		}

		$query->set( 'post_type', $post_type );

		// search xprofile field values
		$query->set( 'search_fields', array_unique( array_merge_recursive(
			(array) $query->get( 'search_fields' ),
			[ 'taxonomies' => [ 'xprofile' ] ]
		) ) );

	}
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
function ep_bp_whitelist_taxonomies( $taxonomies ) {
	return array_merge( $taxonomies, [
		get_taxonomy( bp_get_member_type_tax_name() ),
		get_taxonomy( 'bp_group_type' ),
	] );
}

/**
 * Setup all feature filters
 */
function ep_bp_setup() {
	add_action( 'pre_get_posts', 'ep_bp_translate_args', 20 ); // after elasticpress ep_improve_default_search()
	add_action( 'wp_enqueue_scripts', 'ep_bp_enqueue_style' );

	add_action( 'pre_get_posts', function() {
		if ( is_search() ) {
			add_action( 'is_active_sidebar', '__return_true' );
			add_action( 'dynamic_sidebar_before', 'ep_bp_get_sidebar' );
		}
	} );

	//add_filter( 'ep_searchable_post_types', 'ep_bp_filter_ep_searchable_post_types' );
	add_filter( 'ep_indexable_post_types', 'ep_bp_post_types' );
	add_filter( 'ep_index_name', 'ep_bp_filter_ep_index_name', 10, 2 );
	add_filter( 'ep_default_index_number_of_shards', 'ep_bp_filter_ep_default_index_number_of_shards' );
	add_filter( 'ep_sync_taxonomies', 'ep_bp_whitelist_taxonomies' );
	add_filter( 'ep_search_request_path', 'ep_bp_filter_ep_search_request_path' );
	add_filter( 'ep_formatted_args', 'ep_bp_filter_ep_formatted_args' );
	add_filter( 'the_permalink', 'ep_bp_filter_the_permalink' );
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
		'requires_install_reindex' => false,
	] );
}
