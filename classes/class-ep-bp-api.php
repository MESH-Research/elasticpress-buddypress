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
	public function prepare_group( $group ) {
		$groupmeta = groups_get_groupmeta( $group->id );

		$user = get_userdata( $group->creator_id );

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

		$args = [
			'post_id'           => $group->id,
			'ID'                => $group->id,
			'post_author'       => $user_data,
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
			'post_type'         => 'group',
			'post_mime_type'    => '',
			'permalink'         => bp_get_group_permalink( $group ),
			'terms'             => [],
			'post_meta'         => [],
			'date_terms'        => [],
			'comment_count'     => 0,
			'comment_status'    => 0,
			'ping_status'       => 0,
			'menu_order'        => 0,
			'guid'              => bp_get_group_permalink( $group ),
		];

		return $args;
	}

	/**
	 * Prepare a member for syncing
	 *
	 * @param int $group_id
	 * @return bool|array
	 */
	public function prepare_member( $user ) {
		$user_data = array(
			'raw'          => $user->user_login,
			'login'        => $user->user_login,
			'display_name' => $user->display_name,
			'id'           => $user->ID,
		);

		$permalink = trailingslashit( get_site_url() ) . 'members/' . $user->user_login;

		$args = [
			'post_id'           => $user->ID,
			'ID'                => $user->ID,
			'post_author'       => $user_data,
			'post_date'         => $user->user_registered,
			'post_date_gmt'     => $user->user_registered,
			'post_title'        => $this->prepare_text_content( $user->display_name ),
			'post_excerpt'      => $this->prepare_text_content( make_clickable( $permalink ) ),
			'post_content'      => null,
			'post_status'       => 'publish',
			'post_name'         => $this->prepare_text_content( $user->display_name ),
			'post_modified'     => null,
			'post_modified_gmt' => null,
			'post_parent'       => 0,
			'post_type'         => 'member',
			'post_mime_type'    => '',
			'permalink'         => $permalink,
			'terms'             => [],
			'post_meta'         => [],
			'date_terms'        => [],
			'comment_count'     => 0,
			'comment_status'    => 0,
			'ping_status'       => 0,
			'menu_order'        => 0,
			'guid'              => $permalink,
		];

		return $args;
	}

	/**
	 * Send a request to EP_API.
	 * Allows bulk_index_* functions to loop through objects and fire off successive requests of a reasonable size.
	 */
	private function send_request( $type, $objects ) {
		$flatten = [];

		foreach ( $objects as $object ) {
			$flatten[] = $object[0];
			$flatten[] = $object[1];
		}

		$path = trailingslashit( ep_get_index_name( bp_get_root_blog_id() ) ) . "$type/_bulk";

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
	 */
	public function bulk_index_groups( $args = [] ) {
		global $groups_template;

		$groups = [];

		$args = array_merge( [
			'per_page' => 350,
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

			$this->send_request( 'group', $groups );

			$this->bulk_index_groups( [
				'page' => $args['page'] + 1,
			] );
		}

		return true;
	}

	/**
	 * Bulk index all members.
	 * See also bulk_index_groups()
	 */
	public function bulk_index_members( $args = [] ) {
		global $members_template;

		$members = [];

		$args = array_merge( [
			'per_page' => 350,
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

			$this->send_request( 'member', $members );

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
