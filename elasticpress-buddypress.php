<?php
/**
 * Plugin Name: ElasticPress BuddyPress
 * Version: alpha
 * Description: ElasticPress custom feature to support BuddyPress content.
 * Text Domain: elasticpress-buddypress
 */

function ep_bp_translate_args( $query ) {
	var_dump( func_get_args() ); die;

	// Lets make sure this doesn't interfere with the CLI
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}

	if ( apply_filters( 'ep_skip_query_integration', false, $query ) ) {
		return;
	}

	$admin_integration = apply_filters( 'ep_admin_wp_query_integration', false );

	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		if ( ! apply_filters( 'ep_ajax_wp_query_integration', false ) ) {
			return;
		} else {
			$admin_integration = true;
		}
	}

	if ( is_admin() && ! $admin_integration ) {
		return;
	}

	$product_name = $query->get( 'product', false );

	$post_parent = $query->get( 'post_parent', false );

	/**
	 * Do nothing for single product queries
	 */
	if ( ! empty( $product_name ) ) {
		return;
	}

	/**
	 * ElasticPress does not yet support post_parent queries
	 */
	if ( ! empty( $post_parent ) ) {
		return;
	}

	/**
	 * Cant hook into WC API yet
	 */
	if ( defined( 'WC_API_REQUEST' ) && WC_API_REQUEST ) {
		return;
	}

	// Flag to check and make sure we are in a WooCommerce specific query
	$integrate = false;

	/**
	 * Force ElasticPress if we are querying WC taxonomy
	 */
	$tax_query = $query->get( 'tax_query', array() );

	$supported_taxonomies = array(
		'product_cat',
		'pa_brand',
		'product_tag',
		'pa_sort-by',
	);

	if ( ! empty( $tax_query ) ) {

		/**
		 * First check if already set taxonomies are supported WC taxes
		 */
		foreach ( $tax_query as $taxonomy_array ) {
			if ( isset( $taxonomy_array['taxonomy'] ) && in_array( $taxonomy_array['taxonomy'], $supported_taxonomies ) ) {
				$integrate = true;
			}
		}
	}

	/**
	 * Next check if any taxonomies are in the root of query vars (shorthand form)
	 */
	foreach ( $supported_taxonomies as $taxonomy ) {
		$term = $query->get( $taxonomy, false );

		if ( ! empty( $term ) ) {
			$integrate = true;

			$terms = array( $term );

			// to add child terms to the tax query
			if ( is_taxonomy_hierarchical( $taxonomy ) ) {
				$term_object = get_term_by( 'slug', $term, $taxonomy );
				$children    = get_term_children( $term_object->term_id, $taxonomy );
				if ( $children ) {
					foreach ( $children as $child ) {
						$child_object = get_term( $child, $taxonomy );
						$terms[]      = $child_object->slug;
					}
				}

			}

			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $terms,
			);
		}
	}

	/**
	 * Force ElasticPress if product post type query
	 */
	$post_type = $query->get( 'post_type', false );

	// Act only on a defined subset of all indexable post types here
	$supported_post_types = array_intersect(
		array(
			'product',
			'shop_order',
			'shop_order_refund',
			'product_variation'
		),
		ep_get_indexable_post_types()
	);

	// For orders it queries an array of shop_order and shop_order_refund post types, hence an array_diff
	if ( ! empty( $post_type ) && ( in_array( $post_type, $supported_post_types ) || ( is_array( $post_type ) && ! array_diff( $post_type, $supported_post_types ) ) ) ) {
		$integrate = true;
	}

	/**
	 * If we have a WooCommerce specific query, lets hook it to ElasticPress and make the query ElasticSearch friendly
	 */
	if ( $integrate ) {
		// Set tax_query again since we may have added things
		$query->set( 'tax_query', $tax_query );

		// Default to product if no post type is set
		if ( empty( $post_type ) ) {
			$post_type = 'product';
			$query->set( 'post_type', 'product' );
		}

		// Handles the WC Top Rated Widget
		if ( has_filter( 'posts_clauses', array( WC()->query, 'order_by_rating_post_clauses' ) ) ) {
			remove_filter( 'posts_clauses', array( WC()->query, 'order_by_rating_post_clauses' ) );
			$query->set( 'orderby', 'meta_value_num' );
			$query->set( 'meta_key', '_wc_average_rating' );
		}

		/**
		 * We can't support any special fields parameters
		 */
		$fields = $query->get( 'fields', false );
		if ( 'ids' === $fields || 'id=>parent' === $fields ) {
			$query->set( 'fields', 'default' );
		}

		/**
		 * Handle meta queries
		 */
		$meta_query = $query->get( 'meta_query', array() );
		$meta_key = $query->get( 'meta_key', false );
		$meta_value = $query->get( 'meta_value', false );

		if ( ! empty( $meta_key ) && ! empty( $meta_value ) ) {
			$meta_query[] = array(
				'key' => $meta_key,
				'value' => $meta_value,
			);

			$query->set( 'meta_query', $meta_query );
		}

		/**
		 * Make sure filters are suppressed
		 */
		$query->query['suppress_filters'] = false;
		$query->set( 'suppress_filters', false );

		$orderby = $query->get( 'orderby' );

		if ( ! empty( $orderby ) && 'rand' === $orderby ) {
			$query->set( 'orderby', false ); // Just order by relevance.
		}

		$s = $query->get( 's' );

		$query->query_vars['ep_integrate'] = true;
		$query->query['ep_integrate'] = true;

		if ( ! empty( $s ) ) {
			$query->set( 'orderby', false ); // Just order by relevance.

			/**
			 * Default order when doing search in Woocommerce is 'ASC'
			 * These lines will change it to 'DESC' as we want to most relevant result
			 */
			if ( empty( $_GET['orderby'] ) && $query->is_main_query() ) {
				$query->set( 'order', 'DESC' );
			}

			// Search query
			if ( 'shop_order' === $post_type ) {
				$search_fields = $query->get( 'search_fields', array( 'post_title', 'post_content', 'post_excerpt' ) );

				$search_fields['meta'] = array_map( 'wc_clean', apply_filters( 'shop_order_search_fields', array(
					'_order_key',
					'_billing_company',
					'_billing_address_1',
					'_billing_address_2',
					'_billing_city',
					'_billing_postcode',
					'_billing_country',
					'_billing_state',
					'_billing_email',
					'_billing_phone',
					'_shipping_address_1',
					'_shipping_address_2',
					'_shipping_city',
					'_shipping_postcode',
					'_shipping_country',
					'_shipping_state',
					'_billing_last_name',
					'_billing_first_name',
					'_shipping_first_name',
					'_shipping_last_name',
				) ) );

				$query->set( 'search_fields', $search_fields );
			} elseif ( 'product' === $post_type ) {
				$search_fields = $query->get( 'search_fields', array( 'post_title', 'post_content', 'post_excerpt' ) );

				// Make sure we search skus on the front end
				$search_fields['meta'] = array( '_sku' );

				// Search by proper taxonomies on the front end
				$search_fields['taxonomies'] = array( 'category', 'post_tag', 'product_tag', 'product_cat' );

				$query->set( 'search_fields', $search_fields );
			}
		} else {
			/**
			 * For default sorting by popularity (total_sales) and rating
	         * Woocommerce doesn't set the orderby correctly.
	         * These lines will check the meta_key and correct the orderby based on that.
	         * And this won't run in search result and only run in main query
			 */
			$meta_key = $query->get( 'meta_key', false );
			if ( $meta_key && $query->is_main_query() ){
				switch ( $meta_key ){
					case 'total_sales':
						$query->set( 'orderby', ep_wc_get_orderby_meta_mapping( 'total_sales' ) );
						$query->set( 'order', 'DESC' );
						break;
					case '_wc_average_rating':
						$query->set( 'orderby', ep_wc_get_orderby_meta_mapping( '_wc_average_rating' ) );
						$query->set( 'order', 'DESC' );
						break;
				}
			}
		}

		/**
		 * Set orderby from GET param
		 * Also make sure the orderby param affects only the main query
		 */
		if ( ! empty( $_GET['orderby'] ) && $query->is_main_query() ) {

			switch ( $_GET['orderby'] ) {
				case 'popularity':
					$query->set( 'orderby', ep_wc_get_orderby_meta_mapping( 'total_sales' ) );
					$query->set( 'order', 'DESC' );
					break;
				case 'price':
					$query->set( 'order', 'ASC' );
					$query->set( 'orderby', ep_wc_get_orderby_meta_mapping( '_price' ) );
					break;
				case 'price-desc':
					$query->set( 'order', 'DESC' );
					$query->set( 'orderby', ep_wc_get_orderby_meta_mapping( '_price' ) );
					break;
				case 'rating' :
					$query->set( 'orderby', ep_wc_get_orderby_meta_mapping( '_wc_average_rating' ) );
					$query->set( 'order', 'DESC' );
					break;
				case 'date':
					$query->set( 'orderby', ep_wc_get_orderby_meta_mapping( 'date' ) );
					break;
				case 'ID':
					$query->set( 'orderby', ep_wc_get_orderby_meta_mapping( 'ID' ) );
					break;
				default:
					$query->set( 'orderby', ep_wc_get_orderby_meta_mapping( 'menu_order' ) ); // Order by menu and title.
			}
		}
	}
}

function ep_bp_setup() {
	var_dump( __METHOD__ );
	//add_filter( 'ep_sync_insert_permissions_bypass', 'ep_wc_bypass_order_permissions_check', 10, 2 );
	//add_filter( 'ep_elasticpress_enabled', 'ep_wc_blacklist_coupons', 10 ,2 );
	//add_filter( 'ep_prepare_meta_allowed_protected_keys', 'ep_wc_whitelist_meta_keys', 10, 2 );
	//add_filter( 'woocommerce_shop_order_search_fields', 'ep_wc_shop_order_search_fields', 9999 );
	//add_filter( 'woocommerce_layered_nav_query_post_ids', 'ep_wc_convert_post_object_to_id', 10, 4 );
	//add_filter( 'woocommerce_unfiltered_product_ids', 'ep_wc_convert_post_object_to_id', 10, 4 );
	//add_filter( 'ep_sync_taxonomies', 'ep_wc_whitelist_taxonomies', 10, 2 );
	//add_filter( 'ep_post_sync_args_post_prepare_meta', 'ep_wc_remove_legacy_meta', 10, 2 );
	add_action( 'pre_get_posts', 'ep_bp_translate_args', 11, 1 );
	var_dump( 'setting ep bp up' );
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
	ep_register_feature( 'buddypress', array(
		'title' => 'BuddyPress',
		'setup_cb' => 'ep_bp_setup',
		'requirements_status_cb' => 'ep_bp_requirements_status',
		'feature_box_summary_cb' => 'ep_bp_feature_box_summary',
		'requires_install_reindex' => true,
	) );
}

if ( class_exists( 'EP_API' ) ) {
	add_action( 'plugins_loaded', 'ep_bp_register_feature' );
}
