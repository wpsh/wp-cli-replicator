<?php
/**
 * Plugin Name: WP Replicator
 */

namespace WPSH_Replicator;

use WP_CLI;

if ( ! defined( 'WP_CLI' ) ) {
	return;
}

require_once( __DIR__ . '/vendor/autoload.php' );

WP_CLI::add_command( 'replicator', ReplicatorCommand::class );
