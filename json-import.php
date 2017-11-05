<?php

global $wpdb;

$json_dir = rtrim( $_SERVER['WP_JSON_DIR'], '/' );

include __DIR__ . '/class-wp-json-importer.php';
$importer = new WP_Json_Importer( $wpdb );

$users = json_decode( file_get_contents( $json_dir .'/users.json' ) );
$importer->import_users( $users );

$option_json = json_decode( file_get_contents( $json_dir . '/options.json' ) );
$importer->import_options( $option_json );

$terms = json_decode( file_get_contents( $json_dir . '/terms.json' ) );
$importer->import_terms( $terms );
