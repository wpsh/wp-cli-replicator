<?php

global $wpdb;

// @todo This should come from a CLI arg.
$json_dir = '../import/hb/json';

include __DIR__ . '/class-wp-json-importer.php';
$importer = new WP_Json_Importer( $wpdb );

// $users = json_decode( file_get_contents( $json_dir .'/users.json' ) );
// $importer->import_users( $users );

$option_json = json_decode( file_get_contents( $json_dir . '/options.json' ) );
$importer->import_options( $option_json );

$terms = json_decode( file_get_contents( $json_dir . '/terms.json' ) );
$importer->import_terms( $terms );

$post_files = glob( $json_dir . '/posts-*.json' );
$importer->import_posts( array_reverse( $post_files ) );
