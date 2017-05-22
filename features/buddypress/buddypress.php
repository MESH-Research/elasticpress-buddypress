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
 * output HTML for post type facet <select>
 * TODO filterable
 */
function ep_bp_post_type_select() {
	// buddypress fake "post" types
	$post_types = [
		EP_BP_API::GROUP_TYPE_NAME => 'Groups',
		EP_BP_API::MEMBER_TYPE_NAME => 'Members',
	];

	// actual post types
	foreach ( ep_get_indexable_post_types() as $post_type ) {
		$post_type_object = get_post_type_object( $post_type );
		$post_types[ $post_type_object->name ] = $post_type_object->label;
	}

	?>
	<select multiple name="post_type[]" size="<?php echo count( $post_types ); ?>">
		<?php foreach ( $post_types as $name => $label ) {
		$selected = ( ! isset( $_REQUEST['post_type'] ) || in_array( $name, $_REQUEST['post_type'] ) );
			printf( '<option value="%1$s"%3$s>%2$s</option>',
				$name,
				$label,
				( $selected ) ? ' selected' : ''
			);
		} ?>
	</select>
	<?php
}

/**
 * output HTML for network facet
 * TODO filterable
 * TODO find a way to avoid removing/adding index name filter
 */
function ep_bp_network_select() {
	// short-circuit our own index name filter to build the list
	remove_filter( 'ep_index_name', 'ep_bp_filter_ep_index_name', 10, 2 );
	?>
		<select multiple name="index[]" size="<?php echo count( get_networks() ); ?>">
		<?php foreach ( get_networks() as $network ) {
			switch_to_blog( get_main_site_for_network( $network ) );
			$selected = ( ! isset( $_REQUEST['index'] ) || in_array( ep_get_index_name(), $_REQUEST['index'] ) );
			printf( '<option value="%1$s"%3$s>%2$s</option>',
				ep_get_index_name(),
				get_bloginfo(),
				( $selected ) ? ' selected' : ''
			);
			restore_current_blog();
		} ?>
	</select>
	<?php
	// restore index name filter
	add_filter( 'ep_index_name', 'ep_bp_filter_ep_index_name', 10, 2 );
}

function ep_bp_orderby_select() {
	$options = [
		'_score' => 'Relevance',
		'date' => 'Date',
	];
	echo '<select name="orderby">';
	foreach ( $options as $value => $label ) {
		$selected = ( isset( $_REQUEST['orderby'] ) && $value === $_REQUEST['orderby'] );
		printf( '<option value="%1$s"%3$s>%2$s</option>',
			$value,
			$label,
			( $selected ) ? ' selected' : ''
		);
	}
	echo '</select>';
}

function ep_bp_order_select() {
	$options = [
		'desc' => 'Descending',
		'asc' => 'Ascending',
	];
	echo '<select name="order">';
	foreach ( $options as $value => $label ) {
		$selected = ( isset( $_REQUEST['order'] ) && $value === $_REQUEST['order'] );
		printf( '<option value="%1$s"%3$s>%2$s</option>',
			$value,
			$label,
			( $selected ) ? ' selected' : ''
		);
	}
	echo '</select>';
}

/**
 * Add search facets to sidebar.
 * TODO widgetize?
 */
function ep_bp_get_sidebar() {
	?>
	<aside id="ep-bp-facets" class="widget" role="complementary">
		<h4>Search Facets</h4>
		<form class="ep-bp-search-facets">
			<h5>Query</h5>
			<input type="text" name="s" value="<?php echo get_search_query(); ?>">
			<h5>Filter by type</h5>
			<?php ep_bp_post_type_select(); ?>
			<h5>Filter by network</h5>
			<?php ep_bp_network_select(); ?>
			<h5>Sort by</h5>
			<?php ep_bp_orderby_select(); ?>
			<?php ep_bp_order_select(); ?>
			<br><br>
			<input type="submit">
		</form>
	</aside>
	<?php

	// only once. TODO
	remove_action( 'is_active_sidebar', '__return_true' );
	remove_action( 'dynamic_sidebar_before', 'ep_bp_get_sidebar' );
}

/**
 * Adjust args to handle facets
 */
function ep_bp_filter_ep_formatted_args( $formatted_args ) {

	// not sure why yet but post_type.raw fails to match while post_type matches fine. change accordingly:
	foreach ( $formatted_args['post_filter']['bool']['must'] as &$must ) {
		// maybe term, maybe terms - depends on whether or not the value of "post_type.raw" is an array. need to handle both.
		foreach ( [ 'term', 'terms' ] as $key ) {
			if ( isset( $must[ $key ]['post_type.raw'] ) ) {
				$must[ $key ]['post_type'] = $must[ $key ]['post_type.raw'];
				unset( $must[ $key ]['post_type.raw'] );

				// re-index 'must' array keys using array_values (non-sequential keys pose problems for elasticpress)
				if ( is_array( $must[ $key ]['post_type'] ) ) {
					$must[ $key ]['post_type'] = array_values( $must[ $key ]['post_type'] );
				}
			}
		}
	}
	return $formatted_args;
}

/**
 * Translate args to ElasticPress compat format.
 *
 * @param WP_Query $query
 */
function ep_bp_translate_args( $query ) {
	/**
	 * Make sure this is an ElasticPress search query
	 */
	if ( ! ep_elasticpress_enabled( $query ) || ! $query->is_search() ) {
		return;
	}

	if ( isset( $_REQUEST['post_type'] ) && ! empty( $_REQUEST['post_type'] ) ) {
		$query->set( 'post_type', $_REQUEST['post_type'] );
	}

	if ( isset( $_REQUEST['orderby'] ) && ! empty( $_REQUEST['orderby'] ) ) {
		$query->set( 'orderby', $_REQUEST['orderby'] );
	}

	// search xprofile field values
	$query->set( 'search_fields', array_unique( array_merge_recursive(
		(array) $query->get( 'search_fields' ),
		[ 'taxonomies' => [ 'xprofile' ] ]
	), SORT_REGULAR ) );
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
 * inject "post" type into search result titles
 * TODO make configurable via ep feature settings api
 */
function ep_bp_filter_result_titles( $title, $id ) {
	global $post;

	switch ( $post->post_type ) {
		case EP_BP_API::GROUP_TYPE_NAME:
			$name = EP_BP_API::GROUP_TYPE_NAME;
			$label = 'Group';
			break;
		case EP_BP_API::MEMBER_TYPE_NAME:
			$name = EP_BP_API::MEMBER_TYPE_NAME;
			$label = 'Member';
			break;
		default:
			$post_type_object = get_post_type_object( $post->post_type );
			$name = $post_type_object->name;
			$label = $post_type_object->labels->singular_name;
			break;
	}

	$title = sprintf( '%3$s <span class="post_type %1$s">%2$s</span>',
		$name,
		$label,
		$title
	);

	return $title;
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

			// temporarily filter titles to include post type in results
			add_action( 'loop_start', function() {
				add_filter( 'the_title', 'ep_bp_filter_result_titles', 2, 20 );
			} );
			add_action( 'loop_end', function() {
				remove_filter( 'the_title', 'ep_bp_filter_result_titles', 2, 20 );
			} );
		}
	} );

	add_filter( 'ep_formatted_args', 'ep_bp_filter_ep_formatted_args' );
	add_filter( 'ep_indexable_post_types', 'ep_bp_post_types' );
	add_filter( 'ep_index_name', 'ep_bp_filter_ep_index_name', 10, 2 );
	add_filter( 'ep_default_index_number_of_shards', 'ep_bp_filter_ep_default_index_number_of_shards' );
	add_filter( 'ep_sync_taxonomies', 'ep_bp_whitelist_taxonomies' );
	add_filter( 'ep_search_request_path', 'ep_bp_filter_ep_search_request_path' );
	add_filter( 'the_permalink', 'ep_bp_filter_the_permalink' );

	// this filter can cause infinite loops while indexing posts when titles are empty
	// TODO can this be added/removed in a more exact way?
	remove_filter( 'the_title', 'bbp_get_reply_title_fallback', 2, 2 );
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
