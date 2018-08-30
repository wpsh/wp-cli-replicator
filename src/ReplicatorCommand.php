<?php

namespace WPSH_Replicator;

use WP_CLI;
use WP_CLI_Command;

/**
 * Defines the Replicator WP CLI command.
 */
class ReplicatorCommand extends WP_CLI_Command {


	/**
	 * Convert WXR export XML files into JSON files.
	 *
	 * @subcommand wxr-to-json
	 */
	public function wxr_to_json( $args, $assoc_args ) {
		if ( ! isset( $args[0] ) ) {
			die( 'Please specify the path to WXR export' );
		}

		$xml_dir = rtrim( $args[0], '/' );
		$files = glob( $xml_dir . '/*.xml' );

		if ( empty( $files ) ) {
			die( 'No files found at ' . $xml_dir );
		}

		$json_dir = dirname( $xml_dir ) . '/json';

		if ( ! file_exists( $json_dir ) ) {
			mkdir( $json_dir );
		}

		$parser = new XmlToJson();

		// Parse all terms out of one file.
		$users = $parser->parse_users( $files[0] );
		$users_filename = sprintf( '%s/users.json', $json_dir );
		$this->to_json_file( $users_filename, $users );

		// Parse all users out of one file.
		$terms = $parser->parse_terms( $files[0] );
		$terms_filename = sprintf( '%s/terms.json', $json_dir );
		$this->to_json_file( $terms_filename, $terms );

		foreach ( $files as $file ) {
			$posts = $parser->parse( $file );
			$posts_filename = sprintf( '%s/posts-%s.json', $json_dir, basename( $file, '.xml' ) );
			$this->to_json_file( $posts_filename, $posts );
		}
	}

	protected function to_json_file( $filename, $data ) {
		return file_put_contents( $filename, json_encode( $data ) );
	}

}
