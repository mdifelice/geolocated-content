<?php
/**
 * Helper functions.
 *
 * @param Geolocation
 */

/**
 * Returns the rewrite slug for location archive page.
 *
 * @return string The location archive page rewrite slug.
 */ 
function geolocation_get_rewrite_slug() {
	$rewrite_slug = get_option( 'geolocation_rewrite_slug' );

	if ( ! $rewrite_slug ) {
		$rewrite_slug = __( 'location', 'geolocation' );
	}

	return $rewrite_slug;
}

/**
 * Returns the list of allowed location IDs for the current user.
 *
 * @return array List of allowed locations IDs.
 */ 
function geolocation_get_current_user_allowed_location_ids() {
	$allowed_locations = false;
	$current_user_id   = get_current_user_id();

	if ( $current_user_id ) {
		$allowed_locations = geolocation_user_allowed_location_ids( $current_user_id );
	}

	return $allowed_locations;
}

/**
 * Returns the list of allowed location IDs for a particular user.
 *
 * @param int $user_id The user ID.
 *
 * @return array List of allowed locations IDs.
 */ 
function geolocation_get_user_allowed_locations( $user_id ) {
	return get_user_meta( $user_id, 'geolocation_allowed_locations', true );
}

/**
 * Determine whether the visitor is navigating the administrator.
 *
 * @return boolean It returns TRUE if the visitor is navigating the
 *                 administrator.
 */
function geolocation_is_admin() {
	$is_admin = false;

	if ( is_admin() ) {
		/**
		 * Ajax calls returns TRUE for the function is_admin(), so if this is
		 * the case, we check the referer. It only will be considered an
		 * administrator request when an Ajax call has a referer which contains
		 * '/wp-admin/'. In those cases, it means that the Ajax call was
		 * originated for some backend script.
		 */
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
				$referer = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );

				if ( false !== strpos( $_SERVER['HTTP_REFERER'], '/wp-admin/' ) ) {
					$is_admin = true;
				}
			}
		} else {
			$is_admin = true;
		}
	}

	return $is_admin;
}

/**
 * Returns the list of locations.
 *
 * @param boolean $include_default_location Optional. Whether to include the
 *                                          default location. Default is TRUE.
 * @return array List of locations.
 */
function geolocation_get_locations( $include_default_location = true ) {
	$locations = get_option( 'geolocation_locations', array() );

	if ( ! $include_default_location ) {
		$default_location_id = geolocation_get_default_location_id();

		if ( $default_location_id && isset( $locations[ $default_location_id ] ) ) {
			unset( $locations[ $default_location_id ] );
		}
	}

	return $locations;
}

/**
 * Returns a location object.
 *
 * @param int $location_id The ID of the location.
 *
 * @return WP_Term A term object representing the location or NULL if it does
 *                 not exist a location with such ID.
 */
function geolocation_get_location( $location_id ) {
	$location  = null;
	$locations = geolocation_get_locations();

	if ( isset( $locations[ $location_id ] ) ) {
		$location = $locations[ $location_id ];
	}

	return $location;
}

/**
 * Returns the default location (if there is one).
 *
 * @return WP_Term The term representing the global market. NULL if it does not
 *                 exist.
 */
function geolocation_get_default_location() {
	$default_location_id = geolocation_get_default_location_id();
	$default_location    = null;

	if ( $default_location_id ) {
		$default_location = geolocation_get_location( $default_location_id );
	}

	return $default_location;
}

/**
 * Returns the current default location identifier.
 *
 * @return int The location ID or NULL if it is not set.
 */
function geolocation_get_default_location_id() {
	return get_option( 'geolocation_default_location_id', null );
}

/**
 * Updates the locations cache.
 */
function geolocation_clean_locations_cache() {
	$current_locations = geolocation_get_locations();

	$terms = get_terms(
		'location',
		array(
			'hide_empty' => false,
		)
	);

	$new_locations = array_combine(
		wp_list_pluck( $terms, 'term_id' ),
		$terms
	);

	if ( $current_locations !== $new_locations ) {
		update_option( 'geolocation_locations', $new_locations );
	}
}

/**
 * Determines if a URL must not be prefixed with the location.
 *
 * @param string $url The URL to be checked.
 *
 * @return boolean TRUE if the URL must not be prefixed with the location.
 */
function geolocation_exclude_url( $url ) {
	$path = wp_parse_url( $url, PHP_URL_PATH );

	/**
	 * Basically we do not want to prefix the path if they correspond with a
	 * administrator page or a login/register page.
	 */
	return preg_match( '/^\/(?:wp-(?:[^\.]+\.php|admin\/))/', $path );
}

/**
  * Extracts the location slug from a request path. It extracts the first string
  * it finds before any slash, for example from '/some/url' it will extract the
  * string 'some' and it will check if that extracted location is a valid one.
  *
  * @param string $path The path to be checked.
  */
function geolocation_extract_location_slug( $path ) {
	$locations          = geolocation_get_locations( false );
	$extracted_location = null;

	if ( preg_match( '/^\/([^\/\?]+)/', $path, $matches ) ) {
		foreach ( $locations as $location ) {
			if ( $location === $matches[1] ) {
				$extracted_location = $location->slug;

				break;
			}
		}
	}

	return $extracted_location;
}

/**
 * Returns the visitor location slug extracted from the URL. In the case of an
 * Ajax call, since there is not market in the URL, it extracts it from the
 * referer.
 *
 * @return string The location slug, NULL if it cannot be found.
 */
function geolocation_get_visitor_location_slug() {
	$location_slug = null;
	$path           = null;

	if ( is_admin() ) {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
				$url = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );

				$path = wp_parse_url( $url, PHP_URL_PATH );
			}
		}
	} else {
		$path = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
	}

	if ( $path ) {
		$location_slug = geolocation_extract_location_slug( $path );
	}

	return $location_slug;
}

/**
 * Adds the location to an URL.
 *
 * @param string $url URL to be modified.
 *
 * @return string The modified URL.
 */
function geolocation_add_location_to_url( $url ) {
	$user_location = null;

	/**
	 * We do the conversion only if it is a public URL and the visitor is
	 * navigating a location which is not the default one.
	 */
	if ( ! geolocation_exclude_url( $url ) ) {
		( $user_location = geolocation_get_user_location() ) ) {
		$parsed_url = parse_url( $url );

		$url = 	( ! empty( $parsed_url['host'] ) ?
					( ! empty( $parsed_url['scheme'] ) ?
						$parsed_url['scheme'] . ':' : '' ) . '//' .
					( ! empty( $parsed_url['username'] ) ?
						$parsed_url['username'] .
						( ! empty( $parsed_url['password'] ) ?
							':' . $parsed_url['username'] : '' ) . '@'
						: '' ) .
					( ! empty( $parsed_url['host'] ) ?
						$parsed_url['host'] : '' ) : '' ) .
				'/' . $user_location .
				( ! empty( $parsed_url['path'] ) ?
					$parsed_url['path'] : '' ) .
				( ! empty( $parsed_url['query'] ) ?
				  	'?' . $parsed_url['query'] : '' ) .
				( ! empty( $parsed_url['fragment'] ) ?
				  	'#' . $parsed_url['fragment'] : '' );
	}

	/**
	  * For a suggestion made by the WordPress VIP team, we must remove
	  * the trailing slash from the URL if we are returning 'home_url'.
	  * For the rest of the hooks, we keep it.
	  *
	  * We also do not want to untrail the slash when the
	  * template_redirect plugin is being fired because it will throw a
	  * notice in wp-includes/canonical.php due to the missing "path". But
	  * we do that only if no market is selected because it could led to
	  * redirection loop.
	  */
	if ( 'home_url' !== current_filter() || ( ! $user_location && doing_action( 'template_redirect' ) ) )
		$url = trailingslashit( $url );
	else
		$url = untrailingslashit( $url );

	return $url;
}
