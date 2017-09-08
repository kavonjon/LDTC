<?php
   /*
   Plugin Name: LDTC Site Sections
   Plugin URI: http://ling.hawaii.edu/ldtc
   Description: creates a custom taxonomy to manage user access to different parts of the 
                site, so that each LDTC project can be handled separately, and users can 
                be granted to editing each separately.
   Version: 0.1
   Author: Kavon Hooshiar
   Author URI: http://ling.hawaii.edu/ldtc
   License: GPL2
   */

/*
 * kavon hooshiar, 09/16/16:
 * Create taxonomy to define sections of the site, "siteSections," applied to pages, media and users
 */
function wptp_register_siteSection_tax() {
 /* register the Site Sections taxonomy */
register_taxonomy( 'siteSection', array('attachment','page','user'),
    array(
        'labels' =>  array(
            'name'              => 'Site Sections',
            'singular_name'     => 'Site Section',
            'search_items'      => 'Search Site Sections',
            'all_items'         => 'All Site Sections',
	    'parent_item'       => 'Parent Site Section',
            'parent_item_colon' => 'Parent Site Section:',
            'edit_item'         => 'Edit Site Sections',
            'update_item'       => 'Update Site Section',
            'add_new_item'      => 'Add New Site Section',
            'new_item_name'     => 'New Site Section Name',
            'menu_name'         => 'Site Sections',
        ),
        'hierarchical'      => true,
        'show_ui'           => true,
        'how_in_nav_menus'  => true,
        'public'            => true,
        'sort'              => true,
        'query_var'         => true,
        'show_admin_column' => true,
        'rewrite'           => array('slug' => 'siteSection')
    )
);
}
add_action( 'init', 'wptp_register_siteSection_tax', 0 );



// add the siteSection taxonomy menu to the user menu
function add_user_siteSection_menu() {
    add_submenu_page( 'users.php' , 'Site Sections', 'Site Sections' , 'add_users',  'edit-tags.php?taxonomy=siteSection' );
}
add_action(  'admin_menu', 'add_user_siteSection_menu' );




//add sections to user profile pages
add_action( 'show_user_profile', 'show_user_siteSection' );
add_action( 'edit_user_profile', 'show_user_siteSection' );
function show_user_siteSection( $user ) {
 
    //get the terms that the user is assigned to 
    $assigned_terms = wp_get_object_terms( $user->ID, 'siteSection' );
    $assigned_term_ids = array();
    foreach( $assigned_terms as $term ) {
        $assigned_term_ids[] = $term->term_id;
    }
 
    //get all the terms we have
    $user_cats = get_terms( 'siteSection', array('hide_empty'=>false) );
 
    echo "<h3>Site Section</h3>";

     //list the terms as checkbox, make sure the assigned terms are checked
    foreach( $user_cats as $cat ) { ?>
        <input type="checkbox" id="siteSection-<?php echo $cat->term_id ?>" <?php if(in_array( $cat->term_id, $assigned_term_ids )) echo 'checked=checked';?> name="siteSection[]"  value="<?php echo $cat->term_id;?>"/> 
        <?php
    	echo '<label for="siteSection-'.$cat->term_id.'">'.$cat->name.'</label>';
    	echo '<br />';
    }
}




//save changes to sections on user pages
add_action( 'personal_options_update', 'save_user_siteSection' );
add_action( 'edit_user_profile_update', 'save_user_siteSection' );
function save_user_siteSection( $user_id ) {

	$user_terms = $_POST['siteSection'];
	$terms = array_unique( array_map( 'intval', $user_terms ) );
	wp_set_object_terms( $user_id, $terms, 'siteSection', false );

	//make sure you clear the term cache
	clean_object_term_cache($user_id, 'siteSection');
}



add_filter( 'body_class', 'siteSection_body_class', 10, 3 );

if( !function_exists( 'siteSection_body_class' ) ) {

    function siteSection_body_class( $classes, $class, $ID ) {

        $taxonomy = 'siteSection';

        $terms = get_the_terms( (int) $ID, $taxonomy );

        if( !empty( $terms ) ) {

            foreach( (array) $terms as $order => $term ) {

                if( !in_array( $term->slug, $classes ) ) {

                    $classes[] = 'siteSection-' . $term->slug;

                }

            }

        }

        return $classes;

    }

}



add_filter( 'post_class', 'siteSection_post_class', 10, 3 );

if( !function_exists( 'siteSection_post_class' ) ) {

    function siteSection_post_class( $classes, $class, $ID ) {

        $taxonomy = 'siteSection';

        $terms = get_the_terms( (int) $ID, $taxonomy );

        if( !empty( $terms ) ) {

            foreach( (array) $terms as $order => $term ) {

                if( !in_array( $term->slug, $classes ) ) {

                    $classes[] = 'siteSection-' . $term->slug . '-post';

                }

            }

        }

        return $classes;

    }

}





/*
 * kavon hooshiar, 09/02/17:
 * restrict siteSections available based on siteSection
 */

add_filter('get_terms', 'restrict_categories');
function restrict_categories($siteSection) {	

	// If we are in the new/edit post page and not an admin, then restrict the categories
	$onPostPage = (strpos($_SERVER['PHP_SELF'], 'post.php') || strpos($_SERVER['PHP_SELF'], 'post-new.php'));	
	if (is_admin() && $onPostPage && !current_user_can('level_10')) { // check for user capabilities - level_10 is admin
		//get the terms that the user is assigned to 
		$user = wp_get_current_user();
		$assigned_terms = wp_get_object_terms( $user->ID, 'siteSection' );

		$size = count($siteSection);
		for ($i = 0; $i < $size; $i++) {			
			if (!in_array( $siteSection[$i], $assigned_terms)) {
				 unset($siteSection[$i]);
			}
		}
	}
	return $siteSection;
}


function edit_filter_get_posts($query) {
    global $pagenow;

    if ( current_user_can('level_10') )
        return;

    if( !$query->is_main_query() )
        return;

    if( 'edit.php' !== $pagenow )
        return $query;

    $user = wp_get_current_user(); 
    $assigned_terms = wp_get_object_terms( $user->ID, 'siteSection' );
    $assigned_term_slugs = array();
    foreach( $assigned_terms as $term ) {
        $assigned_term_slugs[] = $term->slug;
    }

    $taxquery = array(
        array(
            'taxonomy' => 'siteSection',
            'field' => 'slug',
            'terms' => $assigned_term_slugs,
            'operator'=> 'IN'
        )
    );
    if ( $query->is_admin ) 
        $query->set( 'tax_query', $taxquery );

}
add_action( 'pre_get_posts', 'edit_filter_get_posts' );



/** Set Child Terms to Parent Terms for siteSection taxonomy**/
function set_parent_terms_siteSections( $post_id, $post ) {
    if ( 'publish' === $post->post_status && $post->post_parent > 0 ) {
        $ancTax = get_post_ancestors( $post_id );
        if(!empty($ancTax)){
            foreach ( $ancTax as $anc ) {
                $terms = wp_get_post_terms( $anc, 'siteSection' );
                if ( !empty( $terms ) ) {
                    $termArr = array_map(create_function('$obj', 'return $obj->term_id;'), $terms);
                    $tmp = wp_set_object_terms( $post_id, $termArr, 'siteSection', true );
                }
            }
        }
    }
}
add_action( 'save_post', 'set_parent_terms_siteSections', 100, 2 );



?>
