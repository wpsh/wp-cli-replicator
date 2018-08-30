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

		file_put_contents(
			sprintf( '%s/users.json', $json_dir ),
			json_encode( $users )
		);

		// Parse all users out of one file.
		$terms = $parser->parse_terms( $files[0] );

		file_put_contents(
			sprintf( '%s/terms.json', $json_dir ),
			json_encode( $terms )
		);

		foreach ( $files as $file ) {
			$filename = sprintf( '%s/posts-%s.json', $json_dir, basename( $file, '.xml' ) );

			$data = $parser->parse( $file );

			file_put_contents(
				$filename,
				json_encode( $data )
			);
		}
	}

}
