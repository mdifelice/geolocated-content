<?php
/**
 * Helper functions.
 *
 * @package Geolocation
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
	$allowed_location_ids = null;
	$current_user_id      = get_current_user_id();

	if ( $current_user_id ) {
		$allowed_location_ids = geolocation_get_user_allowed_location_ids( $current_user_id );
	}

	return $allowed_location_ids;
}

/**
 * Returns the list of allowed location IDs for a particular user.
 *
 * @param int $user_id The user ID.
 *
 * @return array List of allowed locations IDs.
 */
function geolocation_get_user_allowed_location_ids( $user_id ) {
	return get_user_meta( $user_id, 'geolocation_allowed_location_ids', true );
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

				if ( false !== strpos( $referer, '/wp-admin/' ) ) {
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
 *                                          default location. Default is FALSE.
 * @return array List of locations.
 */
function geolocation_get_locations( $include_default_location = false ) {
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
 * Returns a location object.
 *
 * @param slug $location_slug The slug of the location.
 *
 * @return WP_Term A term object representing the location or NULL if it does
 *                 not exist a location with such slug.
 */
function geolocation_get_location_by_slug( $location_slug ) {
	$location  = null;
	$locations = geolocation_get_locations();

	foreach ( $locations as $possible_location ) {
		if ( $location_slug === $possible_location->slug ) {
			$location = $possible_location;

			break;
		}
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

	$new_locations = apply_filters( 'geolocation_new_locations', $new_locations );

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
	$locations          = geolocation_get_locations();
	$extracted_location = null;

	if ( preg_match( '/^\/([^\/\?]+)/', $path, $matches ) ) {
		foreach ( $locations as $location ) {
			if ( $location->slug === $matches[1] ) {
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
 * @param boolean $include_default_location Optional. If TRUE and the visitor
 *                                          is not navigating a specific
 *                                          location, the default one will be
 *                                          returned. Default is FALSE.
 *
 * @return string The location slug, NULL if it cannot be found.
 */
function geolocation_get_visitor_location_slug( $include_default_location = false ) {
	$location_slug = null;
	$path          = null;

	if ( is_admin() ) {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
				$url = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );

				$path = wp_parse_url( $url, PHP_URL_PATH );
			}
		}
	} elseif ( isset( $_SERVER['GEOLOCATION_REQUEST_URI'] ) ) {
		$path = sanitize_text_field( wp_unslash( $_SERVER['GEOLOCATION_REQUEST_URI'] ) );
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
	$location_slug = null;

	/**
	 * We do the conversion only if it is a public URL and the visitor is
	 * navigating a location which is not the default one.
	 */
	if ( ! geolocation_exclude_url( $url ) ) {
		$location_slug = geolocation_get_visitor_location_slug();
		$parsed_url    = wp_parse_url( $url );

		$url = ( ! empty( $parsed_url['host'] ) ?
					( ! empty( $parsed_url['scheme'] ) ?
						$parsed_url['scheme'] . ':' : '' ) . '//' .
					( ! empty( $parsed_url['username'] ) ?
						$parsed_url['username'] .
						( ! empty( $parsed_url['password'] ) ?
							':' . $parsed_url['username'] : '' ) . '@'
						: '' ) .
					( ! empty( $parsed_url['host'] ) ?
						$parsed_url['host'] : '' ) : '' ) .
				( $location_slug ? '/' . $location_slug : '' ) .
				( ! empty( $parsed_url['path'] ) ?
					$parsed_url['path'] : '' ) .
				( ! empty( $parsed_url['query'] ) ?
					'?' . $parsed_url['query'] : '' ) .
				( ! empty( $parsed_url['fragment'] ) ?
					'#' . $parsed_url['fragment'] : '' );
	}

	/**
	 * For a suggestion made by the WordPress VIP team, we must remove the
	 * trailing slash from the URL if the URL is being filtered by the
	 * 'home_url' filter.
	 *
	 * We also do not want to untrail the slash when the template_redirect
	 * plugin is being fired because it will throw a notice in
	 * wp-includes/canonical.php due to the missing "path". But we do that only
	 * if there is not a specific location because it could led to redirection
	 * loop.
	 */
	if ( 'home_url' !== current_filter() || ( ! $location_slug && doing_action( 'template_redirect' ) ) ) {
		$url = trailingslashit( $url );
	} else {
		$url = untrailingslashit( $url );
	}

	return $url;
}

/**
 * Returns the visitor location ID from the current request.
 *
 * @param boolean $include_default_location Optional. If TRUE and the visitor
 *                                          is not navigating a specific
 *                                          location, the default one will be
 *                                          returned. Default is FALSE.
 *
 * @return int The location ID. Or NULL if there is not a selected market.
 */
function geolocation_get_visitor_location_id( $include_default_location = false ) {
	$location_id   = null;
	$location_slug = geolocation_get_visitor_location_slug( $include_default_location );
	$locations     = geolocation_get_locations( $include_default_location );

	foreach ( $locations as $location ) {
		if ( $location_slug === $location->slug ) {
			$location_id = $location->term_id;

			break;
		}
	}

	return $location_id;
}

/**
 * Prints dropdown of locations.
 *
 * @param mixed $args Optional. Settings for the dropdown.
 */
function geolocation_dropdown( $args = null ) {
	$args = wp_parse_args(
		$args,
		array(
			'id'       => 'geolocation_location_id',
			'multiple' => false,
			'name'     => 'geolocation_location_id',
			'selected' => null,
		)
	);

	if ( $args['multiple'] ) {
		$name_filter = function() use ( $args ) {
			return $args['name'];
		};

		add_filter( 'geolocation_walker_location_checklist_input_name', $name_filter );

		wp_terms_checklist(
			0,
			array(
				'name'          => $args['name'],
				'selected_cats' => $args['selected'],
				'taxonomy'      => 'location',
				'walker'        => new Geolocation_Walker_Location_Checklist(),
			)
		);

		remove_filter( 'geolocation_walker_location_checklist_input_name', $name_filter );
	} else {
		wp_dropdown_categories(
			array(
				'hide_empty'       => false,
				'id'               => $args['id'],
				'name'             => $args['name'],
				'orderby'          => 'name',
				'selected'         => $args['selected'],
				'show_option_none' => __( 'Select a location...', 'geolocation' ),
				'taxonomy'         => 'location',
			)
		);
	}
}

/**
 * Returns the location the current user is navigating.
 *
 * @return int The location ID.
 */
function geolocation_get_current_user_location_id() {
	$current_location_id = null;

	if ( isset( $_GET['geolocation_location_id'] ) ) {
		$current_location_id = absint( $_GET['geolocation_location_id'] ); // WPCS: CSRF ok.
	} else {
		if ( isset( $_SERVER['REQUEST_METHOD'] ) ) {
			$request_method = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) );

			/**
			 * If this a post request, we will try to check if the referer is
			 * containing the market.
			 */
			if ( 'POST' === $request_method ) {
				if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
					$http_referer = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
					$url          = wp_parse_url( $http_referer );

					if ( isset( $url['query'] ) ) {
						wp_parse_str( $url['query'], $query );

						if ( isset( $query['geolocation_location_id'] ) ) {
							$current_location_id = $query['geolocation_location_id'];
						}
					}
				}
			}
		}
	}

	return $current_location_id;
}

/**
 * Returns the current location ID using the one defined for the visitor if it
 * is a public request or the one defined for the user, in case of an
 * administration request.
 *
 * @return int The location ID.
 */
function geolocation_get_current_location_id() {
	if ( geolocation_is_admin() ) {
		$location_id = geolocation_get_current_user_location_id();
	} else {
		$location_id = geolocation_get_visitor_location_id();
	}

	return $location_id;
}

/**
 * Sanitizes a positive float number.
 *
 * @param mixed $value Number to be sanitized.
 *
 * @return float The sanitized value.
 */
function geolocation_absfloat( $value ) {
	return abs( floatval( $value ) );
}
