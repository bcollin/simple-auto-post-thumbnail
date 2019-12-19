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
 * This is a fork of Auto Post Thumbnail.
 */

add_action('publish_post', 'sapt_publish_post');
// This hook will now handle all sort publishing including posts, custom types, scheduled posts, etc.
add_action('transition_post_status', 'sapt_check_required_transition', 10, 3);
add_action('admin_notices', 'sapt_check_perms');
add_action('wp_ajax_generatepostthumbnail', 'sapt_ajax_process_post'); // Hook to implement AJAX request

/**
 * Process single post to generate the post thumbnail
 *
 * @return void
 */
function sapt_ajax_process_post() {
    if ( !current_user_can( 'manage_options' ) ) {
        die('-1');
    }

    $id = (int) $_POST['id'];

    if ( empty($id) ) {
        die('-1');
    }

    set_time_limit( 60 );

    // Pass on the id to our 'publish' callback function.
    sapt_publish_post($id);

    die(-1);
} //End sapt_ajax_process_post()

/**
 * Check whether the required directory structure is available so that the plugin can create thumbnails if needed.
 * If not, don't allow plugin activation.
 */
function sapt_check_perms() {
    $uploads = wp_upload_dir(current_time('mysql'));

    if ($uploads['error']) {
        echo '<div class="updated"><p>';
        echo $uploads['error'];

        if ( function_exists('deactivate_plugins') ) {
            deactivate_plugins('auto-post-thumbnail/auto-post-thumbnail.php', 'auto-post-thumbnail.php' );
            echo '<br /> This plugin has been automatically deactivated.';
        }

        echo '</p></div>';
    }
}

/**
 * Function to check whether scheduled post is being published. If so, sapt_publish_post should be called.
 *
 * @param $new_status
 * @param $old_status
 * @param $post
 * @return void
 */
function sapt_check_required_transition($new_status='', $old_status='', $post='') {

    if ('publish' == $new_status) {
        sapt_publish_post($post->ID);
    }
}

/**
 * Function to save first image in post as post thumbmail.
 */
function sapt_publish_post($post_id)
{
    global $wpdb;

    // First check whether Post Thumbnail is already set for this post.
    if (get_post_meta($post_id, '_thumbnail_id', true) || get_post_meta($post_id, 'skip_post_thumb', true)) {
        return;
    }

    $post = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE id = $post_id");

    // Initialize variable used to store list of matched images as per provided regular expression
    $matches = array();

    // Get all images from post's body
    preg_match_all('/<\s*img [^\>]*src\s*=\s*[\""\']?([^\""\'>]*)/i', $post[0]->post_content, $matches);

    if (count($matches)) {
        foreach ($matches[0] as $key => $image) {
            /**
             * If the image is from wordpress's own media gallery, then it appends the thumbmail id to a css class.
             * Look for this id in the IMG tag.
             */
            preg_match('/wp-image-([\d]*)/i', $image, $thumb_id);
            if($thumb_id){
                $thumb_id = $thumb_id[1];
            }

            // If thumb id is not found, try to look for the image in DB. Thanks to "Erwin Vrolijk" for providing this code.
            if (!$thumb_id) {
                $image = substr($image, strpos($image, '"')+1);
                $result = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE guid = '".$image."'");
                if($result){
                    $thumb_id = $result[0]->ID;
                }
                
            }

            // Ok. Still no id found. Some other way used to insert the image in post. Now we must fetch the image from URL and do the needful.
            if (!$thumb_id) {
                $thumb_id = sapt_generate_post_thumb($matches, $key, $post[0]->post_content, $post_id);
            }

            // If we succeed in generating thumg, let's update post meta
            if ($thumb_id) {
                update_post_meta( $post_id, '_thumbnail_id', $thumb_id );
                break;
            }
        }
    }
}// end sapt_publish_post()

/**
 * Function to fetch the image from URL and generate the required thumbnails
 */
function sapt_generate_post_thumb($matches, $key, $post_content, $post_id)
{
    // Make sure to assign correct title to the image. Extract it from img tag
    $imageTitle = '';
    preg_match_all('/<\s*img [^\>]*title\s*=\s*[\""\']?([^\""\'>]*)/i', $post_content, $matchesTitle);

    if (count($matchesTitle) && isset($matchesTitle[1])) {
        $imageTitle = $matchesTitle[1][$key];
    }

    // Get the URL now for further processing
    $imageUrl = $matches[1][$key];

    // Get the file name
    $filename = substr($imageUrl, (strrpos($imageUrl, '/'))+1);

    if (!(($uploads = wp_upload_dir(current_time('mysql')) ) && false === $uploads['error'])) {
        return null;
    }

    // Generate unique file name
    $filename = wp_unique_filename( $uploads['path'], $filename );

    // Move the file to the uploads dir
    $new_file = $uploads['path'] . "/$filename";

    if (!ini_get('allow_url_fopen')) {
        $file_data = curl_get_file_contents($imageUrl);
    } else {
        $file_data = @file_get_contents($imageUrl);
    }

    if (!$file_data) {
        return null;
    }

    //Fix for checking file extensions
    $exts = explode(".",$filename);
	if(count($exts)>2)return null;
	$allowed=get_allowed_mime_types();
	$ext=pathinfo($new_file,PATHINFO_EXTENSION);
	if(!array_key_exists($ext,$allowed))return null;

    file_put_contents($new_file, $file_data);

    // Set correct file permissions
    $stat = stat( dirname( $new_file ));
    $perms = $stat['mode'] & 0000666;
    @ chmod( $new_file, $perms );

    // Get the file type. Must to use it as a post thumbnail.
    $wp_filetype = wp_check_filetype( $filename, $mimes );

    extract( $wp_filetype );

    // No file type! No point to proceed further
    if ( ( !$type || !$ext ) && !current_user_can( 'unfiltered_upload' ) ) {
        return null;
    }

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

    $thumb_id = wp_insert_attachment($attachment, $file, $post_id);
    if ( !is_wp_error($thumb_id) ) {
        require_once(ABSPATH . '/wp-admin/includes/image.php');

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
function curl_get_file_contents($URL) {
    $c = curl_init();
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_URL, $URL);
    $contents = curl_exec($c);
    curl_close($c);

    if ($contents) {
        return $contents;
    }

    return FALSE;
}