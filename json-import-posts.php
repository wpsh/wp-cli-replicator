<?php

global $wpdb;

$json_file = $_SERVER['WP_JSON_FILE'];

if ( ! file_exists( $json_file ) ) {
	die( 'JSON file does not exist.' );
}

include __DIR__ . '/class-wp-json-importer.php';
$importer = new WP_Json_Importer( $wpdb );
$importer->import_post( json_decode( file_get_contents( $json_file ) ) );
