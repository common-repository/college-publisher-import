college-publisher-import
=========================

Import articles from CSV file into wordpress.



Please go through the attached example file to know how to build the CSV file.

CSV file contents explained below.
----------------------------------
Title - Title of your post.
Summary - Excerpt of your post.
Body text - Full text of the post.
Byline - Author name this post. eg: John
second byline - Designation of the author. eg: Reporter, Article Manager
created date - Date in string format
articleCategory - Category name to which this post belongs.
html Element - Thumbnail of the image that needs to be shown for this post in format #ImageId:filename:title:caption:copyright.
#ImageId:filename:title:caption:copyright - Format for attachments/images.
	eg: #1.2069634:1050472890.jpg:/stills/4361d20d4ea01-71-1.jpg:New housing for students:Scott Stewart (Your image file shoulld benamed as 2069634-1050472890.jpg)


Copy the following code into your theme (Single.php)
-----------------------------------------------------
$gw_custom_image_id = get_post_meta($post->ID, '_gw_csv_custom_image_id');
			foreach( $gw_custom_image_id as $image_id ) {
				$media_image_url = wp_get_attachment_url( $image_id, 'full' );
				
				$detail_image = aq_resize( $media_image_url, $media_width, $media_height, true, false);
				if ($detail_image) {
					$figure_output .= '<img src="'.$detail_image[0].'" width="'.$detail_image[1].'" height="'.$detail_image[2].'" />'."\n";
				}
			}
			
Here "$figure_output" is the variable that holds the HTML content of images.
