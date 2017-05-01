<?php
/**
 * Functions to add ElasticPress support for BuddyPress non-post content like group and members.
 * Inspired by EP_API.
 */
class EP_BP_API {

	/**
	 * Maximum number of members to include per elasticpress POST request when bulk syncing.
	 */
	const MAX_BULK_MEMBERS_PER_PAGE = 350;

	/**
	 * Maximum number of groups to include per elasticpress POST request when bulk syncing.
	 */
	const MAX_BULK_GROUPS_PER_PAGE = 350;

	/**
	 * Object type name used to fetch taxonomies and index/query elasticsearch
	 */
	const MEMBER_TYPE_NAME = 'user';

	/**
	 * Object type name used to fetch taxonomies and index/query elasticsearch
	 */
	const GROUP_TYPE_NAME = 'bp_group';

	/**
	 * Type of object currently being processed
	 */
	private $type;

	/**
	 * Prepare a group for syncing
	 * Must be inside the groups loop.
	 *
	 * @param int $group_id
	 * @return bool|array
	 */
	public function prepare_group( $group ) {
		$groupmeta = groups_get_groupmeta( $group->id );

		$args = [
			'post_id'           => $group->id,
			'ID'                => $group->id,
			'post_author'       => $this->get_user_data( get_userdata( $group->creator_id ) ),
			'post_date'         => $group->date_created,
			'post_date_gmt'     => $group->date_created,
			'post_title'        => $this->prepare_text_content( $group->name ),
			'post_excerpt'      => $this->prepare_text_content( $group->description ),
			'post_content'      => $this->prepare_text_content( $group->description ),
			'post_status'       => 'publish',
			'post_name'         => $this->prepare_text_content( $group->name ),
			'post_modified'     => null,
			'post_modified_gmt' => null,
			'post_parent'       => 0,
			'post_type'         => self::GROUP_TYPE_NAME,
			'post_mime_type'    => '',
			'permalink'         => bp_get_group_permalink(),
			'terms'             => $this->prepare_terms( $group ),
			'post_meta'         => [],
			'date_terms'        => [],
			'comment_count'     => 0,
			'comment_status'    => 0,
			'ping_status'       => 0,
			'menu_order'        => 0,
			'guid'              => bp_get_group_permalink(),
		];

		return $args;
	}

	/**
	 * Prepare a member for syncing
	 * Must be inside the members loop.
	 *
	 * @param int $group_id
	 * @return bool|array
	 */
	public function prepare_member( $user ) {
		$xprofile_terms = ( function() use ( $user ) {
			$fields = [];

			if ( bp_has_profile( [ 'user_id' => $user->ID ] ) ) {
				while ( bp_profile_groups() ) {
					bp_the_profile_group();

					if (
						bp_profile_group_has_fields() &&
						apply_filters( 'ep_bp_index_xprofile_group_' . bp_get_the_profile_group_slug(), true )
					) {
						while ( bp_profile_fields() ) {
							bp_the_profile_field();

							if ( apply_filters( 'ep_bp_index_xprofile_field_' . bp_get_the_profile_field_id(), true ) ) {
								$fields[] = [
									'term_id' => bp_get_the_profile_field_id(),
									'slug' => bp_get_the_profile_field_name(),
									'name' => bp_get_the_profile_field_value(),
									'parent' => bp_get_the_profile_group_name(),
								];
							}
						}
					}
				}
			}

			return [ 'xprofile' => $fields ];
		} )();

		$args = [
			'post_id'           => $user->ID,
			'ID'                => $user->ID,
			'post_author'       => $this->get_user_data( $user ),
			'post_date'         => $user->user_registered,
			'post_date_gmt'     => $user->user_registered,
			'post_title'        => $this->prepare_text_content( $user->display_name ),
			'post_excerpt'      => $this->prepare_text_content( make_clickable( bp_get_member_permalink() ) ),
			'post_content'      => null,
			'post_status'       => 'publish',
			'post_name'         => $this->prepare_text_content( $user->display_name ),
			'post_modified'     => null,
			'post_modified_gmt' => null,
			'post_parent'       => 0,
			'post_type'         => self::MEMBER_TYPE_NAME,
			'post_mime_type'    => '',
			'permalink'         => bp_get_member_permalink(),
			'terms'             => array_merge( $this->prepare_terms( $user ), $xprofile_terms ),
			'post_meta'         => [],
			'date_terms'        => [],
			'comment_count'     => 0,
			'comment_status'    => 0,
			'ping_status'       => 0,
			'menu_order'        => 0,
			'guid'              => bp_get_member_permalink(),
		];

		return $args;
	}

	/**
	 * Normalized author data for any object type.
	 *
	 * @param WP_User $user
	 * @return array user data
	 */
	private function get_user_data( $user ) {
		if ( $user instanceof WP_User ) {
			$user_data = [
				'raw'          => $user->user_login,
				'login'        => $user->user_login,
				'display_name' => $user->display_name,
				'id'           => $user->ID,
			];
		} else {
			$user_data = [
				'raw'          => '',
				'login'        => '',
				'display_name' => '',
				'id'           => '',
			];
		}

		return $user_data;
	}

	/**
	 * Send a request to EP_API.
	 * Allows bulk_index_* functions to loop through objects and fire off successive requests of a reasonable size.
	 *
	 * @param string $type type of object e.g. 'member' or 'group'
	 * @param array $objects see prepare_member() and prepare_group() for expected array format
	 * @return object decoded response
	 */
	private function send_request( $objects ) {
		$flatten = [];

		foreach ( $objects as $object ) {
			$flatten[] = $object[0];
			$flatten[] = $object[1];
		}

		$path = trailingslashit( ep_get_index_name( bp_get_root_blog_id() ) ) . "{$this->type}/_bulk";

		// make sure to add a new line at the end or the request will fail
		$body = rtrim( implode( "\n", $flatten ) ) . "\n";

		$request_args = array(
			'method'  => 'POST',
			'body'    => $body,
			'timeout' => 30,
		);

		$request = ep_remote_request( $path, apply_filters( 'ep_bulk_index_posts_request_args', $request_args, $body ) );

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		$response = wp_remote_retrieve_response_code( $request );

		if ( 200 !== $response ) {
			return new WP_Error( $response, wp_remote_retrieve_response_message( $request ), $request );
		}

		return json_decode( wp_remote_retrieve_body( $request ), true );
	}

	/**
	 * Bulk index all groups.
	 * The equivalent functionality for posts in EP_API is spread out over several functions.
	 * This is a "condensed" adapted version that handles all required preparation as well as indexing.
	 *
	 * @param array $args passed to bp_has_groups()
	 * @return bool success
	 */
	public function bulk_index_groups( $args = [] ) {
		global $groups_template;

		$this->type = self::GROUP_TYPE_NAME;

		$groups = [];

		$args = array_merge( [
			'per_page' => self::MAX_BULK_GROUPS_PER_PAGE,
			'page' => 1,
		], $args );

		$querystring = bp_ajax_querystring( 'groups' ) . '&' . http_build_query( $args );

		if ( bp_has_groups( $querystring ) ) {
			while ( bp_groups() ) {
				bp_the_group();
				$group_args = $this->prepare_group( $groups_template->group );
				$groups[ $groups_template->group->id ][] = '{ "index": { "_id": "' . $groups_template->group->id . '" } }';
				$groups[ $groups_template->group->id ][] = addcslashes( wp_json_encode( $group_args ), "\n" );
			}

			$this->send_request( $groups );

			$this->bulk_index_groups( [
				'page' => $args['page'] + 1,
			] );
		}

		return true;
	}

	/**
	 * Bulk index all members.
	 * See also bulk_index_groups()
	 *
	 * @param array $args passed to bp_has_members()
	 * @return bool success
	 */
	public function bulk_index_members( $args = [] ) {
		global $members_template;

		$this->type = self::MEMBER_TYPE_NAME;

		$members = [];

		$args = array_merge( [
			'per_page' => self::MAX_BULK_MEMBERS_PER_PAGE,
			'page' => 1,
		], $args );

		$querystring = bp_ajax_querystring( 'members' ) . '&' . http_build_query( $args );

		if ( bp_has_members( $querystring ) ) {
			while ( bp_members() ) {
				bp_the_member();
				$member_args = $this->prepare_member( $members_template->member );
				$members[ $members_template->member->id ][] = '{ "index": { "_id": "' . $members_template->member->id . '" } }';
				$members[ $members_template->member->id ][] = addcslashes( wp_json_encode( $member_args ), "\n" );
			}

			$this->send_request( $members );

			$this->bulk_index_members( [
				'page' => $args['page'] + 1,
			] );
		}

		return true;
	}

	/**
	 * Ripped straight from EP_API.
	 *
	 * @param  string $content
	 * @return string
	 */
	private function prepare_text_content( $content ) {
		//$content = strip_tags( $content ); // preserve links in results.
		$content = preg_replace( '#[\n\r]+#s', ' ', $content );

		return $content;
	}

	/**
	 * Prepare terms to send to ES.
	 * Modified from EP_API.
	 *
	 * @param WP_User|BP_Groups_Group $object user or group
	 *
	 * @since 0.1.0
	 * @return array
	 */
	private function prepare_terms( $object ) {
		$taxonomy_names = get_object_taxonomies( $this->type );

		$selected_taxonomies = array();

		foreach ( $taxonomy_names as $taxonomy_name ) {
			$taxonomy = get_taxonomy( $taxonomy_name );
			if ( $taxonomy->public ) {
				$selected_taxonomies[] = $taxonomy;
			}
		}

		$selected_taxonomies = apply_filters( 'ep_sync_taxonomies', $selected_taxonomies, $object );

		if ( empty( $selected_taxonomies ) ) {
			return array();
		}

		$terms = array();

		$allow_hierarchy = apply_filters( 'ep_sync_terms_allow_hierarchy', false );

		foreach ( $selected_taxonomies as $taxonomy ) {

			$object_terms = wpmn_get_object_terms(
				( isset( $object->ID ) ) ? $object->ID : $object->id, // groups have lowercase id property, members upper
				$taxonomy->name
			);

			if ( ! $object_terms || is_wp_error( $object_terms ) ) {
				continue;
			}

			$terms_dic = array();

			foreach ( $object_terms as $term ) {
				if( ! isset( $terms_dic[ $term->term_id ] ) ) {
					$terms_dic[ $term->term_id ] = array(
						'term_id'  => $term->term_id,
						'slug'     => $term->slug,
						'name'     => $term->name,
						'parent'   => $term->parent
					);
					if( $allow_hierarchy ){
						$terms_dic = $this->get_parent_terms( $terms_dic, $term, $taxonomy->name );
					}
				}
			}
			$terms[ $taxonomy->name ] = array_values( $terms_dic );
		}

		return $terms;
	}

	/**
	 * Ripped straight from EP_API.
	 *
	 * @return EP_BP_API
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance  ) {
			$instance = new self();
		}

		return $instance;
	}

}

/**
 * Accessor functions for methods in above class. See doc blocks above for function details.
 */

function ep_bp_bulk_index_groups() {
	return EP_BP_API::factory()->bulk_index_groups();
}

function ep_bp_bulk_index_members() {
	return EP_BP_API::factory()->bulk_index_members();
}
