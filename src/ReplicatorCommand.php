<?php

namespace WPSH_Replicator;

use WP_CLI_Command;
use WP_CLI\Utils;

/**
 * Defines the Replicator WP CLI command.
 */
class ReplicatorCommand extends WP_CLI_Command {

	/**
	 * @var JsonImporter Our importer tool.
	 */
	protected $importer;

	/**
	 * @var \CliTool Instance of generic CLI tools.
	 */
	protected $cli;

	/**
	 * Init the command.
	 */
	public function __construct() {
		/**
		 * @var \wpdb $wpdb
		 */
		global $wpdb;

		$this->importer = new JsonImporter( $wpdb );
		$this->cli = new CliTool();
	}

	/**
	 * Convert WXR export XML files into JSON files.
	 *
	 * ## OPTIONS
	 *
	 * <path>
	 * : Path to the directory containing XML files.
	 *
	 *
	 * [--fresh]
	 * : Override any existing files.
	 *
	 * @subcommand parse-wxr
	 */
	public function parse_wxr( $args, $assoc_args ) {
		list( $xml_dir ) = $args;

		$fresh = (bool) Utils\get_flag_value( $assoc_args, 'fresh' );

		$xml_dir = rtrim( $xml_dir, '/' );
		$files = glob( $xml_dir . '/*.xml' );

		if ( empty( $files ) ) {
			return $this->cli->error( sprintf(
				'No XML files found in %s.',
				$xml_dir
			) );
		}

		$json_dir = dirname( $xml_dir ) . '/json';

		if ( ! file_exists( $json_dir ) ) {
			mkdir( $json_dir );
		}

		$parser = new XmlToJson();

		$users_filename = sprintf( '%s/users.json', $json_dir );
		$terms_filename = sprintf( '%s/terms.json', $json_dir );

		// Parse all terms out of one file.
		if ( ! file_exists( $users_filename ) || $fresh ) {
			$users = $parser->parse_users( $files[0] );
			$this->to_json_file( $users_filename, $users );

			$this->cli->success( sprintf(
				'Parsed %d users to %s.',
				count( $users ),
				$users_filename
			) );
		} else {
			$this->cli->warn( sprintf(
				'Skip parsing users because %s exists.',
				$users_filename
			) );
		}

		// Parse all users out of one file.
		if ( ! file_exists( $terms_filename ) || $fresh ) {
			$terms = $parser->parse_terms( $files[0] );
			$this->to_json_file( $terms_filename, $terms );

			$this->cli->success( sprintf(
				'Parsed terms to %s.',
				$terms_filename
			) );
		} else {
			$this->cli->warn( sprintf(
				'Skip parsing terms because %s exists.',
				$terms_filename
			) );
		}

		foreach ( $files as $file ) {
			$posts_filename = sprintf( '%s/posts-%s.json', $json_dir, basename( $file, '.xml' ) );

			if ( ! file_exists( $posts_filename ) || $fresh ) {
				$posts = $parser->parse( $file );
				$this->to_json_file( $posts_filename, $posts );

				$this->cli->success( sprintf(
					'Parsed posts from %s to %s.',
					basename( $file ),
					$posts_filename
				) );
			} else {
				$this->cli->warn( sprintf(
					'Skip parsing posts because %s exists.',
					$posts_filename
				) );
			}
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
		list( $options_file ) = $args;

		$this->importer->import_options( $this->from_json_file( $options_file ) );

		// Options also contain the rewrite rules.
		flush_rewrite_rules();

		$this->cli->success( 'Options imported.' );
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
		list( $users_file ) = $args;

		$this->importer->import_users( $this->from_json_file( $users_file ) );

		$this->cli->success( 'Users imported.' );
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
		list( $terms_file ) = $args;

		$this->importer->import_terms( $this->from_json_file( $terms_file ) );

		$this->cli->success( 'Taxonomies and terms imported.' );
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
		list( $posts_dir ) = $args;

		$files = glob( rtrim( $posts_dir, '/' ) . '/posts-*.json' );

		if ( empty( $files ) ) {
			return $this->cli->error( sprintf(
				'Failed to find post json files at %s.',
				$posts_dir
			) );
		}

		foreach ( $files as $file ) {
			$this->cli->log( sprintf(
				'Start importing posts from %s',
				$file
			) );

			$this->importer->import_post( $this->from_json_file( $file ) );

			$this->cli->success( sprintf(
				'Finished importing posts from %s using %s of memory.',
				$file,
				size_format( memory_get_peak_usage() )
			) );

			Utils\wp_clear_object_cache();
		}

		$this->cli->success( 'All posts imported.' );
	}

	public function from_json_file( $filename ) {
		if ( ! file_exists( $filename ) ) {
			return $this->cli->error( sprintf(
				'File %s not found.',
				$filename
			) );
		}

		return json_decode( file_get_contents( $filename ) );
	}

	protected function to_json_file( $filename, $data ) {
		return file_put_contents( $filename, json_encode( $data ) );
	}

}
