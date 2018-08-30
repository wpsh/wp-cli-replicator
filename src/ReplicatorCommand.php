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

	/**
	 * Import options.
	 *
	 * @subcommand import-options
	 */
	public function import_options( $args, $assoc_args ) {
		global $wpdb;

		if ( ! isset( $args[0] ) ) {
			die( 'Please specify path to the exported options.json.' );
		}

		$options_file = $args[0];

		if ( ! file_exists( $options_file ) ) {
			die( 'Specified options.json not found.' );
		}

		$importer = new JsonImporter( $wpdb );

		return $importer->import_options( $this->from_json_file( $options_file ) );
	}

	/**
	 * Import users.
	 *
	 * @subcommand import-users
	 */
	public function import_users( $args, $assoc_args ) {
		global $wpdb;

		if ( ! isset( $args[0] ) ) {
			die( 'Please specify path to the exported options.json.' );
		}

		$users_file = $args[0];

		if ( ! file_exists( $users_file ) ) {
			die( 'Specified users not found.' );
		}

		$importer = new JsonImporter( $wpdb );

		return $importer->import_users( $this->from_json_file( $users_file ) );
	}

	/**
	 * Import terms.
	 *
	 * @subcommand import-terms
	 */
	public function import_terms( $args, $assoc_args ) {
		global $wpdb;

		if ( ! isset( $args[0] ) ) {
			die( 'Please specify path to the exported options.json.' );
		}

		$terms_file = $args[0];

		if ( ! file_exists( $terms_file ) ) {
			die( 'Specified terms file not found.' );
		}

		$importer = new JsonImporter( $wpdb );

		return $importer->import_terms( $this->from_json_file( $terms_file ) );
	}

	/**
	 * Import posts.
	 *
	 * @subcommand import-posts
	 */
	public function import_posts( $args, $assoc_args ) {
		global $wpdb;

		if ( ! isset( $args[0] ) ) {
			die( 'Please specify path to posts.' );
		}

		$files = glob( rtrim( $args[0], '/' ) . '/posts-*.json' );

		$importer = new JsonImporter( $wpdb );

		foreach ( $files as $file ) {
			$importer->import_post( $this->from_json_file( $file ) );
		}
	}

	protected function from_json_file( $filename ) {
		if ( ! file_exists( $filename ) ) {
			die( 'File not found.' );
		}

		return json_decode( file_get_contents( $filename ) );
	}

	protected function to_json_file( $filename, $data ) {
		return file_put_contents( $filename, json_encode( $data ) );
	}

}
