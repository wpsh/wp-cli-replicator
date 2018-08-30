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
	 * ## OPTIONS
	 *
	 * <path>
	 * : Path to the directory containing XML files.
	 *
	 * @subcommand wxr-to-json
	 */
	public function wxr_to_json( $args, $assoc_args ) {
		list( $xml_dir ) = $args;

		$xml_dir = rtrim( $xml_dir, '/' );
		$files = glob( $xml_dir . '/*.xml' );

		if ( empty( $files ) ) {
			return $this->error( sprintf(
				'No XML files found in %s.',
				$xml_dir
			) );
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
	 * ## OPTIONS
	 *
	 * <path>
	 * : Path to the options.json file.
	 *
	 * @subcommand import-options
	 */
	public function import_options( $args, $assoc_args ) {
		global $wpdb;

		list( $options_file ) = $args;

		$importer = new JsonImporter( $wpdb );

		return $importer->import_options( $this->from_json_file( $options_file ) );
	}

	/**
	 * Import users.
	 *
	 * ## OPTIONS
	 *
	 * <path>
	 * : Path to the users.json file.
	 *
	 * @subcommand import-users
	 */
	public function import_users( $args, $assoc_args ) {
		global $wpdb;

		list( $users_file ) = $args;

		$importer = new JsonImporter( $wpdb );

		return $importer->import_users( $this->from_json_file( $users_file ) );
	}

	/**
	 * Import terms.
	 *
	 * ## OPTIONS
	 *
	 * <path>
	 * : Path to terms.json file.
	 *
	 * @subcommand import-terms
	 */
	public function import_terms( $args, $assoc_args ) {
		global $wpdb;

		list( $terms_file ) = $args;

		$importer = new JsonImporter( $wpdb );

		return $importer->import_terms( $this->from_json_file( $terms_file ) );
	}

	/**
	 * Import posts.
	 *
	 * ## OPTIONS
	 *
	 * <path>
	 * : Path to the directory with the posts-*.json files.
	 *
	 * @subcommand import-posts
	 */
	public function import_posts( $args, $assoc_args ) {
		global $wpdb;

		list( $posts_dir ) = $args;

		$files = glob( rtrim( $posts_dir, '/' ) . '/posts-*.json' );

		if ( empty( $files ) ) {
			return $this->error( sprintf(
				'Failed to find post json files at %s.',
				$posts_dir
			) );
		}

		$importer = new JsonImporter( $wpdb );

		foreach ( $files as $file ) {
			$importer->import_post( $this->from_json_file( $file ) );
		}
	}

	protected function from_json_file( $filename ) {
		if ( ! file_exists( $filename ) ) {
			return $this->error( sprintf(
				'File %s not found.',
				$filename
			) );
		}

		return json_decode( file_get_contents( $filename ) );
	}

	protected function to_json_file( $filename, $data ) {
		return file_put_contents( $filename, json_encode( $data ) );
	}

	protected function error( $message ) {
		return WP_CLI::error( $message );
	}

}
