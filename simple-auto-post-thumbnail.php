<?php

/*
Plugin Name: Simple Auto Post Thumbnail
Plugin URI: http://www.abeleto.nl
Description: Automatically generate the Post Thumbnail (Featured Thumbnail) from the first image in post (or any custom post type) but only if Post Thumbnail is not set manually.
Version: 0.1
Author: Branko Collin
Author URI: http://www.abeleto.nl
*/

/*  Copyright 2009  Aditya Mooley  (email: adityamooley@sanisoft.com)
    Copyright 2019  Branko Collin  (email: collin@xs4all.nl)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * This is a fork of Auto Post Thumbnail version 3.4.1.
 * This was the last version by Sanisoft.
 */

add_action( 'publish_post', 'sapt_publish_post' );
// This hook will now handle all sort publishing including posts, custom types, scheduled posts, etc.
add_action( 'transition_post_status', 'sapt_check_required_transition', 10, 3 );
add_action( 'admin_notices', 'sapt_check_perms' );

/**
 * Check whether the required directory structure is available so that the plugin can create thumbnails if needed.
 * If not, don't allow plugin activation.
 */
function sapt_check_perms() {
	$uploads = wp_upload_dir( current_time( 'mysql' ) );

	if ( $uploads['error'] ) {
		echo '<div class="updated"><p>';
		echo $uploads['error'];

		if ( function_exists( 'deactivate_plugins' ) ) {
			deactivate_plugins('simple-auto-post-thumbnail/simple-auto-post-thumbnail.php', 'simple-auto-post-thumbnail.php' );
			echo '<br /> This plugin has been automatically deactivated.';
		}

		echo '</p></div>';
	}
}

/**
 * Function to check whether scheduled post is being published. If so, sapt_publish_post should be called.
 *
 * @param (string) $new_status
 * @param (string) $old_status
 * @param (object) $post
 *
 * @return void
 */
function sapt_check_required_transition( $new_status = '', $old_status = '', $post = null ) {
	if ( 'publish' === $new_status && ! empty( $post->ID ) ) {
		sapt_publish_post( $post->ID );
	}
}

/**
 * Function to save first image in post as post thumbmail.
 */
function sapt_publish_post( $post_id ) {
	global $wpdb;

	// First check whether Post Thumbnail is already set for this post.
	if ( get_post_meta( $post_id, '_thumbnail_id', true ) || get_post_meta( $post_id, 'skip_post_thumb', true ) ) {
		return;
	}

	$post = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE id = $post_id" );

	// Initialize variable used to store list of matched images as per provided regular expression
	$matches = array();

	// Get all images from post's body
	preg_match_all( '/<\s*img [^\>]*src\s*=\s*[\""\']?([^\""\']*)[\""\']?[^\>]+\>/i', $post[0]->post_content, $matches );

	if ( ! empty( $matches ) ) {
		foreach ( $matches[0] as $key => $image_tag ) {
			$url = $matches[1][$key];
			
			//If the image is from Wordpress's own Media gallery, then it appends 
			// the thumbmail ID to a CSS class. Look for this ID in the IMG tag.
			preg_match( '/wp-image-([\d]*)/i', $image_tag, $id_matches );
			if ( $id_matches ) {
				$thumb_id = $id_matches[1];
			}

			// If thumb ID is not found, try to look for the image in DB. Thanks to 
			// Erwin Vrolijk for providing this code.
			if ( empty( $thumb_id ) ) {
				$result = $wpdb->get_results( "SELECT ID FROM {$wpdb->posts} WHERE guid = '" . $url . "'" );

				if ( $result ){
					$thumb_id = $result[0]->ID;
				}
			}

			// Ok. Still no ID found. Some other way used to insert the image in 
			// post. Now we must fetch the image from URL and do the needful.
			if ( empty( $thumb_id ) ) {
				$thumb_id = sapt_generate_post_thumb( $url, $image_tag, $post_id );
			}

			// If we succeeded in finding a thumb ID, let's update post meta
			if ( ! empty ( $thumb_id ) ) {
				update_post_meta( $post_id, '_thumbnail_id', $thumb_id );
				break;
			}
		}
	}
}// end sapt_publish_post()

/**
 * Function to fetch the image from URL and generate the required thumbnails
 * 
 * @param (string) $url       URL of the image.
 * @param (string) $img_tag   HTML img tag from the post's content.
 * @param (int)    $post_id   Post ID.
 * 
 * @return (void|string)      The ID of the thumbnail or null.
 */
function sapt_generate_post_thumb( $url, $img_tag, $post_id ) {
	// Make sure to assign correct title to the image. Extract it from img tag
	$imageTitle = '';
	preg_match_all( '/title\s*=\s*[\""\']?([^\""\'>]*)/i', $img_tag, $matchesTitle );

	if ( ! empty ( $matchesTitle ) && isset( $matchesTitle[1] ) ) {
		$imageTitle = $url;
	}

	// Get the file name
	$filename = substr($url, ( strrpos( $url, '/' ) ) + 1 );

	if ( ! ( ( $uploads = wp_upload_dir( current_time( 'mysql' ) ) ) && false === $uploads['error'] ) ) {
		return null;
	}

	// Generate unique file name
	$filename = wp_unique_filename( $uploads['path'], $filename );

	// Move the file to the uploads dir
	$new_file = $uploads['path'] . "/$filename";

	if ( ! ini_get( 'allow_url_fopen' ) ) {
		$file_data = sapt_curl_get_file_contents( $url );
	} else {
		$file_data = file_get_contents( $url );
	}

	if ( empty ( $file_data ) ) {
		return null;
	}

	//Fix for checking file extensions
	$exts = explode( ".", $filename );
	if ( count( $exts ) > 2 ) { 
		return null; 
	}
	$mimes = get_allowed_mime_types();
	$ext = pathinfo( $new_file, PATHINFO_EXTENSION );

	foreach ( $mimes as $ext_preg => $mime_match ) {
		$ext_preg = '!^(' . $ext_preg . ')$!i';
		if ( preg_match( $ext_preg, $ext ) ) {
			$allowed = true;
			break;
		}
	}

	if ( ! $allowed ) { 
		return null; 
	}

	file_put_contents( $new_file, $file_data );

	// Set correct file permissions
	$stat = stat( dirname( $new_file ) );
	$perms = $stat['mode'] & 0000666;
	chmod( $new_file, $perms );

	// Get the file type. Must to use it as a post thumbnail.
	$wp_filetype = wp_check_filetype( $filename, $mimes );
	
	// No file type! No point to proceed further
	if ( ( empty( $wp_filetype ) || empty( $wp_filetype ) ) && ! current_user_can( 'unfiltered_upload' ) ) {
		return null;
	}

	$type = $wp_filetype['type'];
	$ext = $wp_filetype['ext'];

	// Compute the URL
	$url = $uploads['url'] . "/$filename";

	// Construct the attachment array
	$attachment = array(
		'post_mime_type' => $type,
		'guid' => $url,
		'post_parent' => null,
		'post_title' => $imageTitle,
		'post_content' => '',
	);

	$thumb_id = wp_insert_attachment( $attachment, $file, $post_id );
	if ( ! is_wp_error( $thumb_id ) ) {
		require_once( ABSPATH . '/wp-admin/includes/image.php' );

		// Added fix by misthero as suggested
		wp_update_attachment_metadata( $thumb_id, wp_generate_attachment_metadata( $thumb_id, $new_file ) );
		update_attached_file( $thumb_id, $new_file );

		return $thumb_id;
	}

	return null;
}

/**
 * Function to fetch the contents of URL using curl in absense of allow_url_fopen.
 *
 * Copied from user comment on php.net (http://in.php.net/manual/en/function.file-get-contents.php#82255)
 */
function sapt_curl_get_file_contents( $url ) {
	$c = curl_init();
	curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $c, CURLOPT_URL, $url );
	$contents = curl_exec( $c ); // Returns the file contents or the boolean false
	curl_close( $c );

	return $contents;
}
