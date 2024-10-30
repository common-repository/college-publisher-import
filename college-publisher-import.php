<?php
/**
 * Plugin Name: College publisher Import
 * Plugin URI: https://github.com/istattic/college-publisher-import
 * Description: Import articles from CSV file into wordpress
 * Author: Vinay Yeddula
 * Version: 0.1
 * Author URI: 
 * License: GPL2
 */

/* Copyright 2014 Vinay Yeddula  (email: vyeddula@unomaha.edu / vinaykumarreddy.y@gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


// Add Import option in admin menu
function cp_csv_importer() {
	add_menu_page( 'CSV File Importer', 'CP Import', 1, 'cp_csv_import', 'cp_csv_admin' );
}

// callback function that executes on clicking Import button from menu
function cp_csv_admin() {
	// Function that does actual stuff for us
	import_admin();
}
add_action( 'admin_menu', 'cp_csv_importer' );
global $cp_csv_import_msgs;


function import_admin() {
	// Do import if we have 'Y' in the post variables
	if( 'Y' == $_POST['cp_imp_hidden'] ) {
		do_import();
	}
	
	// Show import UI
	show_import_ui();
	
	// Show messages if we hve any
	show_msgs();
}

function do_import() {
	// Change PHP settings
	ini_set( "auto_detect_line_endings", true );
	set_time_limit(0);
	
	// move the uploaded file to permanent location
	$file_path = save_uploaded_file();
	
	// Open file in read mode
	$handler = fopen( $file_path, 'r' );
	if ( ! $handler ) {
		throw_error( 'Failed to read file "' . $file_path . '". Make sure all permissions are correct.' );
	}
	
	$header = true;
	$body_data = array();
	$header_data = array();
	$header_data_count = 0;
	$post_count = 0;
	
	$time_start = microtime( true );
	
	// Read one line at a time and save it to database.
	while ( false !== ( $buffer = fgets( $handler ) ) ) {
		if( ! $header ) {
			$row = preg_replace( "/(\²)/", "", $buffer );
			$body_data = array_map( "trim", explode( '|||', $row ) );
			
			// Check if CSV if formed correctly
			if( $header_data_count != count( $body_data ) ) {
				throw_error( 'Malformed CSV File. Headers count does not match with body content.' );
			}
			$post = array_combine( $header_data, $body_data );
			
			// Save the post/articles
			if ( save_post( $post ) ) {
				$post_count++;
			} else {
				$msg = 'Failed to import post with id ' . $post['article id'] . '<br>';
				add_msg( $msg );
			}
		} else {
			$filtered = preg_replace( "/(\<br \/>)/", "", $buffer );
			$row = preg_replace( "/(\²)/", "", $filtered );
			$header_data = array_filter( array_map( "trim", explode( '|||', $row ) ) );
			$header_data_count = count( $header_data );
			$header = false;
		}
	}
	
	// Close the file handler
	fclose( $handler );
	
	$time_end = microtime( true );
	$time = $time_end - $time_start;
	
	// We are done!
	add_msg( '<b>Imported '. $post_count .' articles in ' . $time . ' seconds.</b><br>' );
}

function save_uploaded_file() {
	// get the wordpress upload directory path.
	$upload_dir = wp_upload_dir();
	
	if ( ! empty( $_FILES['csvfile'] ) ) {
		$myFile = $_FILES['csvfile'];
		if ( UPLOAD_ERR_OK !== $myFile['error'] ) {
			throw_error( '<p>Failed to upload file. Please try again.</p>' );
		}
		
		// ensure a safe filename
		$name = preg_replace( "/[^A-Z0-9._-]/i", "_", $myFile['name'] );
		
		$parts = pathinfo( $name );
		$name = $parts['filename'] . "-" . date( "Ymd-His" ) . "." . $parts['extension'];
		
		$dir_path = $upload_dir['basedir'] . '/cp_imported_csv';
		$file_path = $dir_path . '/' . $name;
		
		// Create a directory if we do not have one to store uploaded file
		if ( ! file_exists( $dir_path ) ) {
			if( ! mkdir( $dir_path, 0777, true ) ) {
				$msg = '<p>Failed to create upload folder "'. $dir_path .'". Make sure all permissions are correct.</p>';
				throw_error( $msg );
			}
		}
		
		// preserve file from temporary directory
		$success = move_uploaded_file( $myFile["tmp_name"], $file_path );
		if ( ! $success ) {
			$msg = '<p>Failed to save file in "' . $dir_path . '/". Make sure all permissions are correct.</p>';
			throw_error( $msg );
		}
		
		return $file_path;
	} else {
		throw_error( '<p>Failed to upload file. Please try again.</p>' );
	}
}

function save_post( $post ) {
	$wp_post = array();
	
	$wp_post['post_content'] = $post['Body text'];  // The full text of the post.
	if( ! empty( $post['Byline'] ) ) {
		$by_line = '<h3>By ' . $post['Byline'];
		
		if( ! empty( $post['second byline'] )) {
			$by_line .= ', '. $post['second byline'];
		}
		
		$by_line .= '</h3><br>';
		$wp_post['post_content'] = $by_line . $wp_post['post_content'];
	}
	
	$wp_post['post_title'] = $post['Title'];  // The title of your post.
	$wp_post['post_status'] = 'publish';  // [ 'draft' | 'publish' | 'pending'| 'future' | 'private' | custom registered status ] - Default 'draft'.
	$wp_post['post_excerpt'] = $post['Summary'];  // For all your post excerpt needs.
	$wp_post['post_date'] = date( "Y-m-d H:i:s", strtotime( $post['created date'] ) );  // The time post was made.
	
	// Save categories - wp_create_categories will return category Id, if it already exists
	$categories = array_filter( explode( ':', $post['articleCategory'] ) );
	$wp_post['post_category'] = wp_create_categories($categories);  // [ array(<category id>, ...) ] - Default empty.
	
	//$wp_post['post_name'] = $post[''];  // The name (slug) for your post
	//$wp_post['post_type'] = $post[''];  // [ 'post' | 'page' | 'link' | 'nav_menu_item' | custom post type ] - Default 'post'.
	//$wp_post['post_author'] = $post[''];  // The user ID number of the author. Default is the current user ID.
	//$wp_post['ping_status'] = $post[''];  // Pingbacks or trackbacks allowed. Default is the option 'default_ping_status'.
	//$wp_post['post_parent'] = $post[''];  // [ <post ID> ] - Sets the parent of the new post, if any. Default 0.
	//$wp_post['menu_order'] = $post[''];  // If new post is a page, sets the order in which it should appear in supported menus. Default 0.
	//$wp_post['to_ping'] = $post[''];  // Space or carriage return-separated list of URLs to ping. Default empty string.
	//$wp_post['pinged'] = $post[''];  // Space or carriage return-separated list of URLs that have been pinged. Default empty string.
	//$wp_post['post_password'] = $post[''];  // Password for post, if any. Default empty string.
	//$wp_post['post_date_gmt'] = $post[''];  // The time post was made, in GMT.
	//$wp_post['comment_status'] = $post[''];  // Default is the option 'default_comment_status', or 'closed'.
	//$wp_post['tags_input'] = $post[''];  // [ '<tag>, <tag>, ...' | array ] - Default empty.
	//$wp_post['tax_input'] = $post[''];  // [ array( <taxonomy> => <array | string> ) ] - For custom taxonomies. Default empty.
	//$wp_post['page_template'] = $post[''];  // Default empty.
	
	
	// Insert post
	$post_id = wp_insert_post( $wp_post );
	
	// Insert attachments
	if( ! empty( $post['html Element'] ) ) {
		insert_attachment( $post['html Element'], $post_id, $post['article id'], true );
	}
	
	if( ! empty( $post['#ImageId:filename:title:caption:copyright'] ) ) {
		insert_attachment( $post['#ImageId:filename:title:caption:copyright'], $post_id, $post['article id'] );
	}
	
	for( $i=2; $i<=9; $i++ ) {
		$key = '#ImageId'.$i.':filename'.$i.':title'.$i.':caption'.$i.':copyright'.$i;
		if( ! empty( $post[$key] ) ) {
			insert_attachment( $post[$key], $post_id, $post['article id'] );
		}
	}
	
	if( ! empty( $post['#ImageId10:filename01:title10:caption10:copyright10'] ) ) {
		insert_attachment( $post['#ImageId10:filename01:title10:caption10:copyright10'], $post_id, $post['article id'] );
	}
	
	return true;
}

function insert_attachment( $data, $parent_post_id, $article_id, $thumbnail = false ) {
	$header_data = array( '#ImageId', 'filename', 'title', 'caption', 'copyright' );
	$header_data_count = count( $header_data );
	$body_data = explode( ':', $data );
	
	if( $header_data_count != count( $body_data ) ) {
		add_msg( 'Malformed attachment data for article with id ' . $article_id .'<br>');
		return;
	}
	
	$image_data = array_combine( $header_data, $body_data );
	
	$file_name = substr( trim( $image_data['#ImageId'] ), 3 ). '-'. trim( $image_data['filename'] );
	
	$fileType = wp_check_filetype( basename($file_name), null );
	$wpUploadDir = wp_upload_dir();
	
	$attachment = array(
			'guid' => $wpUploadDir['url'] . '/' . basename( $file_name ),
			'post_mime_type' => $fileType['type'],
			'post_title' => trim( $image_data['title'] ),
			'post_content' => trim( $image_data['caption'] ),
			'post_status' => 'inherit'
	);
	
	$abs_path = $wpUploadDir['path'] . '/' . $file_name;
	$attachment_id = wp_insert_attachment( $attachment, $abs_path, $parent_post_id );
	
	// you must first include the image.php file
	// for the function wp_generate_attachment_metadata() to work
	require_once( ABSPATH . 'wp-admin/includes/image.php' );
	$attachment_data = wp_generate_attachment_metadata( $attachment_id, $abs_path );
	wp_update_attachment_metadata( $attachment_id, $attachment_data );
	
	// Set Featured Image
	if( $thumbnail ) {
		set_post_thumbnail( $parent_post_id, $attachment_id );
	} else {
		add_post_meta( $parent_post_id, '_gw_csv_custom_image_id', $attachment_id );
	}
}

function show_import_ui() {
?>
	<div class="wrap">
		<?php echo "<h2>" . __( 'Import Articles', 'gwimp_trdom' ) . "</h2>"; ?>
		
		<p> <?php $wpUploadDir = wp_upload_dir();
				echo 'Please add your images to folder <b>"'. $wpUploadDir['path'] . '"</b> before importing CSV file.';
			?>
		</p>
		<form enctype="multipart/form-data" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>" method="post" name="upload">
			<input type="hidden" value="Y" name="cp_imp_hidden">
			<p>Choose file to upload... <input type="file" name="csvfile"> </p>
			
			<hr>
			<p class="submit">
				<input type="submit" value="Upload" class="button-primary" name="Submit">
			</p>
		</form>
	</div>
<?php } 

// Save all messages
function add_msg( $err_msg ) {
	$GLOBALS['cp_csv_import_msgs'] .= $err_msg;
}

// echo all messages to browser
function show_msgs() {
	echo $GLOBALS['cp_csv_import_msgs'];
}

// Throw error.
function throw_error( $error ) {
	show_import_ui();
	echo '<br><b>' . $error . '</b>';
	die();
}

?>