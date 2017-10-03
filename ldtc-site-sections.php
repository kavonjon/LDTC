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
 * https://wordpress.stackexchange.com/questions/50077/display-a-custom-taxonomy-as-a-dropdown-on-the-edit-posts-page
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
        'meta_box_cb'       => 'restricted_site_sections_metabox',
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

/*
 * kavon hooshiar, 10/01/17:
 * Build a custom metabox for siteSections taxonomy that doesn't bring ticked boxes to the top, doesn't have a popular term tab, and restricts available terms to non admins
 * admin sees everything, editor sees restricted terms, author doesn't see the box
 * https://wordpress.stackexchange.com/questions/50077/display-a-custom-taxonomy-as-a-dropdown-on-the-edit-posts-page
 * https://stackoverflow.com/questions/4830913/wordpress-category-list-order-in-post-edit-page
 */



function restricted_site_sections_metabox( $post, $box ) {
	if (!current_user_can('level_10')) { // check for user capabilities - level_10 is admin
        
        if( !current_user_can('editor')) { // for non-administrator, only show checklist of terms for users with at least editor role
            return;
        }

		//get the terms that the user is assigned to 
		$user = wp_get_current_user();
		$assigned_terms = wp_get_object_terms( $user->ID, 'siteSection' );
        $assigned_term_ids = array();
        foreach( $assigned_terms as $term ) {
            $assigned_term_ids[] = $term->term_id;
        }
 
    }



    $defaults = array('taxonomy' => 'category');
    if ( !isset($box['args']) || !is_array($box['args']) )
        $args = array();
    else
        $args = $box['args'];
    extract( wp_parse_args($args, $defaults), EXTR_SKIP );
    $tax = get_taxonomy($taxonomy);

    ?>
    <div id="taxonomy-<?php echo $taxonomy; ?>" class="categorydiv">
        <ul id="<?php echo $taxonomy; ?>-tabs" class="category-tabs">
            <li class="tabs"><a href="#<?php echo $taxonomy; ?>-all" tabindex="3"><?php echo $tax->labels->all_items; ?></a></li>
        </ul>

        <div id="<?php echo $taxonomy; ?>-pop" class="tabs-panel" style="display: none;">
            <ul id="<?php echo $taxonomy; ?>checklist-pop" class="categorychecklist form-no-clear" >
                <?php $popular_ids = wp_popular_terms_checklist($taxonomy); ?>
            </ul>
        </div>

        <div id="<?php echo $taxonomy; ?>-all" class="tabs-panel">
            <?php
            $name = ( $taxonomy == 'category' ) ? 'post_category' : 'tax_input[' . $taxonomy . ']';
            echo "<input type='hidden' name='{$name}[]' value='0' />"; // Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks.
            ?>
            <ul id="<?php echo $taxonomy; ?>checklist" class="list:<?php echo $taxonomy?> categorychecklist form-no-clear">
                <?php 
                /**
                 * This is the one line we had to change in the original function
                 * Notice that "checked_ontop" is now set to FALSE
                 */
                if (!current_user_can('level_10')) { // check for user capabilities - level_10 is admin
                        // use wp_terms_checklist once for each term that the user is assigned to, with 'descendants_and_self' argument to limit the output to that term
                        foreach( $assigned_term_ids as $available_term ) {
                            wp_terms_checklist($post->ID, array( 'taxonomy' => $taxonomy, 'descendants_and_self' => $available_term, 'checked_ontop' => FALSE ) );
                        }
                } else {
                    wp_terms_checklist($post->ID, array( 'taxonomy' => $taxonomy, 'popular_cats' => $popular_ids, 'checked_ontop' => FALSE ) );
                } ?>
            </ul>
        </div>
    <?php if ( !current_user_can($tax->cap->assign_terms) ) : ?>
    <p><em><?php _e('You cannot modify this taxonomy.'); ?></em></p>
    <?php endif; ?>
    <?php if ( current_user_can($tax->cap->edit_terms) ) : ?>
            <div id="<?php echo $taxonomy; ?>-adder" class="wp-hidden-children">
                <h4>
                    <a id="<?php echo $taxonomy; ?>-add-toggle" href="#<?php echo $taxonomy; ?>-add" class="hide-if-no-js" tabindex="3">
                        <?php
                            /* translators: %s: add new taxonomy label */
                            printf( __( '+ %s' ), $tax->labels->add_new_item );
                        ?>
                    </a>
                </h4>
                <p id="<?php echo $taxonomy; ?>-add" class="category-add wp-hidden-child">
                    <label class="screen-reader-text" for="new<?php echo $taxonomy; ?>"><?php echo $tax->labels->add_new_item; ?></label>
                    <input type="text" name="new<?php echo $taxonomy; ?>" id="new<?php echo $taxonomy; ?>" class="form-required form-input-tip" value="<?php echo esc_attr( $tax->labels->new_item_name ); ?>" tabindex="3" aria-required="true"/>
                    <label class="screen-reader-text" for="new<?php echo $taxonomy; ?>_parent">
                        <?php echo $tax->labels->parent_item_colon; ?>
                    </label>
                    <?php wp_dropdown_categories( array( 'taxonomy' => $taxonomy, 'hide_empty' => 0, 'name' => 'new'.$taxonomy.'_parent', 'orderby' => 'name', 'hierarchical' => 1, 'show_option_none' => '&mdash; ' . $tax->labels->parent_item . ' &mdash;', 'tab_index' => 3 ) ); ?>
                    <input type="button" id="<?php echo $taxonomy; ?>-add-submit" class="add:<?php echo $taxonomy ?>checklist:<?php echo $taxonomy ?>-add button category-add-sumbit" value="<?php echo esc_attr( $tax->labels->add_new_item ); ?>" tabindex="3" />
                    <?php wp_nonce_field( 'add-'.$taxonomy, '_ajax_nonce-add-'.$taxonomy, false ); ?>
                    <span id="<?php echo $taxonomy; ?>-ajax-response"></span>
                </p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}


/**
 * Hide siteSections from quick edit if user does not have admin privileges
 * https://developer.wordpress.org/reference/hooks/quick_edit_show_taxonomy/
 */
function hide_tags_from_quick_edit( $show_in_quick_edit, $taxonomy_name, $post_type ) {
    if ( 'siteSection' === $taxonomy_name && !current_user_can('level_10') ) {
        return false;
    } else {
        return $show_in_quick_edit;
    }
}
add_filter( 'quick_edit_show_taxonomy', 'hide_tags_from_quick_edit', 10, 3 );



// add the siteSection taxonomy menu to the user menu
function add_user_siteSection_menu() {
    add_submenu_page( 'users.php' , 'Site Sections', 'Site Sections' , 'add_users',  'edit-tags.php?taxonomy=siteSection' );
}
add_action(  'admin_menu', 'add_user_siteSection_menu' );




//add sections to user profile pages
add_action( 'show_user_profile', 'show_user_siteSection' );
add_action( 'edit_user_profile', 'show_user_siteSection' );
function show_user_siteSection( $user ) {

    if ( !current_user_can('level_10') )
        return;
 
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

    if ( !current_user_can('level_10') )
        return;

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
 * restrict pages that display on edit.php and attachments on upload.php based on siteSection
 */


function edit_filter_get_posts($query) {
    global $pagenow;

    if ( current_user_can('level_10') )
        return;

    if( !$query->is_main_query() )
        return;

    if( 'edit.php' !== $pagenow && 'upload.php' !== $pagenow )
        return $query;

    $user = wp_get_current_user();
    $assigned_terms = wp_get_object_terms( $user->ID, 'siteSection' );
    $assigned_term_slugs = array();
    $add_language_page = FALSE;
    foreach( $assigned_terms as $term ) {
        $assigned_term_slugs[] = $term->slug;
        if( $term->parent == 'languages' ) {
            $add_language_page = TRUE;
        }
    }
    if( add_language_page ) {
        $assigned_term_slugs[] = 'languages';
    }

    $taxquery = array(
        array(
            'taxonomy' => 'siteSection',
            'field' => 'slug',
            'terms' => $assigned_term_slugs,
            'operator'=> 'IN',
            'include_children' => false
        )
    );


    if ( $query->is_admin ) {
        $query->set( 'tax_query', $taxquery );
    }
}
add_action( 'pre_get_posts', 'edit_filter_get_posts' );



/*
 * kavon hooshiar, 10/01/17:
 * assign site section term to media when it's attached to a page
 */
function add_category_automatically($post_ID) {
    $attach = get_post($post_ID);
    if ($attach->post_parent) {

        $languages_term = get_term_by( 'slug', 'languages', 'siteSection' );
        $languages_term_id = $languages_term->term_id;
        $languages_child_terms = get_term_children( $languages_term_id, 'siteSection' ); //output is ID's

        $page_terms = wp_get_object_terms( $attach->post_parent, 'siteSection' );
        $page_term_IDs = array();
        foreach( $page_terms as $page_term ) {
            $page_term_IDs[] = $page_term->term_id;
        }

        $page_terms_IDs_sublang = array_intersect( $page_term_IDs, $languages_child_terms );
        wp_set_object_terms($post_ID, $page_terms_IDs_sublang, 'siteSection', true);
    }
}
add_action('add_attachment', 'add_category_automatically');





/* kavon hooshiar, 9/22/17
 * Set Child Terms to Parent Terms for siteSection taxonomy
 * https://wordpress.stackexchange.com/questions/158522/is-there-a-way-to-make-child-posts-inherit-parent-post-terms
 * https://wordpress.stackexchange.com/questions/24582/how-can-i-get-only-parent-terms
 */
function set_parent_terms_siteSections( $post_id, $post ) {

    if ( $post->post_parent > 0 ) {
        if(!empty($ancs)){
            foreach ( $ancs as $anc ) {
                $terms = wp_get_post_terms( $anc, 'siteSection' );
                if ( !empty( $terms ) ) {

                    $languages_term = get_term_by( 'slug', 'languages', 'siteSection' );
                    $languages_term_id = $languages_term->term_id;
                    $languages_child_terms = get_term_children( $languages_term_id, 'siteSection' ); //output is ID's

                    $tmp = wp_remove_object_terms( $parent_post_ID, $languages_child_terms, 'siteSection' );

                    $termArr = array_map(create_function('$obj', 'return $obj->term_id;'), $terms);
                    $tmp = wp_set_object_terms( $post_id, $termArr, 'siteSection', true );
                }
            }
        }
    }
    if ( $post->post_name == 'languages') {
        wp_set_object_terms( $post_id, 'languages', 'siteSection', true );
    } else {
        wp_remove_object_terms( $post_id, 'languages', 'siteSection' );
    }
}
add_action( 'save_post', 'set_parent_terms_siteSections', 100, 2 );


/**
 * Remove editing privaledges from non-admins to pages that are not part of a siteSection that is also assigned to the user
 * this works in tandem with the hook into pre_get_hosts, so that page structure above a user's accessible pages
 * can be displayed in edit.php without granting them editing rights to those parent pages
 * https://wordpress.stackexchange.com/questions/191658/allowing-user-to-edit-only-certain-pages
 */

function ldtc_user_can_edit( $user_id, $page_id ) {


    if ( current_user_can('level_10') )
        return TRUE;

    $the_post = get_post( $page_id );
    if ( $user_id == $the_post->post_author )
        return TRUE;

    $page_terms = wp_get_object_terms( $page_id, 'siteSection' );
    $page_term_slugs = array();
    foreach( $page_terms as $term ) {
        $page_term_slugs[] = $term->slug;
    }


    $user = wp_get_current_user(); 
    $assigned_terms = wp_get_object_terms( $user->ID, 'siteSection' );
    foreach( $assigned_terms as $term ) {
        $assigned_term_slug = $term->slug;
        if ( in_array($assigned_term_slug, $page_term_slugs) ) {
            return TRUE;
        }
    }

    return FALSE;

}

add_filter( 'map_meta_cap', ldtc_restrict_editing, 10, 4 );

function ldtc_restrict_editing( $caps, $cap, $user_id, $args ) {

    $to_filter = [ 'edit_post', 'delete_post', 'edit_page', 'delete_page' ];

    // If the capability being filtered isn't of our interest, just return current value
    if ( ! in_array( $cap, $to_filter, true ) ) {
        return $caps;
    }

    // First item in $args array should be page ID
    if ( ! $args || empty( $args[0] ) || ! ldtc_user_can_edit( $user_id, $args[0] ) ) {
        // User is not allowed, let's tell that to WP
        return [ 'do_not_allow' ];
    }

    // Every user is allowed to exist.
    // Return this array, the check for capability will be true
    return [ 'exist' ];

}


/* kavon hooshiar, 9/30/17
 * Limit options in parent page dropdown menu
 * code from plugin: selective-parent-page-drop-down/parent_page_dropdown.php
 * https://wordpress.stackexchange.com/questions/26770/get-posts-assigned-to-a-specific-custom-taxonomy-term-and-not-the-terms-childr
 * https://stackoverflow.com/questions/14880043/how-to-hook-page-attributes-meta-box-to-change-displaying-parent-option - lists "no_parent" attribute
 */

add_filter( 'page_attributes_dropdown_pages_args', 'restrict_parent_page_menu' );
add_filter( 'quick_edit_dropdown_pages_args', 'restrict_parent_page_menu' );
    
function restrict_parent_page_menu( $args ) {  

    if ( current_user_can('level_10') )
        return $args;

    $available_pages = array();
    $user = wp_get_current_user(); 
    $assigned_terms = wp_get_object_terms( $user->ID, 'siteSection' );
    $assigned_term_ids = array();
    foreach( $assigned_terms as $term ) {
        $assigned_term_ids[] = $term->term_id;
        $term_pages = get_posts(array(
            'post_type' => 'page',
            'numberposts' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'siteSection',
                    'field' => 'id',
                    'terms' => $term->term_id, // for each term assigned to user
                    'include_children' => false
                )
            )
        ));
        $term_pages_ID = array();
        foreach( $term_pages as $term_page ) {
            $term_pages_ID[] = $term_page->ID;
        }
        $available_pages = array_merge( $available_pages, $term_pages_ID ) ;
    }

    $available_pages_and_parents = $available_pages;
    $parents_of_pages = array();
    foreach( $available_pages as $each_page ) {
        $parent_of_each_page = intval( wp_get_post_parent_id( $each_page ) );
        $parents_of_pages[] = $parent_of_each_page;
    }

    $available_pages_and_parents = array_merge( $available_pages_and_parents, $parents_of_pages ) ;

    $unique_available_pages_and_parents = array_unique ( $available_pages_and_parents );

    $all_pages = get_posts(array(
        'post_type' => 'page',
        'numberposts' => -1,
    ));
    $all_pages_ID = NULL;
    foreach( $all_pages as $all_page ) {
        $all_pages_ID[] = $all_page->ID;
    }

    $restricted_pages = array_diff($all_pages_ID, $unique_available_pages_and_parents);

    $args['exclude'] = $restricted_pages;

    $languages_term = get_term_by( 'slug', 'languages', 'siteSection' );
    $languages_term_id = $languages_term->term_id;
    $languages_child_terms = get_term_children( $languages_term_id, 'siteSection' ); //output is ID's
    $user_terms_IDs_notin_sublang = array_diff( $assigned_term_ids, $languages_child_terms );

    if ( $user_terms_IDs_notin_sublang == [] )
        $args['show_option_none'] = FALSE;

    return $args;  
} 