<?php
/**
 * Plugin Name: ElasticPress BuddyPress
 * Version: alpha
 * Description: ElasticPress custom feature to support BuddyPress content.
 * Text Domain: elasticpress-buddypress
 */

require_once dirname( __FILE__ ) . '/classes/class-ep-bp-api.php';
require_once dirname( __FILE__ ) . '/features/buddypress/buddypress.php';

require_once dirname( __FILE__ ) . '/bin/wp-cli.php';

add_action( 'plugins_loaded', 'ep_bp_register_feature' );
