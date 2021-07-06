<?php
    /*
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    */
    
    /*
     *  Part One 
     *  These files need to be included as this script resides out of WordPress
     *  and are needed to handle media attachments uploads (featured image/gallery)
     *  TODO: Make this a plugin
     */
    define('WP_USE_THEMES', false);
    require( '/home/xxx/public_html/wp-blog-header.php' );
    require( '/home/xxx/public_html/wp-admin/includes/media.php' );
    require( '/home/xxx/public_html/wp-admin/includes/file.php' );
    require( '/home/xxx/public_html/wp-admin/includes/image.php' );

    

    /*
     *  Part Two 
     *  Three functions:
     *  Generate_Featured_Image() - for grabbing an imahe from a URL and inserting into a specific post
     *  curlGet() - grab a webpage and put into a variable. cURL has better (obfuscation) options than file_get_contents - like proxy rotation ;)
     *  http://www.jacobward.co.uk/using-proxies-for-scraping-with-php-curl/
     *  returnXPathObject() - load a webpage into DOMXML so we can start extracting using xpath
     */

    /**
     * Generate_Featured_Image()
     *
     * Grabs an image from a URL and loads it into the media library and assigns
     * it to the relevant post so when we delete a pet later, it also removes 
     * the pets images, otherwise we'll fill up disk space.
     * 
     * This was the trickiest part as the image URLs that were used are dynamically
     * generated from a script and resultant image filenames were the query string from the URL
     * 
     * Inspired from:
     * http://wordpress.stackexchange.com/questions/40301/how-do-i-set-a-featured-image-thumbnail-by-image-url-when-using-wp-insert-post
     *
     * @param int  $image_url    Full URL to image (http/s)
     * @param int  $postid       Post ID to attach to
     * @param bool $set_thumb    Optional. Whether to set image as post thumbnail.
     *                           Default true.
     * @return int $attach_id    So we can generate an array for our ACF gellery field
     */
    function Generate_Featured_Image( $image_url, $post_id, $set_thumb = true ) {
        
        $upload_dir = wp_upload_dir();
        $image_data = file_get_contents($image_url);
        $filename = basename($image_url);
        parse_str($filename, $get_array);
        $oldfile = $upload_dir['path'] . '/' . $filename;
        $newfile = $upload_dir['path'] . '/' . $get_array['imageId'] . '.jpg';
        $filename = basename($newfile);
        
        file_put_contents($oldfile, $image_data);
        rename( $oldfile, $newfile );

        $wp_filetype = wp_check_filetype($filename, null );
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attach_id = wp_insert_attachment( $attachment, $newfile, $post_id );
        
        if ( $set_thumb ) {
            $res2= set_post_thumbnail( $post_id, $attach_id );
        }

        $attach_data = wp_generate_attachment_metadata( $attach_id, $newfile );
        $update_attach_metadata = wp_update_attachment_metadata( $attach_id, $attach_data );
        
        return $attach_id;

    }

    // https://github.com/saulwiggin/albright/blob/master/WebScrape/3-xpath-scraping.php
    // https://gist.github.com/luckyshot/5395600

	function curlGet($url) {
        
		$user_agent = [
			'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13',
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36',
			'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:41.0) Gecko/20100101 Firefox/41.0',
			'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.101 Safari/537.36',
			'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.80 Safari/537.36',
			'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.71 Safari/537.36',
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11) AppleWebKit/601.1.56 (KHTML, like Gecko) Version/9.0 Safari/601.1.56',
			'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.80 Safari/537.36',
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit/601.2.7 (KHTML, like Gecko) Version/9.0.1 Safari/601.2.7',
			'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0) like Gecko',
			'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; AS; rv:11.0) like Gecko',
			'Mozilla/5.0 (compatible, MSIE 11, Windows NT 6.3; Trident/7.0; rv:11.0) like Gecko',
			'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/5.0)',
		];
	
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
		curl_setopt($ch, CURLOPT_TIMEOUT, 120);
		curl_setopt($ch, CURLOPT_USERAGENT, $user_agent[ array_rand($user_agent, 1) ]);
		curl_setopt($ch, CURLOPT_URL, $url);
		$results = curl_exec($ch);
		curl_close($ch);
		return $results;
	}

   
	function returnXPathObject($item) {
		$xmlPageDom = new DomDocument();
		@$xmlPageDom->loadHTML($item);
		$xmlPageXPath = new DOMXPath($xmlPageDom);
       
		return $xmlPageXPath;
	}
    /*
    function err( $script_error = 'Undefined error' ) {
        
        // It's magic...
        // __LINE__
        // __FUNCTION__

        $sep = " - ";
        
        $body = "There has been an error in the Web Scraper\r\n";
        $body .= date('r') . $sep . __FUNCTION__ . $sep . __LINE__ . $sep . $script_error . "\r\n";
        
        wp_mail( 'info@tring-web-design.co.uk', 'Scraper Error', $body );
        
        echo $body;

        //die();
        
    }
    */
    
    /*
     *  Part Three 
     *  Grab our start URL from website.org. THis would be the 'homepage' 
     *  for the branch which lists the pets available
     *  it extracts links to each individual pet details page
     */

    $test_mode = '0';

    // Array to hold our individual pets in
    $pets = array();

    if ( $test_mode ) {
        $seedUrl = './test.html';
        $parseUrl = file_get_contents( $seedUrl );
    } else {
        $seedUrl = 'https://www.website.org.uk/local/bedfordshire-south-branch/';
        $parseUrl = curlGet( $seedUrl );
    }

    //if (!$parseUrl) { err( 'Failed to get root URL' ); } 
    
    $startUrls = returnXPathObject($parseUrl);

    //if (!$startUrls) { err( 'Failed to parse root URL' ); } 

    // Extract URLs for each pet
	$urls = $startUrls->query('//*[@class="entryContainer"]//a/@href');

    //if (!$urls) { err( 'Failed to extract start URL, check xpath query' ); } 

    // Array to hold our individual pets URLs
    $urlArray = array();
    $ref_numbers = array();
    
    foreach($urls as $node){
        $urlArray[] = "https://www.website.org.uk" . trim($node->nodeValue);
    }

    // Get array of current log (ref) numbers from our animal posts
    $query_scrub_animals = get_posts(array(
        'numberposts'	=> -1,
        'post_type'		=> 'animals',
        'post_status'		=> 'publish',
    ));

    $existing_ids = array();
    
    foreach ( $query_scrub_animals as $scrub_list ) {
        
        $existing_ids[] = get_field('ref', $scrub_list->ID);

    }


    /*
     *  Part Four 
     *  Loop to extract content from each page and put into $pets[]
     */

    $counter = 0;

    foreach($urlArray as $urls) {

        $petUrl = curlGet($urls);
        $petDetails = returnXPathObject($petUrl);

        // Array to hold our ref numbers so we can scrub later
        

        // Get our ref first, if it already exists we can stop this iteration
        // e.g. save bandwidth by not downloading images
        $aboutMe = $petDetails->query('(//table)[1]//tr/td');
        //if (!$aboutMe) { err( 'Failed to extract about me table, check xpath query' ); } 

        foreach($aboutMe as $value) {
            $pets[$counter]['about'][] = trim( preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $value->textContent) );
        }

        $ref_numbers[] = $pets[$counter]['about'][3];

        // Get our pet name - the only H1 on the page
        $petName = $petDetails->query('//h1');
        //if (!$petName) { err( 'Failed to extract pet name, check xpath query' ); } 

        if ($petName->length > 0) {
            $pets[$counter]['name'] = ucfirst(strtolower(trim($petName->item(0)->nodeValue)));
        }

        // Our description, under the image
        $description = $petDetails->query("//*[contains(concat(' ', @class, ' '), ' petDescription ')]");
        //if (!$description) { err( 'Failed to extract description, check xpath query' ); }

        if ($description->length > 0) {
            
            $haystack = trim($description->item(0)->nodeValue);
            $needle = '.';
            $replace = '</p>';
            
            $pos = strpos($haystack, $needle);
            if ($pos !== false) {
                $newcontent = substr_replace($haystack, $replace, $pos, strlen($needle));
            }
            
            $pets[$counter]['description'] = '<p>' . $newcontent;
        }

        // The image(s), carousel'd if there are more than one image
        $images = $petDetails->query('//img/@src[contains(., "large")]');
        //if (!$images) { err( 'Failed to extract images, check xpath query' ); }

        if ($images->length > 0) {
            
            $pets[$counter]['images'] = "https://www.website.org.uk" . trim($images->item(0)->nodeValue);
            
        } else {
            
            $images = $petDetails->query('//img/@src[contains(., "small")]');
            //if (!$images) { err( 'Failed to extract carousel images, check xpath query' ); }

            foreach($images as $imagesSrc) {
                $pets[$counter]['gallery'][] = "https://www.website.org.uk" . str_replace("small", "large", $imagesSrc->nodeValue);
            }
            
            $pets[$counter]['images'] = $pets[$counter]['gallery'][0];

        }

        // Generated 'lifestyle' points under the description
        $lifeStyle = $petDetails->query('//div[@id="lifeStyle"]//li//span');
        //if (!$images) { err( 'Failed to extract lifeStyle, check xpath query' ); }

        foreach($lifeStyle as $lifeStylevalue) {
            $pets[$counter]['lifestyle'][] = trim($lifeStylevalue->textContent);
        }


        if (in_array($pets[$counter]['about'][3], $existing_ids)) {
            $counter++;
            continue;
        } else {

            /*
             *  Part Five 
             *  Start our wp_insert_post()
             *  - Skip pets that already exist in our CPT
             *  - Remove pets that no longer exist in the feed
             *  - Add pets that don't already exist in our CPT updating any ACF fields
             * 
             *  Essentially we trashed all pets in WP above, then re-add them from the feed below
             *  You wouldn't want to do this for 100s of pets, rather check if exists
             * 
             */

            // Create a new post
            $my_post = array(
                'post_title'	=> $pets[$counter]['name'],
                'post_type'		=> 'animals',
                'post_status'	=> 'publish',
                'post_content'	=> $pets[$counter]['description'],
            );

            // insert the post into the database to get a $post_id so...
            $post_id = wp_insert_post( $my_post );

            // ...we can update our ACFs by the $post_id

            // save breed
            update_field( 'field_58beaf0c62ea5', $pets[$counter]['about'][0], $post_id );

            // save colour
            update_field( 'field_58beaf1262ea6', $pets[$counter]['about'][1], $post_id );

            // save age
            update_field( 'field_58beaf1762ea7', $value = $pets[$counter]['about'][2], $post_id );

            // save ref (RSPCA Log Number)
            update_field( 'field_58beaede62ea4', $pets[$counter]['about'][3], $post_id );

            // save lifestyle
            update_field( 'field_58beafcbde7e8', $pets[$counter]['lifestyle'], $post_id );

            // save featured Image
            Generate_Featured_Image( $pets[$counter]['images'], $post_id );

            // save gallery images, if available
            if ( is_array( $pets[$counter]['gallery'] ) ) {

                $add_gallery_images = array();

                foreach( $pets[$counter]['gallery'] as $gal_images ) {
                    $add_gallery_images[] = Generate_Featured_Image( $gal_images, $post_id, false );
                }
                
                update_field( 'field_58beac1573601', $add_gallery_images , $post_id );
                
            }
            
            echo $pets[$counter]['about'][3] . ' - Added';
            
        $counter++;
        }
        

    } // End Part Four
    
    /*
     * SCRUB 
     * Make an array of pets currently in the site, 
     * and scrub these against each ref on website.org
     * set custom post status existing IDs that ref 
     * dont match
     * 
     */
     

    $ref_diff = array_diff($existing_ids, $ref_numbers);

    if ( !empty($ref_diff) ) {

        foreach ($ref_diff as $remove_ref) {

            $query_animals2 = get_posts(array(
                'numberposts'	=> -1,
                'post_type'		=> 'animals',
                'post_status'   => 'publish',
                'meta_key'		=> 'ref',
                'meta_value'	=> $remove_ref
            ));
            var_dump($query_animals2);
            echo $remove_ref;
            echo $query_animals2[0]->ID;
            $set_adopted_post = array(
                'ID'           => $query_animals2[0]->ID,
                'post_status'   => 'adopted',
            );

            wp_update_post( $set_adopted_post );

            //$post_thumbnail_id = get_post_thumbnail_id( $scrub_list->ID );
            //wp_delete_attachment( $post_thumbnail_id, TRUE );
            //wp_delete_post( $scrub_list->ID, TRUE );

        }

    }
    
    $body_success = "Found the following log numbers: " . implode(PHP_EOL, $ref_numbers) . "\n\n Removed the following: " . implode(PHP_EOL, $ref_diff);

    wp_mail( 'info@domain.co.uk', 'Scraper Success', $body_success );

?>
