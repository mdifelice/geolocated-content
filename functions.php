<?php
function geolocation_get_rewrite_slug() {
	$rewrite_slug = get_option( 'geolocation_rewrite_slug' );

	if ( ! $rewrite_slug ) {
		$rewrite_slug = __( 'location', 'geolocation' );
	}

	return $rewrite_slug;
}

function geolocation_get_current_user_allowed_locations() {
	$current_user_id   = get_current_user_id();
	$allowed_locations = false;

	if ( $current_user_id ) {
		$allowed_locations = get_user_meta( $current_user_id, 'geolocation_allowed_locations', true );
	}

	return $allowed_locations;
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
 * Returns a location object.
 *
 * @param int $location_id The ID of the location.
 *
 * @return WP_Term A term object representing the location or NULL if it does
 *                 not exist a location with such ID.
 */
function geolocation_get_location( $location_id ) {
	$location = get_term( $location_id, 'location' );

	return $location;
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

