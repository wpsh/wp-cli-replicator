<?php
/**
 * Main CLI command file.
 */

namespace WPSH_Replicator;

use WP_CLI;

if ( ! defined( 'WP_CLI' ) ) {
	return;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

WP_CLI::add_command( 'replicator', ReplicatorCommand::class );
