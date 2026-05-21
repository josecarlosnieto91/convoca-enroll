<?php
require_once 'wp-load.php';

$args  = array(
	'post_type'      => 'inscripcion',
	'posts_per_page' => 1,
	'post_status'    => 'any',
);
$posts = get_posts( $args );

if ( empty( $posts ) ) {
	echo "No inscriptions found.\n";
} else {
	$post = $posts[0];
	echo 'Post ID: ' . $post->ID . "\n";
	echo 'Post Title: ' . $post->post_title . "\n";
	echo 'Post Status: ' . $post->post_status . "\n";
	echo "Meta Data:\n";
	print_r( get_post_custom( $post->ID ) );
}
