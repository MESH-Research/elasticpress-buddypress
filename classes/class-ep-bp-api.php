<?php
/**
 * Functions to add ElasticPress support for BuddyPress non-post content like group and members.
 * Inspired by EP_API.
 */
class EP_BP_API {
	/**
	 * Prepare a group for syncing
	 *
	 * @param int $group_id
	 * @return bool|array
	 */
	public function prepare_group( $group_id ) {
		$group = groups_get_group( $group_id );
		$groupmeta = groups_get_groupmeta( $group_id );

		$user = get_userdata( $group->creator_id );

		if ( $user instanceof WP_User ) {
			$user_data = array(
				'raw'          => $user->user_login,
				'login'        => $user->user_login,
				'display_name' => $user->display_name,
				'id'           => $user->ID,
			);
		} else {
			$user_data = array(
				'raw'          => '',
				'login'        => '',
				'display_name' => '',
				'id'           => '',
			);
		}

		$group_date = $group->date_created;
		$group_date_gmt = $group->date_created;
		$group_modified = $groupmeta['last_activity'][0];
		$group_modified_gmt = $groupmeta['last_activity'][0];
		$comment_count = 0;
		$comment_status = 0;
		$ping_status = 0;
		$menu_order = 0;

		if ( ! strtotime( $group_date ) || $group_date === "0000-00-00 00:00:00" ) {
			$group_date = null;
		}
		if ( ! strtotime( $group_date_gmt ) || $group_date_gmt === "0000-00-00 00:00:00" ) {
			$group_date_gmt = null;
		}
		if ( ! strtotime( $group_modified ) || $group_modified === "0000-00-00 00:00:00" ) {
			$group_modified = null;
		}
		if ( ! strtotime( $group_modified_gmt ) || $group_modified_gmt === "0000-00-00 00:00:00" ) {
			$group_modified_gmt = null;
		}

		$group_args = [
			'post_id'           => $group_id,
			'ID'                => $group_id,
			'post_author'       => $user_data,
			'post_date'         => $group_date,
			'post_date_gmt'     => $group_date_gmt,
			'post_title'        => $this->prepare_text_content( $group->name ),
			'post_excerpt'      => $this->prepare_text_content( $group->description ),
			'post_content'      => $this->prepare_text_content( $group->description ),
			'post_status'       => 'publish',
			'post_name'         => $this->prepare_text_content( $group->name ),
			'post_modified'     => $group_modified,
			'post_modified_gmt' => $group_modified_gmt,
			'post_parent'       => 0,
			'post_type'         => 'group',
			'post_mime_type'    => '',
			'permalink'         => bp_get_group_permalink( $group_id ),
			'terms'             => [],
			'post_meta'         => [],
			'date_terms'        => [],
			'comment_count'     => $comment_count,
			'comment_status'    => $comment_status,
			'ping_status'       => $ping_status,
			'menu_order'        => $menu_order,
			'guid'              => bp_get_group_permalink( $group_id ),
		];

		return $group_args;
	}

	/**
	 * Prepare a member for syncing
	 *
	 * @param int $group_id
	 * @return bool|array
	 */
	public function prepare_member( $member_id ) {
		var_dump( func_get_args() ); die;
		$group = groups_get_group( $group_id );
		$groupmeta = groups_get_groupmeta( $group_id );

		$user = get_userdata( $group->creator_id );

		if ( $user instanceof WP_User ) {
			$user_data = array(
				'raw'          => $user->user_login,
				'login'        => $user->user_login,
				'display_name' => $user->display_name,
				'id'           => $user->ID,
			);
		} else {
			$user_data = array(
				'raw'          => '',
				'login'        => '',
				'display_name' => '',
				'id'           => '',
			);
		}

		$group_date = $group->date_created;
		$group_date_gmt = $group->date_created;
		$group_modified = $groupmeta['last_activity'][0];
		$group_modified_gmt = $groupmeta['last_activity'][0];
		$comment_count = 0;
		$comment_status = 0;
		$ping_status = 0;
		$menu_order = 0;

		if ( ! strtotime( $group_date ) || $group_date === "0000-00-00 00:00:00" ) {
			$group_date = null;
		}
		if ( ! strtotime( $group_date_gmt ) || $group_date_gmt === "0000-00-00 00:00:00" ) {
			$group_date_gmt = null;
		}
		if ( ! strtotime( $group_modified ) || $group_modified === "0000-00-00 00:00:00" ) {
			$group_modified = null;
		}
		if ( ! strtotime( $group_modified_gmt ) || $group_modified_gmt === "0000-00-00 00:00:00" ) {
			$group_modified_gmt = null;
		}

		$group_args = [
			'post_id'           => $group_id,
			'ID'                => $group_id,
			'post_author'       => $user_data,
			'post_date'         => $group_date,
			'post_date_gmt'     => $group_date_gmt,
			'post_title'        => $this->prepare_text_content( $group->name ),
			'post_excerpt'      => $this->prepare_text_content( $group->description ),
			'post_content'      => $this->prepare_text_content( $group->description ),
			'post_status'       => 'publish',
			'post_name'         => $this->prepare_text_content( $group->name ),
			'post_modified'     => $group_modified,
			'post_modified_gmt' => $group_modified_gmt,
			'post_parent'       => 0,
			'post_type'         => 'group',
			'post_mime_type'    => '',
			'permalink'         => bp_get_group_permalink( $group_id ),
			'terms'             => [],
			'post_meta'         => [],
			'date_terms'        => [],
			'comment_count'     => $comment_count,
			'comment_status'    => $comment_status,
			'ping_status'       => $ping_status,
			'menu_order'        => $menu_order,
			'guid'              => bp_get_group_permalink( $group_id ),
		];

		return $group_args;
	}

	/**
	 * Bulk index all groups.
	 * The equivalent functionality for posts in EP_API is spread out over several functions.
	 * This is a "condensed" adapted version that handles all required preparation as well as indexing.
	 */
	public function bulk_index_groups() {
		$groups = [];

		$querystring = bp_ajax_querystring( 'groups' ) . '&' . http_build_query( [
			'per_page' => 1,
		] );

		if ( bp_has_groups( $querystring ) ) {
			while ( bp_groups() ) {
				bp_the_group();
				$group_args = $this->prepare_group( bp_get_group_id() );
				$groups[ bp_get_group_id() ][] = '{ "index": { "_id": "' . bp_get_group_id() . '" } }';
				$groups[ bp_get_group_id() ][] = addcslashes( wp_json_encode( $group_args ), "\n" );
			}
		}
		var_dump( $groups );

		$flatten = [];

		foreach ( $groups as $group ) {
			$flatten[] = $group[0];
			$flatten[] = $group[1];
		}

		// make sure to add a new line at the end or the request will fail
		$body = rtrim( implode( "\n", $flatten ) ) . "\n";

		$path = trailingslashit( ep_get_index_name() ) . 'group/_bulk';

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
	 * Bulk index all members.
	 * See also bulk_index_groups()
	 */
	public function bulk_index_members() {
		$members = [];
		var_dump( func_get_args() ); die;

		$querystring = bp_ajax_querystring( 'groups' ) . '&' . http_build_query( [
			'per_page' => 1,
		] );

		if ( bp_has_groups( $querystring ) ) {
			while ( bp_groups() ) {
				bp_the_group();
				$group_args = $this->prepare_group( bp_get_group_id() );
				$groups[ bp_get_group_id() ][] = '{ "index": { "_id": "' . bp_get_group_id() . '" } }';
				$groups[ bp_get_group_id() ][] = addcslashes( wp_json_encode( $group_args ), "\n" );
			}
		}
		var_dump( $groups );

		$flatten = [];

		foreach ( $groups as $group ) {
			$flatten[] = $group[0];
			$flatten[] = $group[1];
		}

		// make sure to add a new line at the end or the request will fail
		$body = rtrim( implode( "\n", $flatten ) ) . "\n";

		$path = trailingslashit( ep_get_index_name() ) . 'group/_bulk';

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
	 * Ripped straight from EP_API.
	 *
	 * @param  string $content
	 * @return string
	 */
	private function prepare_text_content( $content ) {
		$content = strip_tags( $content );
		$content = preg_replace( '#[\n\r]+#s', ' ', $content );

		return $content;
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
