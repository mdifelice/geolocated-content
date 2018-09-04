<?php
/**
 * Site-wide related functions.
 *
 * @package Geolocation
 */

/**
 * It uses a low priority in order to associate the location taxonomy with all
 * the possible registered custom post types.
 */
add_action( 'init', function() {
	load_theme_textdomain( 'geolocation', __DIR__ . '/languages' );

	$labels = array(
		'name'              => __( 'Locations', 'geolocation' ),
		'singular_name'     => __( 'Location', 'geolocation' ),
		'search_items'      => __( 'Search Locations', 'geolocation' ),
		'all_items'         => __( 'All Locations', 'geolocation' ),
		'parent_item'       => __( 'Parent Location', 'geolocation' ),
		'parent_item_colon' => __( 'Parent Location:', 'geolocation' ),
		'edit_item'         => __( 'Edit Location', 'geolocation' ),
		'update_item'       => __( 'Update Location', 'geolocation' ),
		'add_new_item'      => __( 'Add New Location', 'geolocation' ),
		'new_item_name'     => __( 'New Location Name', 'geolocation' ),
		'menu_name'         => __( 'Locations', 'geolocation' ),
	);

	$args = array(
		'labels'            => $labels,
		'hierarchical'      => true,
		'public'			=> true,
		'show_admin_column'	=> true,
		'rewrite'			=> array(
			'slug' => geolocation_get_rewrite_slug(),
		),
		'capabilities'      => array(
			'manage_terms' => 'manage_options',
			'edit_terms'   => 'manage_options',
			'delete_terms' => 'manage_options',
			'assign_terms' => 'edit_posts',
		),
	);

	/**
	 * We register the taxonomy for each custom post type and we add the 'post'
	 * post type to that list.
	 *
	 * Pages are not included here because usually they are not part of
	 * archives, but they can be included by using the filter.
	 */
	$post_types = get_post_types(
		array(
			'_builtin' => false,
		),
	);

	$post_types[] = 'post';

	$post_types = apply_filters( 'geolocation_post_types', $post_types );

	register_taxonomy( 'location', $post_types, $args );
}, 999 );

add_action( 'pre_get_posts', function( $query ) {
	$post_type	= $query->get( 'post_type' );
	$taxonomies	= get_object_taxonomies( $post_type );

	/**
	 * We don't want to execute this filter on XML-RPC nor WP_CLI requests.
	 * We neither want to execute on the location archive page (only for
	 * the main query), because that page is already executing filtering the
	 * content.
	 */
	$skip = false;

	if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
		$skip = true;
	} else if ( defined( 'WP_CLI' ) && WP_CLI ) {
		$skip = true;
	} else if ( ! in_array( 'location', $taxonomies ) ) {
		$skip = true;
	}

	if ( ! $skip ) {
		if ( is_tax( 'location' ) && $query->is_main_query() ) {
			/**
			 * Since the location taxonomy is associated with several post
			 * type, in the location archive main query we want post post types.
			 */
			$query->set( 'post_type', 'post' );
		} else {
			$is_admin         = geolocation_is_admin();
			$locations        = array();
			$operator         = 'IN';
			$locations_not_in = $query->get( 'location__not_in' );
			$locations_in 	  = $query->get( 'location__in' );

			/**
			 * If locations are explicity set in the query we will use them.
			 * Locations not included have precedence over locations included.
			 */
			if ( $locations_not_in || $locations_in ) {
				if ( $locations_not_in ) {
					$location_slugs = $locations_not_in;
					$operator       = 'NOT IN';
				} else {
					$location_slugs = $locations_not_in;
				}

				$location_slugs = explode( ',', $location_slugs );

				foreach ( $location_slugs as $location_slug ) {
					$location = get_term_by( 'slug', $location_slug, 'location' );

					if ( $location ) {
						$locations[] = $location->term_id;
					}
				}
			} else {
				if ( $is_admin ) {
					/**
					 * Here, we are in the BACKEND. We will apply this filter to
					 * all users except administrators (that's why we check if
					 * they have the 'manage_options' capability).
					 *
					 * We simply extract the allowed locations from the user
					 * attribute and assign them to the $locations array. If
					 * that attribute is empty, it means that the user can work
					 * with any locations.
					 */
					if ( ! current_user_can( 'manage_options' ) ) {
						$allowed_locations = geolocation_get_current_user_allowed_locations();

						if ( ! empty( $allowed_locations ) ) {
							$locations = $allowed_locations;
						}
					}
				} else {
					/**
					 * For FRONTEND we define the $locations array (this array
					 * will be filled with the locations that must be included
					 * in the query) along with the default market (if there is
					 * one) and with the location that the visitor is navigating
					 * (extracted from the URL).
					 */
					$location_slug       = geolocation_get_visitor_location_slug();
					$default_location_id = geolocation_get_default_location_id();

					if ( $default_location_id ) {
						$locations[] = $default_location_id;
					}

					if ( ! empty( $location_slug ) ) {
						$location = get_term_by( 'slug', $location_slug, 'location' );

						if ( $location ) {
							$locations[] = $location->term_id;
						}
					}
				}
			}

			/**
			 * We apply a filter to $location, in case some script want to
			 * include (or exclude) other locations in the query.
			 */
			$locations = apply_filters( 'geolocation_locations_in', $locations, $query, $operator );

			if ( ! empty( $locations ) ) {
				/**
				 * In order to avoid overwriting current tax queries, we check
				 * if there are any before adding our new one.
				 */
				$current_tax_query = $query->get( 'tax_query' );

				if ( ! $current_tax_query ) {
					$current_tax_query = array();
				}

				$current_tax_query[] = array(
					'taxonomy' => 'location',
					'field'    => 'term_id',
					'terms'    => $locations,
					'operator' => $operator,
				);

				$query->set( 'tax_query', $current_tax_query );
			}
		}
	}
} );
