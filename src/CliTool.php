<?php

namespace WPSH_Replicator;

use WP_CLI;

/**
 * Defines generic command with helpers.
 */
class CliTool {

	public function error( $message ) {
		return WP_CLI::error( $message );
	}

	public function log( $message ) {
		return WP_CLI::log( $message );
	}

	public function debug( $message ) {
		return WP_CLI::debug( $message );
	}

	public function warn( $message ) {
		return WP_CLI::warning( $message );
	}

	public function success( $message ) {
		return WP_CLI::success( $message );
	}

}
