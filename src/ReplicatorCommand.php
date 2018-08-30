<?php

namespace WPSH_Replicator;

use WP_CLI;
use WP_CLI_Command;

/**
 * Defines the Replicator WP CLI command.
 */
class ReplicatorCommand extends WP_CLI_Command {

	/**
	 * Define our importer instance.
	 *
	 * @var JsonImporter
	 */
	protected $importer;

	/**
	 * Init the command.
	 */
	public function __construct() {
		global $wpdb;

		$this->importer = new JsonImporter( $wpdb );
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

		$fresh = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'fresh' );

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

		$users_filename = sprintf( '%s/users.json', $json_dir );
		$terms_filename = sprintf( '%s/terms.json', $json_dir );

		// Parse all terms out of one file.
		if ( ! file_exists( $users_filename ) || $fresh ) {
			$users = $parser->parse_users( $files[0] );
			$this->to_json_file( $users_filename, $users );

			$this->success( sprintf(
				'Parsed %d users to %s.',
				count( $users ),
				$users_filename
			) );
		} else {
			$this->warn( sprintf(
				'Skip parsing users because %s exists.',
				$users_filename
			) );
		}

		// Parse all users out of one file.
		if ( ! file_exists( $terms_filename ) || $fresh ) {
			$terms = $parser->parse_terms( $files[0] );
			$this->to_json_file( $terms_filename, $terms );

			$this->success( sprintf(
				'Parsed terms to %s.',
				$terms_filename
			) );
		} else {
			$this->warn( sprintf(
				'Skip parsing terms because %s exists.',
				$terms_filename
			) );
		}

		foreach ( $files as $file ) {
			$posts_filename = sprintf( '%s/posts-%s.json', $json_dir, basename( $file, '.xml' ) );

			if ( ! file_exists( $posts_filename ) || $fresh ) {
				$posts = $parser->parse( $file );
				$this->to_json_file( $posts_filename, $posts );

				$this->success( sprintf(
					'Parsed posts from %s to %s.',
					dirname( $file ),
					$posts_filename
				) );
			} else {
				$this->warn( sprintf(
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

		return $this->importer->import_options( $this->from_json_file( $options_file ) );
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

		return $this->importer->import_users( $this->from_json_file( $users_file ) );
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

		return $this->importer->import_terms( $this->from_json_file( $terms_file ) );
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
			return $this->error( sprintf(
				'Failed to find post json files at %s.',
				$posts_dir
			) );
		}

		foreach ( $files as $file ) {
			$this->log( sprintf(
				'Start importing posts from %s',
				$file
			) );

			$this->importer->import_post( $this->from_json_file( $file ) );

			$this->success( sprintf(
				'Finished importing posts from %s',
				$file
			) );
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

	protected function log( $message ) {
		return WP_CLI::log( $message );
	}

	protected function warn( $message ) {
		return WP_CLI::warning( $message );
	}

	protected function success( $message ) {
		return WP_CLI::success( $message );
	}

}
