<?php
/**
 * Settings extension.
 *
 * @package Geolocation
 */

/**
 * Function to be hooked to the pre_option_$option_name filter.
 *
 * @param mixed  $value Original option value.
 * @param string $name  Name of option.
 *
 * @return mixed The value of the option.
 */
function geolocation_settings_pre_option( $value, $name ) {
	$location_id = geolocation_get_current_location_id();

	if ( $location_id ) {
		$value = apply_filters( 'geolocation_settings_pre_option_' . $name, geolocation_settings_get_option( $name, $location_id ), $location_id, $name );
	}

	return $value;
}

/**
 * Returns the meta key used to store an option for a specific location. 
 *
 * @param string $option_name The name of the option.
 *
 * @return string The meta key for that option.
 */ 
function geolocation_settings_get_option_meta_key( $option_name ) {
	return 'geolocation_' . $option_name;
}

/**
 * Returns the option value for an specific location.
 *
 * @param string $option_name The option name.
 * @param int	 $location_id The location ID.
 *
 * @return mixed The option value. FALSE if it is not found.
 */
function geolocation_settings_get_option( $option_name, $location_id ) {
	$meta_key = geolocation_settings_get_option_meta_key( $option_name );

	if ( metadata_exists( 'term', $location_id, $meta_key ) ) {
		$value = get_term_meta( $location_id, $meta_key, true );
	}

	return $value;
}

/**
 * Deletes the option value for a specific location.
 *
 * @param string $option_name The option name.
 * @param int	 $market_id   The market ID.
 *
 * @return boolean TRUE on success, FALSE otherwise.
 */
function geolocation_settings_delete_option( $option_name, $location_id ) {
	$status   = false;
	$meta_key = geolocation_settings_get_option_meta_key( $option_name );

	if ( metadata_exists( 'term', $location_id, $meta_key ) ) {
		$status = delete_term_meta( $location_id, $meta_key );
	}

	return $status;
}

add_action(	'admin_init', function() {
	add_settings_field(
		'geolocation_settings_enabled', 
		__( 'Enable geolocated settings?', 'geolocation' ),
		function() {
			printf(
				'<input type="checkbox" name="geolocation_settings_enabled" value="yes"%s />',
				checked( 'yes', get_option( 'geolocation_settings_enabled' ), false )
			);

			printf(
				'<p class="description">%s</p>',
				esc_html__( 'If you enable this, settings from other plugins can be modified to have different values for each location.', 'geolocation' )
			);
		},
		'geolocation',
		'geolocation_settings'
	);

	register_setting(
		'geolocation',
		'geolocation_settings_enabled',
		function( $value ) {
			return 'yes' === $value ? 'yes' : 'no';
		}
	);
} );

/**
 * In order to avoid searching for every option, only saved options through
 * this page will be filtered based on the location. To do that, we use an
 * special option called 'geolocation_settings_cache' which contains all the
 * options that were overriden.
 */
$geolocation_settings_enabled = get_option( 'geolocation_settings_enabled' );

if ( 'yes' === $geolocation_settings_enabled ) {
	/**
	 * Adds the location selector to the admin bar.
	 */
	add_action( 'admin_bar_menu', function( $wp_admin_bar ) {
		if ( is_admin() && current_user_can( 'manage_options' ) ) {
			$locations = geolocation_get_locations();

			if ( ! empty( $locations ) ) {
				$current_location_id   = geolocation_get_current_location_id();
				$default_location_name = __( 'Default', 'geolocation' );
				$current_location_name = $default_location_name;

				if ( isset( $locations[ $current_location_id ] ) ) {
					$current_location_name = $locations[ $current_location ]->name;
				}

				$node = array(
					'id'     => 'geolocation_location',
					'parent' => false,
					'title'  => sprintf( __( 'Current location: %s', 'geolocation' ), $current_location_name ),
				);

				$wp_admin_bar->add_node( $node );

				$node = array(
					'href'   => remove_query_arg( 'geolocation_location_id' ),
					'id'     => 'geolocation_location_default',
					'parent' => 'geolocation_location',
					'title'  => $default_location_name,
				);

				$wp_admin_bar->add_node( $node );

				foreach ( $locations as $location ) {
					$node = array(
						'href'   => add_query_arg( 'geolocation_location_id', $location->term_id ),
						'id'     => 'geolocation_location_' . $location->term_id,
						'parent' => 'geolocation_location',
						'title'  => $location->name,
					);

					$wp_admin_bar->add_node( $node );
				}
			}
		}
	}, 999 );

	add_action( 'admin_init', function() {
		global $wp_registered_settings;

		$geolocation_settings_cache = array();

		$location_id = geolocation_get_current_user_location_id();

		foreach ( $wp_registered_settings as $option_name => $args ) {
			if ( 0 !== strpos( $option_name, 'geolocation_' ) ) {
				$geolocation_settings_cache[ $option_name ] = true;

				/**
				 * We only allow this for 'manage_options'-capable users and if
				 * they are working over a valid market rather than the default
				 * one.
				 */
				if ( $location_id && current_user_can( 'manage_options' ) ) {
					add_filter( 'pre_update_option_' . $option_name, function( $value, $old_value, $option ) use ( $location_id ) {
						$current_value = geolocation_settings_get_option( $option, $location_id );

						if ( $current_value !== $value ) {
							update_term_meta( $location_id, 'market_' . $option, $value );
						}

						/**
						 * Returning the old value we make sure the original
						 * option is not updated.
						 *
						 * @link https://codex.wordpress.org/Plugin_API/Filter_Reference/pre_update_option_(option_name)
						 */
						return $old_value;
					}, 10, 3 );
				}
			}
		}

		/**
		 * We only save the geolocation settings if they were modified.
		 */
		if ( $geolocation_settings_cache !== get_option( 'geolocation_settings_cache' ) ) {
			update_option( 'geolocation_settings_cache', $geolocation_settings_cache );
		}
	}, 999 );

	/**
	 * Here we enqueue a script and a style files that allow us to identify
	 * visually which options are being altered by market.
	 */
	add_action( 'admin_enqueue_scripts', function() {
		if ( current_user_can( 'manage_options' ) ) {	
			wp_enqueue_style(
				'geolocation-settings',
				plugins_url( 'assets/administrator/css/settings.css', __DIR__ )
			);

			wp_enqueue_style( 'wp-jquery-ui-dialog' );

			wp_enqueue_script(
				'geolocation-settings',
				plugins_url( 'assets/administrator/js/settings.js', __DIR__ )
			);

			wp_enqueue_script( 'jquery-ui-dialog' );

			wp_localize_script(
				'geolocation-settings',
				'geolocation_settings',
				array(
					'setting_names' => array_keys( get_option( 'geolocation_settings_cache' ) ),
					'nonces'        => array(
						'load'   => wp_create_nonce( 'geolocation_settings_load' ),
						'delete' => wp_create_nonce( 'geolocation_settings_delete' ),
					),
					'i18n'          => array(
						'delete'         => __( 'Delete', 'geolocation' ),
						'reload'         => __( 'Reload', 'geolocation' ),
						'confirm_delete' => __( 'Are you sure to delete this value?', 'geolocation' ),
						'modal_loading'  => __( 'Loading...', 'geolocation' ),
						'modal_title'    => __( 'Viewing values for setting "%s"...', 'geolocation' ),
						'error_delete'   => __( 'Cannot delete setting, maybe it does not exist.', 'geolocation' ),
						'error_unknown'  => __( 'Unknown error. Please, try again later.', 'geolocation' ),
						'error_setting'  => __( 'Unknown setting.', 'geolocation' ),
					),
				)
			);
		}
	} );

	/**
	 * Added dialog for visualization of other markets settings.
	 */
	add_action( 'admin_footer', function() {
		if ( current_user_can( 'manage_options' ) ) {
			echo '<div id="geolocation-settings-modal"></div>';
		}
	} );

	/**
	 * AJAX handler for deleting setting.
	 */
	add_action( 'wp_ajax_geolocation_settings_delete', function() {
		if ( current_user_can( 'manage_options' ) ) {
			check_ajax_referer( 'geolocation_settings_delete' );

			if ( isset( $_POST['geolocation_settings_setting_name'] ) ) {
				$setting = sanitize_text_field( wp_unslash( $_POST['geolocation_settings_setting_name'] ) );
			} else {
				$setting = null;
			}

			if ( isset( $_POST['geolocation_settings_location_id'] ) ) {
				$location_id = absint( wp_unslash( $_POST['geolocation_settings_location_id'] ) );
			} else {
				$location_id = null;
			}

			$response = false;

			if ( $setting && null !== $location_id ) {
				if ( $location_id ) {
					$response = geolocation_settings_delete_option( $setting, $location_id );
				} else {
					$response = delete_option( $location_id );
				}
			}

			wp_send_json( $response );
		}
	} );

	/**
	 * AJAX handler for loading setting for all markets.
	 */
	add_action( 'wp_ajax_geolocation_settings_load', function() {
		if ( current_user_can( 'manage_options' ) ) {
			check_ajax_referer( 'geolocation_settings_load' );

			if ( isset( $_POST['geolocation_settings_setting_name'] ) ) {
				$setting = sanitize_text_field( wp_unslash( $_POST['geolocation_settings_setting_name'] ) );
			} else {
				$setting = null;
			}

			$response = null;

			if ( $setting ) {
				$response = array();

				remove_filter( 'pre_option_' . $setting, 'geolocation_settings_pre_option', 10, 3 );

				$response[] = array(
					'id'            => 0,
					'name'          => __( 'Default', 'geolocation' ),
					'setting_value' => var_export( get_option( $setting ), true ),
				);

				add_filter( 'pre_option_' . $setting, 'geolocation_settings_pre_option', 10, 3 );

				$locations = geolocation_get_locations();

				foreach ( $locations as $location ) {
					$response[] = array(
						'id'            => $location->term_id,
						'name'          => $location->name,
						'setting_value' => var_export( geolocation_settings_get_option( $setting, $market->term_id ), true ),
					);
				}
			}

			wp_send_json( $response );
		}
	} );

	/**
	 * We add a filter for each option previously registered in
	 * "geolocation_settings_cache".
	 */
	$geolocation_settings_cache = get_option( 'geolocation_settings_cache' );

	if ( $geolocation_settings_cache ) {
		foreach ( $geolocation_settings_cache as $name => $value ) {
			add_filter( 'pre_option_' . $name, 'geolocation_settings_pre_option', 10, 3 );
		}
	}
}
