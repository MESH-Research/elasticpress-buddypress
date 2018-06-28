<?php
/**
 * Filters for the ElasticPress BuddyPress feature.
 *
 * @package Elasticpress_Buddypress
 */

/**
 * Filter search request path to search groups & members as well as posts.
 *
 * @param string $path Search request path.
 */
function ep_bp_filter_ep_search_request_path( $path ) {
	return str_replace( '/post/', '/', $path );
}

/**
 * Filter index name to include all sub-blogs when on a root blog.
 * This is optional and only affects multinetwork installs.
 *
 * @param string $index_name Index.
 * @param int    $blog_id Blog.
 */
function ep_bp_filter_ep_index_name( $index_name, $blog_id ) {
	// Since we call ep_get_index_name() which uses this filter, we need to disable the filter while this function runs.
	remove_filter( 'ep_index_name', 'ep_bp_filter_ep_index_name', 10, 2 );

	$index_names = [ $index_name ];

	// Checking is_search() prevents changing index name while indexing.
	if ( is_search() ) {
		foreach ( get_networks() as $network ) {
			$network_main_site_id = get_main_site_for_network( $network );
			$index_names[]        = ep_get_index_name( $network_main_site_id );
		}

		// Handle facets.
		if ( isset( $_REQUEST['index'] ) ) {
			$index_names = $_REQUEST['index'];
		}
	}

	// Restore filter now that we're done abusing ep_get_index_name().
	add_filter( 'ep_index_name', 'ep_bp_filter_ep_index_name', 10, 2 );

	return implode( ',', array_unique( $index_names ) );
}

/**
 * This is an attempt at limiting the total number of shards to make searching lots of sites in multinetwork feasible.
 * Not necessary unless querying lots of sites at once.
 *
 * @param int $number_of_shards Shard count.
 * @return int
 */
function ep_bp_filter_ep_default_index_number_of_shards( $number_of_shards ) {
	$number_of_shards = 1;
	return $number_of_shards;
}

/**
 * Filter the search results loop to fix some bad permalinks.
 *
 * @param string $permalink Permalink.
 * @return string
 */
function ep_bp_filter_the_permalink( $permalink ) {
	global $wp_query, $post;

	if ( $wp_query->is_search ) {
		if ( in_array( $post->post_type, [ EP_BP_API::GROUP_TYPE_NAME, EP_BP_API::MEMBER_TYPE_NAME ] ) ) {
			$permalink = $post->permalink;
		} elseif ( in_array( $post->post_type, [ 'reply' ] ) ) {
			$permalink = bbp_get_topic_permalink( $post->post_parent ) . "#post-{$post->ID}";
		}
	}

	return $permalink;
}


/**
 * Adjust args to handle facets.
 *
 * @param array $formatted_args Args from EP.
 * @return array
 */
function ep_bp_filter_ep_formatted_args( $formatted_args ) {
	// Because we changed the mapping for post_type with ep_bp_filter_ep_config_mapping(), change query accordingly.
	foreach ( $formatted_args['post_filter']['bool']['must'] as &$must ) {
		// Maybe term, maybe terms - depends on whether or not the value of "post_type.raw" is an array. need to handle both.
		foreach ( [ 'term', 'terms' ] as $key ) {
			if ( isset( $must[ $key ]['post_type.raw'] ) ) {
				$must[ $key ]['post_type'] = $must[ $key ]['post_type.raw'];
				unset( $must[ $key ]['post_type.raw'] );

				// Re-index 'must' array keys using array_values (non-sequential keys pose problems for elasticpress).
				if ( is_array( $must[ $key ]['post_type'] ) ) {
					$must[ $key ]['post_type'] = array_values( $must[ $key ]['post_type'] );
				}
			}
		}
	}

	// Remove xprofile from highest priority of matched fields, so other fields have more boost.
	$existing_fields = ( isset( $formatted_args['query']['bool']['should'][0]['multi_match']['fields'] ) )
		? $formatted_args['query']['bool']['should'][0]['multi_match']['fields']
		: [];
	$formatted_args['query']['bool']['should'][0]['multi_match']['fields'] = array_values(
		array_diff(
			$existing_fields,
			[ 'terms.xprofile.name' ]
		)
	);

	// Add a match block to give extra boost to matches in post name.
	$existing_query                            = ( isset( $formatted_args['query']['bool']['should'][0]['multi_match']['query'] ) )
		? $formatted_args['query']['bool']['should'][0]['multi_match']['query']
		: [];
	$formatted_args['query']['bool']['should'] = array_values(
		array_merge(
			[
				[
					'multi_match' => [
						'query'  => $existing_query,
						'type'   => 'phrase',
						'fields' => [ 'post_title' ],
						'boost'  => 4,
					],
				],
			],
			$formatted_args['query']['bool']['should']
		)
	);

	if ( empty( $_REQUEST['s'] ) ) {
		// Remove query entirely since results are incomplete otherwise.
		unset( $formatted_args['query'] );

		// "Relevancy" has no significance without a search query as context, just sort by most recent.
		$formatted_args['sort'] = [
			[
				'post_date' => [ 'order' => 'desc' ],
			],
		];
	}

	return $formatted_args;
}

/**
 * Translate args to ElasticPress compat format.
 *
 * @param WP_Query $query Search query.
 */
function ep_bp_translate_args( $query ) {
	/**
	 * Make sure this is an ElasticPress search query
	 */
	if ( ! ep_elasticpress_enabled( $query ) || ! $query->is_search() ) {
		return;
	}

	$fallback_post_types = apply_filters(
		'ep_bp_fallback_post_type_facet_selection', [
			EP_BP_API::GROUP_TYPE_NAME,
			EP_BP_API::MEMBER_TYPE_NAME,
			'topic',
			'reply',
		]
	);

	if ( ! isset( $_REQUEST['post_type'] ) || empty( $_REQUEST['post_type'] ) ) {
		$_REQUEST['post_type'] = $fallback_post_types;
	}

	$query->set( 'post_type', $_REQUEST['post_type'] );

	if ( ! isset( $_REQUEST['index'] ) ) {
		// TODO find a way to avoid removing & adding this filter again.
		remove_filter( 'ep_index_name', 'ep_bp_filter_ep_index_name', 10, 2 );
		$_REQUEST['index'] = [ ep_get_index_name() ];
		add_filter( 'ep_index_name', 'ep_bp_filter_ep_index_name', 10, 2 );
	}

	if ( isset( $_REQUEST['orderby'] ) && ! empty( $_REQUEST['orderby'] ) ) {
		$query->set( 'orderby', $_REQUEST['orderby'] );
	}

	if ( isset( $_REQUEST['paged'] ) && ! empty( $_REQUEST['paged'] ) ) {
		$query->set( 'paged', $_REQUEST['paged'] );
	}

	// Search xprofile field values.
	$query->set(
		'search_fields', array_unique(
			array_merge_recursive(
				(array) $query->get( 'search_fields' ),
				[ 'taxonomies' => [ 'xprofile' ] ]
			), SORT_REGULAR
		)
	);
}

/**
 * Index BP-related post types
 *
 * @param  array $post_types Existing post types.
 * @return array
 */
function ep_bp_post_types( $post_types = [] ) {
	return array_unique(
		array_merge(
			$post_types, [
				bbp_get_topic_post_type() => bbp_get_topic_post_type(),
				bbp_get_reply_post_type() => bbp_get_reply_post_type(),
			]
		)
	);
}

/**
 * Index BP taxonomies
 *
 * @param   array $taxonomies Index taxonomies array.
 * @return  array
 */
function ep_bp_whitelist_taxonomies( $taxonomies ) {
	return array_merge(
		$taxonomies, [
			get_taxonomy( bp_get_member_type_tax_name() ),
			get_taxonomy( 'bp_group_type' ),
		]
	);
}

/**
 * Inject "post" type labels into search result titles.
 * TODO make configurable via ep feature settings api.
 *
 * @param string $title Post title.
 */
function ep_bp_filter_result_titles( $title ) {
	global $post;

	// If we're filtering the_title_attribute() rather than the_title(), bail.
	foreach ( debug_backtrace() as $bt ) {
		if ( isset( $bt['function'] ) && 'the_title_attribute' === $bt['function'] ) {
			return $title;
		}
	}

	switch ( $post->post_type ) {
		case EP_BP_API::GROUP_TYPE_NAME:
			$name  = EP_BP_API::GROUP_TYPE_NAME;
			$label = 'Group';
			break;
		case EP_BP_API::MEMBER_TYPE_NAME:
			$name  = EP_BP_API::MEMBER_TYPE_NAME;
			$label = 'Member';
			break;
		default:
			$post_type_object = get_post_type_object( $post->post_type );
			$name             = $post_type_object->name;
			$label            = $post_type_object->labels->singular_name;
			break;
	}

	$tag = sprintf(
		'<span class="post_type %1$s">%2$s</span>',
		$name,
		$label
	);

	if ( strpos( $title, $tag ) !== 0 ) {
		$title = $tag . str_replace( $tag, '', $title );
	}

	return $title;
}

/**
 * Change author links to point to profiles rather than /author/username
 *
 * @param string $link Author link.
 * @return string
 */
function ep_bp_filter_result_author_link( $link ) {
	$link = str_replace( '/author/', '/members/', $link );
	return $link;
}

/**
 * Remove posts from results which are duplicates of other posts in all aspects except network.
 * e.g. for a member of two networks, if both results appear on a given page, only show the first.
 * No additional results are added to fill in gaps - infinite scroll with potentially < 10 results per page is acceptable.
 *
 * IMPORTANT: there is an equivalent clientside function that handles dupes which appear on different pages.
 * Update that also if you change the logic here.
 *
 * @param array $results Search results.
 * @return array
 */
function ep_bp_filter_ep_search_results_array( $results ) {
	foreach ( $results['posts'] as $k => $this_post ) {
		foreach ( array_slice( $results['posts'], $k + 1 ) as $that_post ) {
			if (
				$this_post['ID'] === $that_post['ID'] &&
				$this_post['post_title'] === $that_post['post_title']
			) {
				unset( $results['posts'][ $k ] );
			}
		}
	}

	return $results;
}

/**
 * Filter out private bbpress content this way instead of a meta_query since that also excludes some non-replies.
 * This takes the place of bbp_pre_get_posts_normalize_forum_visibility().
 *
 * @param bool  $kill If true, don't sync the post.
 * @param array $post_args Post args.
 * @param array $post_id Post id.
 * @return bool
 */
function ep_bp_filter_ep_post_sync_kill( $kill, $post_args, $post_id ) {
	$meta = get_post_meta( $post_id );
	if ( isset( $meta['_bbp_forum_id'] ) && array_intersect( $meta['_bbp_forum_id'], bbp_exclude_forum_ids( 'array' ) ) ) {
		$kill = true;
	}
	return $kill;
}

/**
 * Unless we change post_type from text to keyword, searches for some of our buddypress fake "post" types return no results.
 *
 * @param array $mapping Elasticsearch index mapping.
 */
function ep_bp_filter_ep_config_mapping( $mapping ) {
	$mapping['mappings']['post']['properties']['post_type'] = [
		'type' => 'keyword',
	];
	return $mapping;
}

/**
 * Elasticpress doesn't turn on integration if the search query is empty.
 * We consider that a valid use case to return all results (according to filters) so enable it anyway.
 *
 * @param bool     $enabled Integration enabled.
 * @param WP_Query $query Search query.
 * @return bool
 */
function ep_bp_filter_ep_elasticpress_enabled( $enabled, $query ) {
	if ( method_exists( $query, 'is_search' ) && $query->is_search() && isset( $_REQUEST['s'] ) ) {
		$enabled = true;
	}
	return $enabled;
}
